<?php

namespace App\Enums;

use Illuminate\Support\Collection;

/**
 * 🔥✨ PRODUCT STATUS ENUM - COLLECTION-POWERED ✨🔥
 *
 * Type-safe product status with sexy Collection helpers
 */
enum ProductStatus: string
{
    case ACTIVE = 'active';
    case DRAFT = 'draft';
    case ARCHIVED = 'archived';
    case INACTIVE = 'inactive';

    /**
     * 🚀 Get all cases as a sexy Collection
     */
    public static function collection(): Collection
    {
        return collect(self::cases());
    }

    /**
     * 🎯 Get UI-friendly options Collection for forms
     */
    public static function options(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [
            $case->value => $case->label(),
        ]);
    }

    /**
     * 📝 Get human-readable labels Collection
     */
    public static function labels(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [
            $case->value => $case->label(),
        ]);
    }

    /**
     * 🎨 Get status values as Collection for validation
     */
    public static function values(): Collection
    {
        return collect(self::cases())->pluck('value');
    }

    /**
     * 🏷️ Human-readable label for this status
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::DRAFT => 'Draft',
            self::ARCHIVED => 'Archived',
            self::INACTIVE => 'Inactive',
        };
    }

    /**
     * 🎨 CSS class for UI styling
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'text-green-600 bg-green-100',
            self::DRAFT => 'text-yellow-600 bg-yellow-100',
            self::ARCHIVED => 'text-gray-600 bg-gray-100',
            self::INACTIVE => 'text-red-600 bg-red-100',
        };
    }

    /**
     * ✅ Check if status is considered "active"
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * 🔍 Check if status allows operations
     */
    public function allowsModifications(): bool
    {
        return $this->isActive();
    }

    /**
     * 🎯 Get default status for new products
     */
    public static function default(): self
    {
        return self::ACTIVE;
    }

    /**
     * 📊 Get status statistics from Collection
     */
    public static function getStatistics(Collection $products): Collection
    {
        return $products->groupBy('status')
            ->map->count()
            ->mapWithKeys(fn (int $count, string $status) => [
                $status => [
                    'count' => $count,
                    'label' => self::from($status)->label(),
                    'percentage' => $products->isNotEmpty() ? round(($count / $products->count()) * 100, 1) : 0,
                ],
            ]);
    }
}
