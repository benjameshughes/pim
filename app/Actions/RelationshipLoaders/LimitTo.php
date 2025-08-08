<?php

namespace App\Actions\RelationshipLoaders;

class LimitTo
{
    public function __construct(
        public int $limit = 1
    ) {}

    public function apply($query)
    {
        return $query->limit($this->limit);
    }

    public static function one(): array
    {
        return [
            'action' => self::class,
            'limit' => 1,
        ];
    }

    public static function make(int $limit): array
    {
        return [
            'action' => self::class,
            'limit' => $limit,
        ];
    }
}
