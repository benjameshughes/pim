### Overall parent component

- handles data object and collecting data from each step to build a main object and step objects for product data, variants, images, and pricing
- Handles the saving of the object to the database with each step being it's own table and managing relationships
- handles the drafting system to save drafts
- handles step navigation
- handles shortcuts
- handles the saving of the object to the database

### Step 1 - Parent Product
- handles assigning of SKU and validating a valid sku of 000 style with the laravel custom rule in App\Rules
- handles validating of product name
- handle validating of product description
- handles the creation of the parent product object and passes it to the overall parent

### Step 2 - Variantions
- handles the generation of variant sku generation based off the parent sku so 000-001 sequentially based off amount of variants
- handles the colour attribute
- handles the width attribute
- handles the drop attributes
- handles the quick add options
- handles the creations of variants and passes this to the overall parent component

### Step 3 - Images
- handles the uploading of images
- handles the validation of images
- handles the creating of image data
- handles the creating of the image(s) data object to pass to the overall parent component

### Step 4 - Stock and Pricing
- handles the stock and pricing for the variants
- handles the displaying of each variant from the overall parent component to allow users to set individual stock and prices
- handles the creation of creating the stock and pricing object to pass to the overall parent component

### Features

### Auto-save / Draft system
- Saves the wizard data if not submitted in the cache for the user to retrieve or delete
- one draft per product per user
- decoupled as a service from the wizard, maybe a trait or interface
- works across all users

### Barcode assignment
- Auto assign the next available barcode when creating variants
- auto sign is done via a job I believe so maybe invoke the job when adding variants to the database

### Variants generation
- three options for generating variants, colour/width/drop
- they should be assigned sequentially and in order

### Images
- decoupled from the wizard and is part of the dam system
- images are stored as relationship in the database
- images should be assigned the parent product
- images can also be assigned to the variants with option to select variants for an image so variant with id's 1/2/3 have image id /3/4/5 as a relationship

### Pricing and stock
- Each variant should have the abilty to be assigned a price/stock and the ability to bulk update

### Keyboard Navigation (Alpine.js)
- Power user keyboard shortcuts for efficient wizard navigation
- Cross-platform support (Mac ⌘ and Windows/Linux Ctrl)
- Visual hints that auto-hide after 5 seconds
- Prevention of navigation conflicts when typing in form fields

#### Keyboard Shortcuts
- **⌘/Ctrl + →** - Navigate to next step (with validation)
- **⌘/Ctrl + ←** - Navigate to previous step
- **⌘/Ctrl + S** - Save product (when on final step)
- **Esc** - Clear draft data (with confirmation)
- **1-4 Keys** - Quick step indicators (shows which step user wants to navigate to)

#### Technical Implementation
- Alpine.js component with window-level keyboard event listening
- Mac vs Windows/Linux detection for appropriate modifier keys
- Form field detection to prevent navigation conflicts
- Toast notifications for keyboard action feedback
- Auto-hiding hint system for clean UI