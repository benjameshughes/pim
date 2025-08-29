<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * 🔐 SUPER GRANULAR ROLE AND PERMISSION SEEDER
 * 
 * Creates extremely detailed permissions for every aspect of the application.
 * Pattern: {action}-{resource} (e.g., view-products, create-users, etc.)
 * 
 * Actions: view, create, edit, update, delete, manage, bulk-edit, bulk-delete, 
 *          import, export, assign, upload, download, sync, process
 */
class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // === USER MANAGEMENT PERMISSIONS ===
        $userPermissions = [
            'view-users',
            'create-users', 
            'edit-users',
            'update-users',
            'delete-users',
            'bulk-edit-users',
            'bulk-delete-users',
            'manage-users',
            'view-user-profiles',
            'edit-user-profiles',
            'view-user-activity',
            'impersonate-users',
        ];

        // === ROLE & PERMISSION MANAGEMENT ===
        $rolePermissions = [
            'view-roles',
            'create-roles',
            'edit-roles', 
            'update-roles',
            'delete-roles',
            'assign-roles',
            'remove-roles',
            'view-permissions',
            'create-permissions',
            'edit-permissions',
            'update-permissions',
            'delete-permissions',
            'assign-permissions',
            'remove-permissions',
            'manage-role-permissions',
        ];

        // === PRODUCT MANAGEMENT PERMISSIONS ===
        $productPermissions = [
            'view-products',
            'create-products',
            'edit-products',
            'update-products', 
            'delete-products',
            'bulk-edit-products',
            'bulk-delete-products',
            'manage-products',
            'publish-products',
            'unpublish-products',
            'archive-products',
            'duplicate-products',
            'view-product-details',
            'edit-product-details',
            'view-product-history',
            'restore-products',
        ];

        // === PRODUCT VARIANT PERMISSIONS ===
        $variantPermissions = [
            'view-variants',
            'create-variants',
            'edit-variants',
            'update-variants',
            'delete-variants', 
            'bulk-edit-variants',
            'bulk-delete-variants',
            'manage-variants',
            'assign-variants',
            'remove-variants',
            'view-variant-details',
            'edit-variant-details',
            'duplicate-variants',
        ];

        // === PRODUCT PRICING PERMISSIONS ===
        $pricingPermissions = [
            'view-pricing',
            'create-pricing',
            'edit-pricing',
            'update-pricing',
            'delete-pricing',
            'bulk-edit-pricing',
            'bulk-update-pricing',
            'manage-pricing',
            'view-channel-pricing',
            'edit-channel-pricing',
            'sync-pricing',
            'calculate-pricing',
            'import-pricing',
            'export-pricing',
        ];

        // === IMAGE MANAGEMENT PERMISSIONS ===
        $imagePermissions = [
            'view-images',
            'upload-images',
            'edit-images',
            'update-images',
            'delete-images',
            'bulk-upload-images',
            'bulk-edit-images', 
            'bulk-delete-images',
            'manage-images',
            'assign-images',
            'remove-images',
            'process-images',
            'optimize-images',
            'download-images',
            'view-image-details',
            'organize-images',
        ];

        // === BARCODE MANAGEMENT PERMISSIONS ===
        $barcodePermissions = [
            'view-barcodes',
            'create-barcodes',
            'edit-barcodes', 
            'update-barcodes',
            'delete-barcodes',
            'bulk-edit-barcodes',
            'bulk-delete-barcodes',
            'manage-barcodes',
            'assign-barcodes',
            'remove-barcodes',
            'import-barcodes',
            'export-barcodes',
            'generate-barcodes',
            'scan-barcodes',
            'validate-barcodes',
        ];

        // === CATEGORY MANAGEMENT PERMISSIONS ===
        $categoryPermissions = [
            'view-categories',
            'create-categories',
            'edit-categories',
            'update-categories',
            'delete-categories',
            'bulk-edit-categories',
            'bulk-delete-categories', 
            'manage-categories',
            'assign-categories',
            'remove-categories',
            'organize-categories',
            'view-category-tree',
            'edit-category-tree',
        ];

        // === STOCK MANAGEMENT PERMISSIONS ===
        $stockPermissions = [
            'view-stock',
            'create-stock-entries',
            'edit-stock',
            'update-stock',
            'delete-stock-entries',
            'bulk-edit-stock',
            'bulk-update-stock',
            'manage-stock',
            'adjust-stock',
            'import-stock',
            'export-stock',
            'view-stock-history',
            'track-stock-movements',
        ];

        // === IMPORT/EXPORT PERMISSIONS ===
        $importExportPermissions = [
            'view-imports',
            'create-imports',
            'edit-imports',
            'delete-imports',
            'manage-imports',
            'import-products',
            'import-variants', 
            'import-images',
            'import-barcodes',
            'import-stock',
            'view-exports',
            'create-exports', 
            'edit-exports',
            'delete-exports',
            'manage-exports',
            'export-products',
            'export-variants',
            'export-pricing',
            'export-images', 
            'export-barcodes',
            'export-stock',
            'view-import-history',
            'view-export-history',
            'download-exports',
            'schedule-imports',
            'schedule-exports',
        ];

        // === MARKETPLACE INTEGRATION PERMISSIONS ===
        $marketplacePermissions = [
            'view-marketplaces',
            'create-marketplace-connections',
            'edit-marketplace-connections',
            'update-marketplace-connections',
            'delete-marketplace-connections',
            'manage-marketplace-connections',
            'sync-to-marketplaces',
            'sync-from-marketplaces',
            'view-marketplace-listings',
            'create-marketplace-listings',
            'edit-marketplace-listings',
            'delete-marketplace-listings',
            'publish-to-marketplaces',
            'unpublish-from-marketplaces',
            'view-sync-logs',
            'view-sync-status',
            'manage-sync-settings',
            'test-marketplace-connections',
            'view-marketplace-orders',
            'process-marketplace-orders',
        ];

        // === SYNC ACCOUNT MANAGEMENT PERMISSIONS ===
        $syncAccountPermissions = [
            'view-sync-accounts',
            'create-sync-accounts',
            'edit-sync-accounts',
            'update-sync-accounts',
            'delete-sync-accounts',
            'manage-sync-accounts',
            'test-sync-accounts',
            'authenticate-sync-accounts',
            'view-sync-account-logs',
        ];

        // === ATTRIBUTE MANAGEMENT PERMISSIONS ===
        $attributePermissions = [
            'view-attributes',
            'create-attributes',
            'edit-attributes',
            'update-attributes',
            'delete-attributes',
            'bulk-edit-attributes',
            'bulk-delete-attributes',
            'manage-attributes',
            'assign-attributes',
            'remove-attributes',
            'view-attribute-definitions',
            'create-attribute-definitions',
            'edit-attribute-definitions',
            'delete-attribute-definitions',
        ];

        // === TAG MANAGEMENT PERMISSIONS ===
        $tagPermissions = [
            'view-tags',
            'create-tags',
            'edit-tags',
            'update-tags', 
            'delete-tags',
            'bulk-edit-tags',
            'bulk-delete-tags',
            'manage-tags',
            'assign-tags',
            'remove-tags',
            'organize-tags',
        ];

        // === DASHBOARD & REPORTING PERMISSIONS ===
        $dashboardPermissions = [
            'view-dashboard',
            'view-analytics',
            'view-reports',
            'create-reports',
            'edit-reports',
            'delete-reports',
            'export-reports',
            'schedule-reports',
            'view-statistics',
            'view-metrics',
            'view-charts',
            'customize-dashboard',
        ];

        // === SYSTEM ADMINISTRATION PERMISSIONS ===
        $systemPermissions = [
            'access-management-area',
            'view-system-settings',
            'edit-system-settings',
            'update-system-settings',
            'manage-system-settings',
            'view-system-logs',
            'download-system-logs',
            'clear-system-logs',
            'view-system-info',
            'manage-cache',
            'clear-cache',
            'run-maintenance',
            'view-queues',
            'manage-queues',
            'retry-failed-jobs',
            'clear-failed-jobs',
            'manage-database',
            'backup-database',
            'restore-database',
        ];

        // === NOTIFICATION PERMISSIONS ===
        $notificationPermissions = [
            'view-notifications',
            'create-notifications',
            'edit-notifications',
            'delete-notifications',
            'manage-notifications',
            'send-notifications',
            'view-notification-history',
            'manage-notification-settings',
        ];

        // Combine all permissions
        $allPermissions = array_merge(
            $userPermissions,
            $rolePermissions,
            $productPermissions,
            $variantPermissions,
            $pricingPermissions,
            $imagePermissions,
            $barcodePermissions,
            $categoryPermissions,
            $stockPermissions,
            $importExportPermissions,
            $marketplacePermissions,
            $syncAccountPermissions,
            $attributePermissions,
            $tagPermissions,
            $dashboardPermissions,
            $systemPermissions,
            $notificationPermissions
        );

        // Create all permissions (avoiding duplicates)
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // === CREATE ROLES WITH SPECIFIC PERMISSIONS ===

        // ADMIN - Gets absolutely everything
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // MANAGER - Comprehensive permissions except system administration
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerPermissions = array_merge(
            $userPermissions,
            ['view-roles', 'assign-roles', 'remove-roles'], // Limited role management
            $productPermissions,
            $variantPermissions, 
            $pricingPermissions,
            $imagePermissions,
            $barcodePermissions,
            $categoryPermissions,
            $stockPermissions,
            $importExportPermissions,
            ['view-marketplaces', 'view-marketplace-listings', 'sync-to-marketplaces', 'view-sync-logs'], // Limited marketplace
            $attributePermissions,
            $tagPermissions,
            ['view-dashboard', 'view-analytics', 'view-reports', 'create-reports'], // Limited reporting
            ['access-management-area', 'view-system-logs'], // Very limited system access
            $notificationPermissions
        );
        $managerRole->givePermissionTo($managerPermissions);

        // USER - Basic operational permissions, no management features
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userPermissions = [
            // Basic user operations
            'view-users', 'view-user-profiles',
            
            // Product operations (no delete)
            'view-products', 'create-products', 'edit-products', 'update-products', 'view-product-details',
            
            // Variant operations (no delete)
            'view-variants', 'create-variants', 'edit-variants', 'update-variants', 'view-variant-details',
            
            // Basic pricing (view only)
            'view-pricing', 'view-channel-pricing',
            
            // Image operations (limited)
            'view-images', 'upload-images', 'view-image-details',
            
            // Barcode operations (view + assign)
            'view-barcodes', 'assign-barcodes', 'scan-barcodes',
            
            // Category operations (view + assign)
            'view-categories', 'assign-categories', 'view-category-tree',
            
            // Stock operations (view + basic updates)
            'view-stock', 'update-stock', 'view-stock-history',
            
            // Import operations (basic)
            'import-products', 'import-variants', 'view-import-history',
            
            // Marketplace operations (view only)
            'view-marketplaces', 'view-marketplace-listings', 'view-sync-logs',
            
            // Attributes (view + assign)
            'view-attributes', 'assign-attributes', 'view-attribute-definitions',
            
            // Tags (view + assign)  
            'view-tags', 'assign-tags',
            
            // Basic dashboard
            'view-dashboard', 'view-analytics',
            
            // Notifications
            'view-notifications'
        ];
        $userRole->givePermissionTo($userPermissions);

        $this->command->info('✅ Created ' . Permission::count() . ' super granular permissions');
        $this->command->info('✅ Created 3 roles: admin (all permissions), manager (comprehensive), user (basic)');
        $this->command->info('🔐 Super granular permission system ready!');
    }
}