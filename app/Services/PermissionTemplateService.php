<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;

/**
 * 🎭 PERMISSION TEMPLATE SERVICE
 *
 * Provides pre-configured permission templates for common role types.
 * Makes permission management user-friendly by grouping 241 permissions
 * into logical templates that admins can easily understand and apply.
 */
class PermissionTemplateService
{
    /**
     * Get all available permission templates
     */
    public static function getTemplates(): array
    {
        return [
            'basic_user' => [
                'name' => 'Basic User',
                'description' => 'Limited access - can view and edit own content only',
                'permission_count' => 25,
                'color' => 'blue',
                'icon' => 'user',
                'permissions' => self::getBasicUserPermissions(),
            ],
            'content_manager' => [
                'name' => 'Content Manager',
                'description' => 'Product & content management - no user admin or system access',
                'permission_count' => 85,
                'color' => 'green',
                'icon' => 'pencil-square',
                'permissions' => self::getContentManagerPermissions(),
            ],
            'system_manager' => [
                'name' => 'System Manager',
                'description' => 'Advanced access - user management, imports, marketplace sync',
                'permission_count' => 180,
                'color' => 'yellow',
                'icon' => 'cog-6-tooth',
                'permissions' => self::getSystemManagerPermissions(),
            ],
            'full_admin' => [
                'name' => 'Full Administrator',
                'description' => 'Complete system access - all 241 permissions included',
                'permission_count' => 241,
                'color' => 'red',
                'icon' => 'key',
                'permissions' => self::getFullAdminPermissions(),
            ],
        ];
    }

    /**
     * Get template by key
     */
    public static function getTemplate(string $key): ?array
    {
        return self::getTemplates()[$key] ?? null;
    }

    /**
     * Apply template to user or role
     */
    public static function applyTemplate(string $templateKey, $userOrRole): bool
    {
        $template = self::getTemplate($templateKey);

        if (! $template) {
            return false;
        }

        // Remove all existing permissions first
        $userOrRole->permissions()->detach();

        // Apply template permissions
        $userOrRole->givePermissionTo($template['permissions']);

        return true;
    }

    /**
     * Basic User Template (25 permissions)
     * - View products, variants, basic operations
     * - No delete, no bulk operations, no management
     */
    private static function getBasicUserPermissions(): array
    {
        return [
            // Basic Product Access
            'view-products', 'view-product-details', 'create-products', 'edit-products', 'update-products',

            // Basic Variant Access
            'view-variants', 'view-variant-details', 'create-variants', 'edit-variants', 'update-variants',

            // Basic Pricing (view only)
            'view-pricing', 'view-channel-pricing',

            // Basic Image Operations
            'view-images', 'upload-images', 'view-image-details',

            // Barcode Operations (view + assign)
            'view-barcodes', 'assign-barcodes', 'scan-barcodes',

            // Category Operations (view + assign)
            'view-categories', 'assign-categories', 'view-category-tree',

            // Basic Stock
            'view-stock', 'view-stock-history',

            // Dashboard & Self
            'view-dashboard', 'view-user-profiles',
        ];
    }

