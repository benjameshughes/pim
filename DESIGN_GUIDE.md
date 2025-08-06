# Design & Style Guide

## UI Framework
- **Primary Framework**: Flux UI Basic/Free Components Only
- **Styling**: Tailwind CSS 4.0 with dark mode support
- **Layout**: Sidebar-based navigation with responsive design

## Color Palette
- **Background**: 
  - Light: `bg-white`
  - Dark: `bg-zinc-800` (body), `bg-zinc-900` (sidebar)
- **Borders**: 
  - Light: `border-zinc-200`
  - Dark: `border-zinc-700`
- **Text**: 
  - Primary: `text-zinc-900 dark:text-white`
  - Secondary: `text-zinc-600 dark:text-zinc-400`
- **Accents**: 
  - Neutral: `bg-zinc-50 dark:bg-zinc-900`
  - Pattern: `stroke-gray-900/20 dark:stroke-neutral-100/20`

## Layout Structure
- **Sidebar**: Fixed, stashable on mobile (`flux:sidebar`)
- **Main Content**: Full-height with `flux:main` wrapper
- **Grid System**: Use CSS Grid for layouts (`grid`, `md:grid-cols-3`)
- **Spacing**: Consistent gap classes (`gap-4`, `gap-6`, `space-y-6`)

## Available Flux Free Components

### Basic Components
- `flux:button` - Buttons with variants (primary, secondary)
- `flux:input` - Form inputs with labels and validation
- `flux:textarea` - Multi-line text inputs
- `flux:checkbox` - Checkboxes with labels
- `flux:radio` - Radio buttons
- `flux:select` - Dropdown selects
- `flux:label` - Form labels
- `flux:text` - Text content
- `flux:link` - Links with styling
- `flux:heading` - Page headings
- `flux:subheading` - Subheadings

### Layout Components
- `flux:main` - Main content wrapper
- `flux:sidebar` - Sidebar navigation
- `flux:header` - Page headers
- `flux:spacer` - Flexible spacing

### Navigation Components
- `flux:navlist` - Navigation lists
- `flux:navlist.item` - Navigation items
- `flux:navlist.group` - Grouped navigation

## Components Standards

### Forms
- **Container**: `form` with `flex flex-col gap-6` or `space-y-6`
- **Inputs**: `flux:input` with labels, placeholders, and validation
- **Buttons**: `flux:button variant="primary"` for main actions
- **Selects**: `flux:select` for dropdown options
- **Textareas**: `flux:textarea` for long text
- **Layout**: Full-width buttons (`class="w-full"`)

### Cards & Containers
- **Cards**: Custom divs with `rounded-xl border border-neutral-200 dark:border-neutral-700`
- **Aspect Ratios**: `aspect-video` for media containers
- **Overflow**: `overflow-hidden` for rounded containers

### Navigation
- **Sidebar Nav**: `flux:navlist`
- **Groups**: `flux:navlist.group` with headings
- **Items**: `flux:navlist.item` with icons and wire:navigate
- **Custom dropdowns**: Build with standard HTML/Tailwind

### Typography
- **Headings**: `flux:heading` and `flux:subheading`
- **Body Text**: `flux:text` for paragraphs
- **Links**: `flux:link` with hover states
- **Small Text**: `text-sm` and `text-xs` for meta information

### Data Display
- **Tables**: Custom HTML tables with Tailwind styling
- **Lists**: Standard `<ul>` and `<li>` with consistent spacing
- **Status Indicators**: Custom badges with color coding
- **Images**: Standard `<img>` with responsive classes

### Custom Components Needed
- **Dropdown Menus**: Build with Alpine.js/Livewire
- **Modals**: Custom implementation
- **Tables**: HTML tables with Tailwind styling
- **Badges**: Custom status indicators
- **Cards**: Reusable card components
- **File Upload**: Custom file input styling

### Form Validation
- **Livewire Validation**: Use component-level validation
- **Error Display**: Custom error styling below inputs
- **Success Messages**: Custom success message components
- **Required Fields**: Visual indicators with `required` attribute

### Loading States
- **Wire Loading**: Use `wire:loading` attributes
- **Skeleton Loaders**: Custom placeholder patterns
- **Button States**: Disabled states during submission

## Patterns to Follow

### File Structure
- Components in `resources/views/components/`
- Livewire views in `resources/views/livewire/`
- Partials in `resources/views/partials/`
- Layouts in `resources/views/components/layouts/`

### Naming Conventions
- **Routes**: Kebab-case (`products.index`)
- **Components**: Kebab-case (`product-form`)
- **Classes**: Camel-case (`ProductController`)
- **Variables**: Camel-case (`$productName`)

### Accessibility
- **Labels**: All form inputs must have labels
- **ARIA**: Proper ARIA attributes for interactive elements
- **Keyboard Navigation**: Tab order and focus management
- **Screen Reader**: Descriptive text for icons and actions

### Performance
- **Lazy Loading**: Use `wire:lazy` for heavy components
- **Navigation**: Use `wire:navigate` for SPA-like behavior
- **Caching**: Implement proper caching strategies
- **Image Optimization**: Responsive images with proper sizing