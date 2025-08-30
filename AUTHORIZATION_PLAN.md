# 🔐 COMPREHENSIVE AUTHORIZATION IMPLEMENTATION PLAN

## 🚨 **PRIORITY 1: CRITICAL SECURITY - Management & Admin Components**
### Status: ❌ NEEDS IMMEDIATE ATTENTION

**Livewire Components:**
- ✅ `Management/UserRoleManagement.php` - `authorize('manage-users')` *(DONE)*
- ✅ `Management/Users/UserIndex.php` - `authorize('manage-system')` *(DONE)*
- ❌ `LogDashboard.php` - `authorize('view-system-logs')`
- ❌ `Settings/DeleteUserForm.php` - `authorize('delete-users')` or admin role check

**Action Classes:**
- ✅ `Users/CreateUserAction.php` - `authorize('create-users')` *(DONE)*
- ❌ `Users/UpdateUserAction.php` - `authorize('edit-users')`
- ❌ `Users/DeleteUserAction.php` - `authorize('delete-users')`
- ❌ `Users/AssignUserRoleAction.php` - `authorize('assign-roles')`
- ❌ `Users/GetUsersWithRolesAction.php` - `authorize('view-users')`

---

## 📦 **PRIORITY 2: PRODUCT OPERATIONS - Core Business Logic**
### Status: 🟡 PARTIALLY DONE

**Livewire Components:**
- ✅ `Products/ProductIndex.php` - `authorize('view-products')` *(DONE)*
- ✅ `Products/ProductForm.php` - `authorize('create-products')` or `authorize('edit-products')` *(DONE)*
- ❌ `Products/ProductShow.php` - `authorize('view-product-details')`
- ❌ `Products/ProductVariants.php` - `authorize('view-variants')`
- ❌ `Products/ProductVariantsTab.php` - `authorize('view-variants')`
- ❌ `Products/ProductHistory.php` - `authorize('view-product-history')`
- ❌ `Products/ProductImages.php` - `authorize('manage-images')`
- ❌ `Products/ProductOverview.php` - `authorize('view-product-details')`
- ❌ `Products/ProductPricing.php` - `authorize('view-pricing')`
- ❌ `Products/ProductMarketplace.php` - `authorize('view-marketplace-connections')`
- ❌ `Products/VariantCreate.php` - `authorize('create-variants')`
- ❌ `ProductWizard.php` - `authorize('create-products')`

**Variant Components:**
- ❌ `Variants/VariantIndex.php` - `authorize('view-variants')`
- ❌ `Variants/VariantShow.php` - `authorize('view-variant-details')`  
- ❌ `Variants/VariantForm.php` - `authorize('create-variants')` or `authorize('edit-variants')`

**Action Classes:**
- ❌ `Products/CreateProductAction.php` - `authorize('create-products')`
- ❌ `Products/UpdateProductAction.php` - `authorize('edit-products')`
- ❌ `Products/DeleteProductAction.php` - `authorize('delete-products')`
- ❌ `Products/SaveProductAction.php` - `authorize('edit-products')`
- ❌ `Products/CreateVariantsAction.php` - `authorize('create-variants')`
- ❌ `Products/AttachImagesAction.php` - `authorize('assign-images')`
- ❌ `Variants/CreateVariantAction.php` - `authorize('create-variants')`
- ❌ `Variants/CreateVariantWithBarcodeAction.php` - `authorize('create-variants')` + `authorize('create-barcodes')`

---

## ⚡ **PRIORITY 3: BULK OPERATIONS & DANGEROUS ACTIONS**
### Status: 🟡 PARTIALLY DONE

**Livewire Components:**
- ✅ `BulkOperations/BulkOperationsCenter.php` - `authorize('bulk-edit-products')` *(DONE)*
- ❌ `BulkOperations/BulkImageOperation.php` - `authorize('bulk-upload-images')`
- ❌ `BulkOperations/BulkPricingOperation.php` - `authorize('bulk-edit-pricing')`
- ❌ `BulkOperations/BulkAttributeOperation.php` - `authorize('bulk-edit-attributes')`
- ❌ `BulkOperations/BulkMarketplaceAttributesOperation.php` - `authorize('bulk-edit-attributes')`

**Action Classes:**
- ❌ `Products/Pricing/BulkUpdateChannelPricingAction.php` - `authorize('bulk-update-pricing')`
- ❌ `Import/SimpleImportAction.php` - `authorize('import-products')`
- ❌ `Import/ProcessCSVRow.php` - `authorize('import-products')`
- ❌ `Import/CreateOrUpdateProduct.php` - `authorize('import-products')`
- ❌ `Import/CreateOrUpdateVariant.php` - `authorize('import-variants')`

---

## 🌐 **PRIORITY 4: MARKETPLACE & SYNC OPERATIONS**
### Status: ❌ NOT SECURED

**Livewire Components:**
- ❌ `Marketplace/AddIntegrationWizard.php` - `authorize('create-marketplace-connections')`
- ❌ `Marketplace/MarketplaceAttributesCard.php` - `authorize('view-marketplace-listings')`
- ❌ `Marketplace/MarketplaceSyncCards.php` - `authorize('view-sync-status')`
- ❌ `Marketplace/IdentifiersDashboard.php` - `authorize('view-marketplace-listings')`
- ❌ `SyncAccounts/SyncAccountsIndex.php` - `authorize('view-sync-accounts')`
- ❌ `SyncAccounts/CreateSyncAccount.php` - `authorize('create-sync-accounts')`