    /**
     * Content Manager Template (85 permissions)
     * - Full product/variant/image/barcode management
     * - Import/export capabilities
     * - No user management, limited system access
     */
    private static function getContentManagerPermissions(): array
    {
        return array_merge(
            self::getBasicUserPermissions(),
            [
                // Full Product Management
                'delete-products', 'bulk-edit-products', 'bulk-delete-products', 'manage-products',
                'publish-products', 'unpublish-products', 'archive-products', 'duplicate-products',
                'view-product-history', 'restore-products',

                // Full Variant Management
                'delete-variants', 'bulk-edit-variants', 'bulk-delete-variants', 'manage-variants',
                'assign-variants', 'remove-variants', 'duplicate-variants',

                // Pricing Management
                'create-pricing', 'edit-pricing', 'update-pricing', 'delete-pricing',
                'bulk-edit-pricing', 'bulk-update-pricing', 'manage-pricing',
                'edit-channel-pricing', 'sync-pricing', 'calculate-pricing',

                // Full Image Management
                'edit-images', 'update-images', 'delete-images', 'bulk-upload-images',
                'bulk-edit-images', 'bulk-delete-images', 'manage-images',
                'assign-images', 'remove-images', 'process-images', 'optimize-images',
                'download-images', 'organize-images',

                // Full Barcode Management
                'create-barcodes', 'edit-barcodes', 'update-barcodes', 'delete-barcodes',
                'bulk-edit-barcodes', 'bulk-delete-barcodes', 'manage-barcodes',
                'remove-barcodes', 'generate-barcodes', 'validate-barcodes',

                // Category Management
                'create-categories', 'edit-categories', 'update-categories', 'delete-categories',
                'bulk-edit-categories', 'bulk-delete-categories', 'manage-categories',
                'remove-categories', 'organize-categories', 'edit-category-tree',

                // Stock Management
                'create-stock-entries', 'edit-stock', 'update-stock', 'delete-stock-entries',
                'bulk-edit-stock', 'bulk-update-stock', 'manage-stock', 'adjust-stock',
                'track-stock-movements',

                // Attributes & Tags
                'view-attributes', 'create-attributes', 'edit-attributes', 'update-attributes',
                'delete-attributes', 'bulk-edit-attributes', 'assign-attributes', 'remove-attributes',
                'view-attribute-definitions', 'create-attribute-definitions', 'edit-attribute-definitions',

                'create-tags', 'edit-tags', 'update-tags', 'delete-tags',
                'bulk-edit-tags', 'manage-tags', 'assign-tags', 'remove-tags', 'organize-tags',

                // Import/Export
                'import-products', 'import-variants', 'import-images', 'import-barcodes', 'import-stock',
                'export-products', 'export-variants', 'export-images', 'export-barcodes', 'export-stock',
                'view-import-history', 'view-export-history', 'download-exports',
            ]
        );
    }

    /**
     * System Manager Template (180 permissions)
     * - Everything from Content Manager
     * - User management capabilities
     * - Marketplace & sync management
     * - Advanced reporting
     */
    private static function getSystemManagerPermissions(): array
    {
        return array_merge(
            self::getContentManagerPermissions(),
            [
                // User Management
                'view-users', 'create-users', 'edit-users', 'update-users', 'delete-users',
                'bulk-edit-users', 'manage-users', 'edit-user-profiles', 'view-user-activity',

                // Role Management (limited)
                'view-roles', 'assign-roles', 'remove-roles',

                // Advanced Import/Export
                'view-imports', 'create-imports', 'edit-imports', 'delete-imports', 'manage-imports',
                'view-exports', 'create-exports', 'edit-exports', 'delete-exports', 'manage-exports',
                'schedule-imports', 'schedule-exports',

                // Marketplace Management
                'view-marketplaces', 'create-marketplace-connections', 'edit-marketplace-connections',
                'update-marketplace-connections', 'delete-marketplace-connections', 'manage-marketplace-connections',
                'sync-to-marketplaces', 'sync-from-marketplaces', 'view-marketplace-listings',
                'create-marketplace-listings', 'edit-marketplace-listings', 'delete-marketplace-listings',
                'publish-to-marketplaces', 'unpublish-from-marketplaces', 'view-sync-logs',
                'view-sync-status', 'manage-sync-settings', 'test-marketplace-connections',

                // Sync Account Management
                'view-sync-accounts', 'create-sync-accounts', 'edit-sync-accounts', 'update-sync-accounts',
                'delete-sync-accounts', 'manage-sync-accounts', 'test-sync-accounts',
                'authenticate-sync-accounts', 'view-sync-account-logs',

                // Advanced Analytics & Reports
                'view-analytics', 'view-reports', 'create-reports', 'edit-reports', 'delete-reports',
                'export-reports', 'schedule-reports', 'view-statistics', 'view-metrics', 'view-charts',
                'customize-dashboard',

                // Notifications
                'view-notifications', 'create-notifications', 'edit-notifications', 'delete-notifications',
                'manage-notifications', 'send-notifications', 'view-notification-history',
                'manage-notification-settings',

                // Limited System Access
                'access-management-area', 'view-system-logs',
            ]
        );
    }

    /**
     * Full Admin Template (241 permissions)
     * - Absolutely everything including system administration
     */
    private static function getFullAdminPermissions(): array
    {
        // Just get all permissions from database
        return Permission::all()->pluck('name')->toArray();
    }

    /**
     * Get template statistics for display
     */
    public static function getTemplateStats(): array
    {
        $templates = self::getTemplates();

        return [
            'total_templates' => count($templates),
            'total_permissions' => 241,
            'templates' => array_map(function ($template) {
                return [
                    'name' => $template['name'],
                    'permission_count' => $template['permission_count'],
                    'percentage' => round(($template['permission_count'] / 241) * 100, 1),
                ];
            }, $templates),
        ];
    }
}
