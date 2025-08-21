<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 🎨✨ ADD DAM METADATA TO IMAGES TABLE ✨🎨
     * 
     * Transform the images table into a proper Digital Asset Management system
     * with metadata, tagging, and organization capabilities
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Core DAM metadata
            $table->string('title')->nullable()->after('filename'); // User-friendly title
            $table->string('alt_text')->nullable()->after('title'); // Accessibility text
            $table->text('description')->nullable()->after('alt_text'); // Longer description
            
            // Organization and categorization
            $table->string('folder')->default('uncategorized')->after('description'); // Folder organization
            $table->json('tags')->nullable()->after('folder'); // Tags for categorization
            
            // User tracking
            $table->foreignId('created_by_user_id')->nullable()->after('tags')->constrained('users')->onDelete('set null');
            
            // Add indexes for performance
            $table->index('folder'); // Fast folder filtering
            $table->index('created_by_user_id'); // Fast user filtering
            $table->index(['imageable_type', 'imageable_id', 'folder']); // Complex queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['images_folder_index']);
            $table->dropIndex(['images_created_by_user_id_index']);
            $table->dropIndex(['images_imageable_type_imageable_id_folder_index']);
            
            // Drop foreign key constraint
            $table->dropForeign(['created_by_user_id']);
            
            // Drop columns
            $table->dropColumn([
                'title',
                'alt_text', 
                'description',
                'folder',
                'tags',
                'created_by_user_id',
            ]);
        });
    }
};
