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
        Schema::create('file_processing_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('processing_type'); // file_analysis, sample_data, dry_run_data, full_import_data
            $table->string('status')->default('pending'); // pending, analyzing, processing, completed, failed, cancelled
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->integer('current_step')->nullable();
            $table->integer('total_steps')->nullable();
            $table->text('message')->nullable();
            $table->json('result_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_processing_progress');
    }
};
