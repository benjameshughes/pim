<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HeaderOnlyImport implements WithLimit, WithMultipleSheets
{
    public function limit(): int
    {
        return 1; // Only read the header row
    }

    public function sheets(): array
    {
        return [];
    }
}
