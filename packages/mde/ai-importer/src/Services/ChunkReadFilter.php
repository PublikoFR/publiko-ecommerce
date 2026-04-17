<?php

declare(strict_types=1);

namespace Mde\AiImporter\Services;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * PhpSpreadsheet read filter that keeps only the requested row window
 * plus the header row. Used by `SpreadsheetParser::iterateRowsStreamed()`
 * to cap peak memory on large workbooks.
 */
final class ChunkReadFilter implements IReadFilter
{
    public function __construct(
        private int $startRow,
        private int $endRow,
        private bool $keepHeaderRow = true,
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
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
