<?php

namespace App\Actions\Import;

use App\Services\ImportMappingCache;

class LoadMappingCache
{
    public function execute(): array
    {
        $cache = app(ImportMappingCache::class);
        return $cache->getSavedMappings();
    }
}