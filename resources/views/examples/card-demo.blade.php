{{-- 🎨 CARD COMPONENT DEMO - Shadcn Inspired --}}
<div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Flux Card Components - Shadcn Inspired</h1>

    {{-- Basic Card --}}
    <flux:card class="w-96">
        <flux:card.header>
            <flux:card.title>Basic Card</flux:card.title>
            <flux:card.description>This is a simple card example with header and content.</flux:card.description>
        </flux:card.header>
        <flux:card.content>
            <p>This is the main content area of the card. You can put anything here.</p>
        </flux:card.content>
    </flux:card>

    {{-- Card with Footer --}}
    <flux:card class="w-96">
        <flux:card.header>
            <flux:card.title>Card with Footer</flux:card.title>
            <flux:card.description>This card includes a footer with actions.</flux:card.description>
        </flux:card.header>
        <flux:card.content>
            <p>Some content here that describes what this card is about.</p>
        </flux:card.content>
        <flux:card.footer>
            <flux:button variant="outline">Cancel</flux:button>
            <flux:button variant="primary">Save Changes</flux:button>
        </flux:card.footer>
    </flux:card>

    {{-- Import-Style Card --}}
    <flux:card class="w-96">
        <flux:card.header>
            <flux:card.title>Import Products</flux:card.title>
            <flux:card.description>Upload and process your product CSV file</flux:card.description>
        </flux:card.header>
        <flux:card.content class="space-y-4">
            <div>
                <flux:input type="file" accept=".csv" />
            </div>
            <div class="text-sm text-gray-500">
                <strong>Supported formats:</strong> CSV<br>
                <strong>Max size:</strong> 10MB
            </div>
        </flux:card.content>
        <flux:card.footer>
            <flux:button variant="primary">Upload File</flux:button>
        </flux:card.footer>
    </flux:card>
</div>