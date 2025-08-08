<?php

namespace App\Console\Commands;

use App\Exceptions\MediaLibraryException;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class MigrateImagesToMediaLibrary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:migrate-to-media-library {--dry-run : Show what would be migrated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing JSON-stored images to Spatie Media Library';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        } else {
            $this->info('🚀 Starting migration of images to Media Library...');
        }

        $this->migrateProductImages($isDryRun);
        $this->migrateVariantImages($isDryRun);

        $this->info('✅ Migration completed!');
    }

    private function migrateProductImages(bool $isDryRun)
    {
        $this->info('📦 Processing Product images...');

        $products = Product::whereNotNull('images')->get();

        if ($products->isEmpty()) {
            $this->line('   No products with images found.');

            return;
        }

        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products as $product) {
            $images = $product->images ?? [];

            foreach ($images as $index => $imageData) {
                $this->migrateImage($product, $imageData, $index, $isDryRun, 'Product');
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    private function migrateVariantImages(bool $isDryRun)
    {
        $this->info('🎨 Processing ProductVariant images...');

        $variants = ProductVariant::whereNotNull('images')->get();

        if ($variants->isEmpty()) {
            $this->line('   No variants with images found.');

            return;
        }

        $progressBar = $this->output->createProgressBar($variants->count());
        $progressBar->start();

        foreach ($variants as $variant) {
            $images = $variant->images ?? [];

            // Handle both array and string formats
            if (is_string($images)) {
                $this->line("   ⚠️  Variant {$variant->id}: Images stored as string, skipping");

                continue;
            }

            foreach ($images as $index => $imageData) {
                // Ensure imageData is an array
                if (is_string($imageData)) {
                    $this->line("   ⚠️  Variant {$variant->id}: Image data is string: {$imageData}");

                    continue;
                }

                $this->migrateImage($variant, $imageData, $index, $isDryRun, 'Variant');
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    private function migrateImage($model, array $imageData, int $index, bool $isDryRun, string $modelType)
    {
        // Check if image file exists
        $path = $imageData['path'] ?? null;
        $url = $imageData['url'] ?? null;
        $originalUrl = $imageData['imported_from'] ?? null;

        if (! $path) {
            throw MediaLibraryException::fileNotFound("Missing path for {$modelType} {$model->id} image {$index}");
        }

        $fullPath = storage_path('app/public/'.$path);

        if (! file_exists($fullPath)) {
            throw MediaLibraryException::fileNotFound($path);
        }

        if ($isDryRun) {
            $this->line("   📋 Would migrate: {$path} for {$modelType} {$model->id}");

            return;
        }

        // Add to media library
        $mediaItem = $model->addMedia($fullPath)
            ->usingName($imageData['original_name'] ?? "Image {$index}")
            ->usingFileName(basename($path))
            ->withCustomProperties([
                'source' => $imageData['source'] ?? 'migration',
                'imported_from' => $originalUrl,
                'imported_at' => $imageData['imported_at'] ?? now()->toISOString(),
                'original_size' => $imageData['size'] ?? filesize($fullPath),
                'original_path' => $path,
            ])
            ->toMediaCollection('images') ?: throw MediaLibraryException::migrationFailed($path, 'Failed to add to media collection');

        $this->line("   ✅ Migrated: {$path} → Media ID: {$mediaItem->id}");
    }
}
