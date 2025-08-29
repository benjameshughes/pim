<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drop team-related tables as we've moved to simple role-based permissions
     */
    public function up(): void
    {
        // Drop pivot table first (foreign key constraints)
        if (Schema::hasTable('team_user')) {
            Schema::dropIfExists('team_user');
        }
        
        // Then drop main teams table
        if (Schema::hasTable('teams')) {
            Schema::dropIfExists('teams');
        }
        
        // Log the cleanup
        \Log::info('Team tables dropped - migrated to role-based permissions');
    }

    /**
     * Reverse the migrations.
     * 
     * Recreate basic team structure if rollback is needed
     */
    public function down(): void
    {
        // Recreate teams table
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Recreate pivot table
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // admin, manager, member
            $table->timestamps();
            
            $table->unique(['team_id', 'user_id']);
        });
        
        \Log::info('Team tables recreated for rollback');
    }
};
