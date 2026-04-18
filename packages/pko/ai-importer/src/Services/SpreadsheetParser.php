<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a multi-sheet spreadsheet (xlsx/xls/csv) and yields rows of the
 * primary sheet as associative arrays keyed by the header row.
 *
 * Two loading modes:
 *
 *  - `load($path, $config)` — full workbook in memory. Fine up to ~50k rows.
 *  - `loadStreamed($path, $config, $chunkSize = 2000)` — activates
 *    `ChunkReadFilter` so each yielded row-window loads only that window
 *    from disk. Cap peak memory on 100k+ row workbooks.
 *
 * Secondary sheets are pre-indexed by `join_key` on first access (lazy)
 * so `multiline_aggregate` actions can resolve 1-N rows in O(1). Note
 * that index building still reads the full secondary sheet — the assumption
 * is that secondary sheets (logistics, images) are much smaller than the
 * primary. If that ever breaks we'll need a streamed index too.
 */
final class SpreadsheetParser
{
    private ?Spreadsheet $spreadsheet = null;

    private ?string $streamingPath = null;

    private int $streamingChunkSize = 2000;

    /** @var array<string, array<string, array<int, array<string, mixed>>>> */
    private array $indexes = [];

    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function load(string $path, array $config): void
    {
        $this->spreadsheet = IOFactory::load($path);
        $this->config = $config;
        $this->indexes = [];
        $this->streamingPath = null;
    }

