<?php

namespace App\Actions\Import;

use App\Services\ImportMappingCache;

class ClearMappingCache
{
    public function execute(): void
    {
        $cache = app(ImportMappingCache::class);
        $cache->clearSavedMappings();
    }
}
