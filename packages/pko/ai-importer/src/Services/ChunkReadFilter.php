<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * PhpSpreadsheet read filter that keeps only the requested row window
 * plus the header row. Used by `SpreadsheetParser` in streamed mode to cap
 * peak memory on large workbooks.
 *
 *  - `$windowSheet` : when set, the row window applies to this sheet only and
 *    every other sheet is skipped (except `$fullSheets`).
 *  - `$fullSheets`  : sheets read in full, whatever the window — the secondary
 *    sheets joined on the primary must be indexed entirely.
 */
final class ChunkReadFilter implements IReadFilter
{
    /**
     * @param  array<int, string>  $fullSheets
     */
    public function __construct(
        private int $startRow,
        private int $endRow,
        private bool $keepHeaderRow = true,
        private ?string $windowSheet = null,
        private array $fullSheets = [],
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if ($this->fullSheets !== [] && in_array($worksheetName, $this->fullSheets, true)) {
            return true;
        }

        if ($this->windowSheet !== null && $worksheetName !== $this->windowSheet) {
            return false;
        }

        if ($this->keepHeaderRow && $row === 1) {
            return true;
        }

        return $row >= $this->startRow && $row <= $this->endRow;
    }

    public function setWindow(int $startRow, int $endRow): void
    {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
    }
}
