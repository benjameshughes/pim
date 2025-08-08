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
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 32)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // File information
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->string('file_type', 20); // xlsx, csv
            $table->bigInteger('file_size');
            $table->string('file_hash', 64)->nullable();
            
            // Processing status
            $table->enum('status', [
                'initializing',
                'analyzing_file', 
                'awaiting_mapping',
                'mapped',
                'dry_run',
                'processing',
                'completed',
                'failed',
                'cancelled'
            ])->default('initializing');
            
            $table->string('current_stage', 100)->nullable();
            $table->string('current_operation', 255)->nullable();
            $table->integer('progress_percentage')->default(0);
            
            // Row tracking
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            
            // Performance metrics
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();
            $table->decimal('rows_per_second', 8, 2)->nullable();
            
            // Configuration and results
            $table->json('configuration')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('file_analysis')->nullable();
            $table->json('dry_run_results')->nullable();
            $table->json('final_results')->nullable();
            
            // Error tracking
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Job tracking
            $table->string('current_job_id', 100)->nullable();
            $table->json('job_chain_status')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
