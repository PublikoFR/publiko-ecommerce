<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
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
 *    of the primary sheet from disk. Cap peak memory on 100k+ row workbooks.
 *    The secondary sheets declared in `config.sheets` are loaded IN FULL at
 *    `loadStreamed()` (joins need them entirely) ; row counts come from the
 *    reader's `listWorksheetInfo()` (no full load).
 *
 * Both modes must yield the same rows : trailing empty rows are dropped
 * (same as `getHighestDataRow()` in full mode), empty rows in the middle kept.
 *
 * Cell values : formulas yield their computed value (cached value written by
 * Excel as a fallback when the calculation fails — a row never crashes on a
 * formula), rich text yields its plain text.
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

    /** @var array<string, int> sheet name → last row (streamed mode, from listWorksheetInfo). */
    private array $streamingLastRows = [];

    /** Excel error codes a failed calculation can return as a string. */
    private const EXCEL_ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A', '#CALC!', '#SPILL!'];

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

        $reader = $this->streamingReader();

        // Row counts without loading data (Xlsx: XMLReader scan of each sheet).
        $this->streamingLastRows = [];
        foreach ($reader->listWorksheetInfo($path) as $info) {
            $this->streamingLastRows[(string) $info['worksheetName']] = (int) $info['totalRows'];
        }

        $sheetNames = array_keys($this->streamingLastRows);
        $primary = (string) ($config['primary_sheet'] ?? ($sheetNames[0] ?? ''));
        $secondaries = array_values(array_filter(
            array_map('strval', array_keys((array) ($config['sheets'] ?? []))),
            static fn (string $name): bool => $name !== $primary && in_array($name, $sheetNames, true),
        ));

        // One load : header row of the primary + secondary sheets in full.
        $reader->setReadFilter(new ChunkReadFilter(1, 1, true, $primary, $secondaries));
        $this->spreadsheet = $reader->load($path);
    }

    private function streamingReader(): BaseReader
    {
        $reader = IOFactory::createReaderForFile((string) $this->streamingPath);
        if (! $reader instanceof BaseReader) {
            throw new \RuntimeException('Streamed mode requires a PhpSpreadsheet BaseReader.');
        }
        $reader->setReadDataOnly(true);

        return $reader;
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

    public function hasSheet(string $sheetName): bool
    {
        return $this->spreadsheet()->getSheetByName($sheetName) !== null;
    }

    /**
     * @return array<int, string>
     */
    public function sheetNames(): array
    {
        return $this->streamingPath !== null
            ? array_keys($this->streamingLastRows)
            : $this->spreadsheet()->getSheetNames();
    }

    /**
     * Intitulés de la ligne d'en-têtes d'une feuille (lettres de colonne si la
     * feuille n'a pas d'en-têtes ou si la cellule est vide).
     *
     * @return array<int, string>
     */
    public function headerNames(string $sheetName): array
    {
        return array_values($this->readHeaders($this->getSheet($sheetName)));
    }

    public function countRows(string $sheetName): int
    {
        $hasHeader = (bool) (($this->config['sheets'][$sheetName]['skip_first_row'] ?? true));
        $lastRow = $this->streamingPath !== null
            ? $this->streamingLastRow($sheetName)
            : $this->getSheet($sheetName)->getHighestDataRow();

        return max(0, $lastRow - ($hasHeader ? 1 : 0));
    }

    private function streamingLastRow(string $sheetName): int
    {
        if (! array_key_exists($sheetName, $this->streamingLastRows)) {
            throw new \RuntimeException("Sheet not found: {$sheetName}");
        }

        return $this->streamingLastRows[$sheetName];
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

        // Last row from listWorksheetInfo() (computed at loadStreamed, no full load).
        // It may include formatted-but-empty trailing rows : those are buffered
        // below and dropped, so both modes yield the same rows.
        $lastRow = $this->streamingLastRow($sheetName);

        $reader = $this->streamingReader();
        $reader->setLoadSheetsOnly([$sheetName]);
        $filter = new ChunkReadFilter(1, 1, true, $sheetName);
        $reader->setReadFilter($filter);

        /** @var array<int, array<string, mixed>> Empty rows held until a non-empty row follows. */
        $pendingEmpty = [];

        for ($start = $firstDataRow; $start <= $lastRow; $start += $this->streamingChunkSize) {
            $end = min($start + $this->streamingChunkSize - 1, $lastRow);
            $filter->setWindow($start, $end);
            $chunkSpreadsheet = $reader->load($this->streamingPath);
            $chunkSheet = $chunkSpreadsheet->getSheetByName($sheetName);
            if (! $chunkSheet) {
                continue;
            }

            for ($rowIndex = $start; $rowIndex <= $end; $rowIndex++) {
                $row = $this->readRow($chunkSheet, $rowIndex, $headers);
                if ($this->isEmptyRow($row)) {
                    $pendingEmpty[$rowIndex] = $row;

                    continue;
                }
                foreach ($pendingEmpty as $emptyIndex => $emptyRow) {
                    yield $emptyIndex => $emptyRow;
                }
                $pendingEmpty = [];
                yield $rowIndex => $row;
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
            // Colonne de jointure côté feuille secondaire : `join_col` (schéma PS)
            // prioritaire, puis `join_key` local, puis le `join_key` global.
            $joinKey = $sheetCfg['join_col'] ?? $sheetCfg['join_key'] ?? ($this->config['join_key'] ?? null);
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
                $val = $sheet->cellExists($letter.'1')
                    ? (string) ($this->cellValue($sheet->getCell($letter.'1')) ?? '')
                    : '';
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
            // `getCell()` CRÉE la cellule si elle n'existe pas : sur une feuille
            // creuse, lire 50 colonnes × 100k lignes matérialiserait des millions
            // de cellules vides en mémoire. On teste l'existence d'abord.
            $coord = $letter.$rowIndex;
            $row[$name] = $sheet->cellExists($coord) ? $this->cellValue($sheet->getCell($coord)) : null;
            // Alias by column letter too, so configs with `col: "M"` keep working
            $row[$letter] ??= $row[$name];
        }

        return $row;
    }

    /**
     * Valeur exploitable d'une cellule : jamais un objet non sérialisable,
     * jamais le texte d'une formule.
     */
    private function cellValue(Cell $cell): mixed
    {
        $value = $cell->getValue();

        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        if (! $cell->isFormula()) {
            return $value;
        }

        $cached = $cell->getOldCalculatedValue();

        // Streamed mode : only a window of rows is loaded, a formula pointing
        // outside it would be computed against empty cells. Trust Excel's cache.
        if ($this->streamingPath !== null && $cached !== null) {
            return $this->plain($cached);
        }

        try {
            $computed = $cell->getCalculatedValue();
        } catch (\Throwable) {
            $computed = null;
        }

        if ($computed === null || (is_string($computed) && in_array($computed, self::EXCEL_ERRORS, true))) {
            return $this->plain($cached ?? $computed);
        }

        return $this->plain($computed);
    }

    private function plain(mixed $value): mixed
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        // Formule matricielle : on garde la première valeur scalaire.
        while (is_array($value)) {
            $value = reset($value);
            if ($value === false) {
                return null;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
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
