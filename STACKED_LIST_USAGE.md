# Premium Stacked List Component - Usage Examples

## Overview
The enhanced stacked list component now features a premium floating action bar and enterprise-grade styling using Tailwind-first design principles.

## Key Design Improvements

### 1. Premium Floating Action Bar
- **Glass morphism effect**: Semi-transparent background with backdrop blur
- **Layered shadows**: Multiple shadow layers for depth and premium feel  
- **Visual connection**: Subtle line connecting to selected items
- **Mobile optimization**: Compact icon-only version for small screens
- **Enhanced accessibility**: Proper ARIA labels and keyboard navigation

### 2. Professional Visual Hierarchy
- **Enhanced controls bar**: Grouped inputs with subtle backgrounds
- **Improved headers**: Better typography and interactive states
- **Premium data rows**: Gradient hover effects and smooth micro-interactions
- **Consistent spacing**: Optimized for data density while maintaining readability

## Component Configuration

```php
// Example configuration for enterprise data table
$config = [
    'title' => 'Product Inventory',
    'subtitle' => 'Manage your product catalog',
    'searchable' => true,
    'search_placeholder' => 'Search products...',
    'export' => true,
    
    // Bulk actions with premium styling
    'bulk_actions' => [
        [
            'key' => 'export',
            'label' => 'Export Selected',
            'icon' => 'download',
            'variant' => 'outline'
        ],
        [
            'key' => 'update_pricing',
            'label' => 'Update Pricing',
            'icon' => 'currency-dollar',
            'variant' => 'primary'
        ],
        [
            'key' => 'delete',
            'label' => 'Delete',
            'icon' => 'trash',
            'variant' => 'danger'
        ]
    ],
    
    // Column configuration
    'columns' => [
        [
            'key' => 'name',
            'label' => 'Product Name',
            'type' => 'text',
            'sortable' => true,
            'font' => 'font-semibold'
        ],
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'badge',
            'badges' => [
                'active' => [
                    'label' => 'Active',
                    'class' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800',
                    'icon' => 'check-circle'
                ],
                'inactive' => [
                    'label' => 'Inactive',
                    'class' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
                    'icon' => 'x-circle'
                ]
            ]
        ],
        [
            'key' => 'actions',
            'label' => 'Actions',
            'type' => 'actions',
            'actions' => [
                [
                    'label' => 'Edit',
                    'icon' => 'pencil',
                    'route' => 'products.edit'
                ],
                [
                    'label' => 'View',
                    'icon' => 'eye',
                    'route' => 'products.show'
                ]
            ]
        ]
    ]
];
```

## Floating Action Bar Features

### Desktop Experience
- **Glassmorphism design**: Translucent background with backdrop blur
- **Visual hierarchy**: Clear separation between selection info and actions
- **Enhanced shadows**: Multi-layered shadows for premium depth
- **Gradient accents**: Subtle gradient overlays for visual interest
- **Connection indicator**: Visual line connecting to selected items above

### Mobile Experience  
- **Compact design**: Icon-only actions to save space
- **Touch-optimized**: Larger touch targets for mobile interaction
- **Responsive layout**: Adapts gracefully to small screens
- **Maintains functionality**: All actions accessible in compact form

### Accessibility
- **ARIA labels**: Proper labeling for screen readers
- **Keyboard navigation**: Full keyboard support
- **Focus indicators**: Clear focus states for all interactive elements
- **Color contrast**: WCAG AA compliant color combinations

## Technical Implementation

### CSS Classes Used
```html
<!-- Floating bar container -->
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 w-auto max-w-7xl mx-auto px-4 transition-all duration-300 ease-out">
    
    <!-- Premium glass morphism effect -->
    <div class="relative flex items-center justify-between min-w-96 
                bg-white/95 dark:bg-zinc-900/95 
                backdrop-blur-xl backdrop-saturate-150 
                rounded-2xl 
                border border-zinc-200/80 dark:border-zinc-700/80
                shadow-xl shadow-zinc-900/10 dark:shadow-black/20
                ring-1 ring-zinc-950/5 dark:ring-white/10">
```

### Dark Mode Support
- **Semantic color system**: Consistent dark mode variants
- **Enhanced contrast**: Improved readability in both themes
- **Subtle transitions**: Smooth theme switching animations

## Performance Considerations

- **Pure Tailwind**: No custom CSS for maximum performance
- **Efficient animations**: Hardware-accelerated transforms
- **Conditional rendering**: Mobile/desktop versions render conditionally
- **Optimized shadows**: Reduced repaints with efficient shadow definitions

## Browser Support

- **Modern browsers**: Full support for backdrop-filter and modern CSS
- **Graceful degradation**: Fallbacks for older browsers
- **Cross-platform**: Consistent experience across devices

## Best Practices

1. **Bulk Selection UX**: 
   - Show clear feedback when items are selected
   - Provide easy ways to select/deselect all
   - Use consistent selection patterns

2. **Action Organization**:
   - Group related actions together
   - Use appropriate button variants (primary, outline, danger)
   - Provide clear action labels and icons

3. **Mobile Optimization**:
   - Ensure touch targets are at least 44px
   - Use tooltips for icon-only actions
   - Consider action priority for limited space

4. **Performance**:
   - Limit bulk operations to reasonable batch sizes
   - Provide loading states for long operations
   - Use efficient data structures for large datasets

This premium stacked list component provides an enterprise-grade experience that scales from small datasets to thousands of items while maintaining excellent usability and visual polish.