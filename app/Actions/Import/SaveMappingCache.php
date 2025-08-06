<?php

namespace App\Actions\Import;

use App\Services\ImportMappingCache;

class SaveMappingCache
{
    public function execute(array $columnMapping): void
    {
        $cache = app(ImportMappingCache::class);
        $cache->saveMappings($columnMapping);
    }
}