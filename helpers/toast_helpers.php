<?php

if (!function_exists('toast')) {
    /**
     * Create a new toast notification.
     *
     * @param string|null $title
     * @param string|null $body
     * @return \App\UI\Toasts\Toast|\App\UI\Toasts\ToastManager
     */
    function toast(?string $title = null, ?string $body = null)
    {
        $toastManager = app(\App\UI\Toasts\ToastManager::class);

        if ($title === null) {
            return $toastManager;
        }

        return $toastManager->info($title, $body);
    }
}

if (!function_exists('toast_success')) {
    /**
     * Create a success toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\UI\Toasts\Toast
     */
    function toast_success(string $title, ?string $body = null): \App\UI\Toasts\Toast
    {
        return app(\App\UI\Toasts\ToastManager::class)->success($title, $body);
    }
}

if (!function_exists('toast_error')) {
    /**
     * Create an error toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\UI\Toasts\Toast
     */
    function toast_error(string $title, ?string $body = null): \App\UI\Toasts\Toast
    {
        return app(\App\UI\Toasts\ToastManager::class)->error($title, $body);
    }
}

if (!function_exists('toast_warning')) {
    /**
     * Create a warning toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\UI\Toasts\Toast
     */
    function toast_warning(string $title, ?string $body = null): \App\UI\Toasts\Toast
    {
        return app(\App\UI\Toasts\ToastManager::class)->warning($title, $body);
    }
}

if (!function_exists('toast_info')) {
    /**
     * Create an info toast notification.
     *
     * @param string $title
     * @param string|null $body
     * @return \App\UI\Toasts\Toast
     */
    function toast_info(string $title, ?string $body = null): \App\UI\Toasts\Toast
    {
        return app(\App\UI\Toasts\ToastManager::class)->info($title, $body);
    }
}