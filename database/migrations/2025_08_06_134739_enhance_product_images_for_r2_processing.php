<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('image_path');
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->after('image_type');
            $table->string('storage_disk')->default('public')->after('processing_status');
            $table->bigInteger('file_size')->unsigned()->nullable()->after('storage_disk');
            $table->string('mime_type')->nullable()->after('file_size');
            $table->json('dimensions')->nullable()->after('mime_type');
            
            // Add indexes for processing status and storage disk
            $table->index('processing_status');
            $table->index(['storage_disk', 'processing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['storage_disk', 'processing_status']);
            $table->dropIndex(['processing_status']);
            $table->dropColumn([
                'original_filename',
                'processing_status', 
                'storage_disk',
                'file_size',
                'mime_type',
                'dimensions'
            ]);
        });
    }
};
