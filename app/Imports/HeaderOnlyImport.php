<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithLimit;

class HeaderOnlyImport implements WithMultipleSheets, WithLimit
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