<?php

namespace App\Enums;

enum ImageProcessingStatus: string
{
    case PENDING = 'pending';
    case UPLOADING = 'uploading';
    case PROCESSING = 'processing';
    case OPTIMISING = 'optimising';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    
    // Legacy alias - keep separate value for backward compatibility
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::UPLOADING => 'Uploading',
            self::PROCESSING => 'Processing',
            self::OPTIMISING => 'Optimising',
            self::SUCCESS => 'Success',
            self::COMPLETED => 'Completed', // Legacy
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::UPLOADING => 'blue',
            self::PROCESSING => 'blue',
            self::OPTIMISING => 'purple',
            self::SUCCESS => 'green',
            self::COMPLETED => 'green', // Legacy
            self::FAILED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::UPLOADING => 'arrow-up-tray',
            self::PROCESSING => 'arrow-path',
            self::OPTIMISING => 'sparkles',
            self::SUCCESS => 'check-circle',
            self::COMPLETED => 'check-circle', // Legacy
            self::FAILED => 'x-circle',
        };
    }
}
