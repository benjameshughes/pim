<?php

namespace App\UI\Toasts\Facades;

use App\UI\Toasts\Toast as ToastNotification;
use App\UI\Toasts\ToastManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ToastNotification success(string $title, ?string $body = null)
 * @method static ToastNotification error(string $title, ?string $body = null)
 * @method static ToastNotification warning(string $title, ?string $body = null)
 * @method static ToastNotification info(string $title, ?string $body = null)
 * @method static ToastManager add(\App\UI\Toasts\Contracts\ToastContract $toast)
 * @method static \Illuminate\Support\Collection getToasts()
 * @method static \Illuminate\Support\Collection getToastsByPosition()
 * @method static ToastManager clear()
 * @method static ToastManager remove(string $toastId)
 * @method static ToastManager flash()
 * @method static \Illuminate\Support\Collection flush()
 *
 * @see ToastManager
 */
class Toast extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ToastManager::class;
    }
}