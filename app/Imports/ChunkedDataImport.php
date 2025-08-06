<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ChunkedDataImport implements ToArray, WithLimit, WithStartRow
{
    private int $limit;
    private int $startRow;

    public function __construct(int $limit = 100, int $startRow = 2)
    {
        $this->limit = $limit;
        $this->startRow = $startRow;
    }

    public function array(array $array): array
    {
        return $array;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function startRow(): int
    {
        return $this->startRow;
    }
}