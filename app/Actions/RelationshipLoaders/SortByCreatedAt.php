<?php

namespace App\Actions\RelationshipLoaders;

class SortByCreatedAt
{
    public function __construct(
        public string $direction = 'desc'
    ) {}

    public function apply($query)
    {
        return $query->orderBy('created_at', $this->direction);
    }

    public static function desc(): array
    {
        return [
            'action' => self::class,
            'direction' => 'desc',
        ];
    }

    public static function asc(): array
    {
        return [
            'action' => self::class,
            'direction' => 'asc',
        ];
    }
}