    /**
     * Activate streamed mode — only the currently iterated row window is
     * held in memory. Call `iterateRows()` as usual.
     *
     * @param  array<string, mixed>  $config
     */
    public function loadStreamed(string $path, array $config, int $chunkSize = 2000): void
    {
        $this->config = $config;
        $this->indexes = [];
        $this->streamingPath = $path;
        $this->streamingChunkSize = max(100, $chunkSize);
        // Load once with a filter covering just row 1 (for headers + sheet discovery).
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ChunkReadFilter(1, 1));
        $this->spreadsheet = $reader->load($path);
    }

    public function primarySheetName(): string
    {
        $name = $this->config['primary_sheet'] ?? null;
        if (! $name) {
            // Fall back to the first sheet
            $name = $this->spreadsheet()->getSheetNames()[0] ?? null;
        }
        if (! $name) {
            throw new \RuntimeException('No primary sheet available in workbook.');
        }

        return (string) $name;
    }

    public function countRows(string $sheetName): int
    {
        $sheet = $this->getSheet($sheetName);
        $hasHeader = (bool) (($this->config['sheets'][$sheetName]['skip_first_row'] ?? true));

        return max(0, $sheet->getHighestDataRow() - ($hasHeader ? 1 : 0));
    }

    /**
     * Yield rows of the primary (or named) sheet as associative arrays
     * keyed by header column name.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function iterateRows(string $sheetName, int $startAfterRow = 0): Generator
    {
        if ($this->streamingPath !== null) {
            yield from $this->iterateStreamed($sheetName, $startAfterRow);

            return;
        }

        $sheet = $this->getSheet($sheetName);
        $headers = $this->readHeaders($sheet);
        $hasHeader = (bool) (($this->config['sheets'][$sheetName]['skip_first_row'] ?? true));

        $firstDataRow = ($hasHeader ? 2 : 1) + $startAfterRow;
        $lastRow = $sheet->getHighestDataRow();

        for ($rowIndex = $firstDataRow; $rowIndex <= $lastRow; $rowIndex++) {
            $row = $this->readRow($sheet, $rowIndex, $headers);
            yield $rowIndex => $row;
        }
    }

    /**
     * Streamed variant: reload the workbook in windows of `streamingChunkSize`
     * rows, reusing the same filter instance to reset its bounds cheaply.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function iterateStreamed(string $sheetName, int $startAfterRow): Generator
    {
        $headerSheet = $this->getSheet($sheetName);
        $headers = $this->readHeaders($headerSheet);
        $hasHeader = (bool) (($this->config['sheets'][$sheetName]['skip_first_row'] ?? true));
        $firstDataRow = ($hasHeader ? 2 : 1) + $startAfterRow;

        // Cheap one-shot full load to find the real last row. If that proves too heavy
        // on huge files, swap in a reader-level scan (PhpSpreadsheet Xlsx reader supports
        // `sheetInfo()` with row counts without loading data).
        $reader = IOFactory::createReaderForFile($this->streamingPath);
        $reader->setReadDataOnly(true);
        $probe = $reader->load($this->streamingPath)->getSheetByName($sheetName);
        $lastRow = $probe ? $probe->getHighestDataRow() : 0;
        unset($probe);

        $filter = new ChunkReadFilter(1, 1);
        $reader->setReadFilter($filter);

        for ($start = $firstDataRow; $start <= $lastRow; $start += $this->streamingChunkSize) {
            $end = min($start + $this->streamingChunkSize - 1, $lastRow);
            $filter->setWindow($start, $end);
            $chunkSpreadsheet = $reader->load($this->streamingPath);
            $chunkSheet = $chunkSpreadsheet->getSheetByName($sheetName);
            if (! $chunkSheet) {
                continue;
            }

            for ($rowIndex = $start; $rowIndex <= $end; $rowIndex++) {
                yield $rowIndex => $this->readRow($chunkSheet, $rowIndex, $headers);
            }

            // Release the chunk workbook before loading the next one — this is what
            // actually keeps peak memory flat.
            $chunkSpreadsheet->disconnectWorksheets();
            unset($chunkSpreadsheet, $chunkSheet);
        }
    }

    /**
     * Build an index of secondary sheets on their `join_key`, exposed to
     * actions through `ExecutionContext::$sheets`.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function secondarySheetsFor(mixed $primaryJoinValue): array
    {
        $out = [];
        foreach ($this->config['sheets'] ?? [] as $sheetName => $sheetCfg) {
            if ($sheetName === ($this->config['primary_sheet'] ?? null)) {
                continue;
            }
            $joinKey = $sheetCfg['join_key'] ?? ($this->config['join_key'] ?? null);
            if (! $joinKey) {
                continue;
            }
            $this->indexes[$sheetName] ??= $this->buildIndex($sheetName, (string) $joinKey);
            $out[$sheetName] = $this->indexes[$sheetName][(string) $primaryJoinValue] ?? [];
        }

        return $out;
    }

    /**
     * Used by ParseFileToStagingJob to extract the join key from the primary row.
     */
    public function joinKeyName(): ?string
    {
        return $this->config['join_key'] ?? null;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildIndex(string $sheetName, string $joinKey): array
    {
        $sheet = $this->getSheet($sheetName);
        $headers = $this->readHeaders($sheet);
        $hasHeader = (bool) (($this->config['sheets'][$sheetName]['skip_first_row'] ?? true));

        $index = [];
        $lastRow = $sheet->getHighestDataRow();
        $firstDataRow = $hasHeader ? 2 : 1;

        for ($rowIndex = $firstDataRow; $rowIndex <= $lastRow; $rowIndex++) {
            $row = $this->readRow($sheet, $rowIndex, $headers);
            $key = (string) ($row[$joinKey] ?? '');
            if ($key === '') {
                continue;
            }
            $index[$key][] = $row;
        }

        return $index;
    }

    /**
     * @return array<int, string> column letter => header name (or A/B/C if no header)
     */
    private function readHeaders(Worksheet $sheet): array
    {
        $hasHeader = (bool) (($this->config['sheets'][$sheet->getTitle()]['skip_first_row'] ?? true));
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $headers = [];
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            if ($hasHeader) {
                $val = (string) ($sheet->getCell($letter.'1')->getValue() ?? '');
                $headers[$c] = $val !== '' ? $val : $letter;
            } else {
                $headers[$c] = $letter;
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function readRow(Worksheet $sheet, int $rowIndex, array $headers): array
    {
        $row = [];
        foreach ($headers as $colIndex => $name) {
            $letter = Coordinate::stringFromColumnIndex($colIndex);
            $row[$name] = $sheet->getCell($letter.$rowIndex)->getValue();
            // Alias by column letter too, so configs with `col: "M"` keep working
            $row[$letter] ??= $row[$name];
        }

        return $row;
    }

    private function getSheet(string $name): Worksheet
    {
        $sheet = $this->spreadsheet()->getSheetByName($name);
        if (! $sheet) {
            throw new \RuntimeException("Sheet not found: {$name}");
        }

        return $sheet;
    }

    private function spreadsheet(): Spreadsheet
    {
        if (! $this->spreadsheet) {
            throw new \LogicException('SpreadsheetParser::load() must be called first.');
        }

        return $this->spreadsheet;
    }
}