**Shopify Components:**
- ❌ `Shopify/ShopifyDashboard.php` - `authorize('view-sync-accounts')`
- ❌ `Shopify/ShopifySync.php` - `authorize('sync-to-marketplaces')`
- ❌ `Shopify/ShopifyWebhookManager.php` - `authorize('manage-sync-settings')`
- ❌ `Shopify/WebhookDashboard.php` - `authorize('view-sync-logs')`

**Action Classes:**
- ❌ `Marketplace/CreateMarketplaceIntegrationAction.php` - `authorize('create-marketplace-connections')`
- ❌ `Marketplace/UpdateMarketplaceIntegrationAction.php` - `authorize('edit-marketplace-connections')`
- ❌ `Marketplace/DeleteMarketplaceIntegrationAction.php` - `authorize('delete-marketplace-connections')`
- ❌ `Marketplace/TestMarketplaceConnectionAction.php` - `authorize('test-marketplace-connections')`
- ❌ All Shopify Actions - `authorize('sync-to-marketplaces')`

---

## 🖼️ **PRIORITY 5: IMAGE & MEDIA MANAGEMENT**
### Status: ❌ NOT SECURED

**Livewire Components:**
- ❌ `Images/ImageLibrary.php` - `authorize('view-images')`
- ❌ `Images/ImageSelector.php` - `authorize('view-images')`
- ❌ `Images/ImageShow.php` - `authorize('view-image-details')`
- ❌ `Images/ImageEditForm.php` - `authorize('edit-images')`
- ❌ `Images/ImageEditCore.php` - `authorize('edit-images')`
- ❌ `Images/ImageProductAttachment.php` - `authorize('assign-images')`
- ❌ `DAM/ImageShow.php` - `authorize('view-images')`
- ❌ `DAM/ImageEditForm.php` - `authorize('edit-images')`
- ❌ `DAM/ImageEditCore.php` - `authorize('edit-images')`
- ❌ `DAM/ImageProductAttachment.php` - `authorize('assign-images')`

**Action Classes:**
- ❌ `Images/UpdateImageAction.php` - `authorize('edit-images')`
- ❌ `Images/DeleteImageAction.php` - `authorize('delete-images')`

---

## 📊 **PRIORITY 6: REPORTING & SPECIALIZED FEATURES**
### Status: ❌ NOT SECURED

**Livewire Components:**
- ❌ `Barcodes/BarcodeIndex.php` - `authorize('view-barcodes')`
- ❌ `Barcodes/BarcodeImport.php` - `authorize('import-barcodes')`
- ❌ `Pricing/PricingDashboard.php` - `authorize('view-pricing')`
- ❌ `Pricing/PricingForm.php` - `authorize('edit-pricing')`
- ❌ `Pricing/PricingShow.php` - `authorize('view-pricing')`
- ❌ `Import/SimpleProductImport.php` - `authorize('import-products')`
- ❌ `Dashboard.php` - `authorize('view-dashboard')` (if restricted data)

**Action Classes:**
- ❌ `Barcodes/AssignBarcode.php` - `authorize('assign-barcodes')`
- ❌ `Barcodes/MarkBarcodesAssigned.php` - `authorize('edit-barcodes')`
- ❌ `Pricing/AssignPricing.php` - `authorize('edit-pricing')`
- ❌ All `Products/Pricing/*` actions - `authorize('manage-pricing')`

---

## 🔧 **PRIORITY 7: COMPONENTS & UTILITIES**
### Status: ❌ MOSTLY NOT SECURED

**Livewire Components:**
- ❌ `Components/AttributesCard.php` - `authorize('view-attributes')`
- ❌ `Components/FloatingActionBar.php` - Context-dependent authorization
- ❌ `Components/ProductVariantCombobox.php` - `authorize('view-products')`

**Low Priority (Settings/Auth):**
- ✅ Auth components (don't need additional auth)
- ✅ Settings components (user-specific, already protected by auth)

---

## 📝 **IMPLEMENTATION METHODOLOGY**

### **For Each Livewire Component:**
1. Add `mount()` method with appropriate `$this->authorize()` call
2. Add authorization to destructive methods (delete, update, etc.)
3. Use the `HasAuthorization` trait where beneficial

### **For Each Action Class:**
1. Add `use HasAuthorization` trait
2. Add authorization check at start of `performAction()` method
3. Use `authorizeWithRole()` for hybrid permission/role checks

### **Route Protection:**
1. Add middleware groups for permission levels
2. Protect admin routes with `can:manage-system`
3. Protect CRUD routes with appropriate `can:action-resource` patterns

---

## 🎯 **IMMEDIATE NEXT STEPS:**

1. **Start with Priority 1** - Secure all admin/management components
2. **Focus on destructive actions** - Delete, bulk operations, system changes
3. **Work systematically through priorities** - Don't skip around
4. **Test each component** after adding authorization
5. **Document any special permission requirements** you discover

**Questions for you:**
- Should regular 'user' role be able to view products but not edit?
- Should managers be able to do everything except user management?
- Any special business rules for image management permissions?
- Should we restrict dashboard access or leave it open to authenticated users?