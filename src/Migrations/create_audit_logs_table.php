<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for creating the audit_logs table.
 * 
 * This table stores comprehensive audit logs for all AMI actions
 * performed through the Asterisk PBX Manager package.
 * 
 * The table includes:
 * - Action identification and classification
 * - User context and authentication details
 * - Network and session information
 * - Request and response data (sanitized)
 * - Execution timing and success status
 * - Additional context and metadata
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Action identification
            $table->string('action_type', 50)->index(); // 'ami_action', 'connection', etc.
            $table->string('action_name', 100)->index(); // Specific action name (e.g., 'Originate', 'Hangup')
            
            // User context
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable()->index();
            
            // Network and session information
            $table->string('ip_address', 45)->nullable()->index(); // IPv6 compatible
            $table->text('user_agent')->nullable();
            $table->string('session_id', 255)->nullable()->index();
            
            // Execution details
            $table->timestamp('timestamp')->index(); // When the action was executed
            $table->boolean('success')->default(false)->index(); // Whether the action succeeded
            $table->decimal('execution_time', 10, 6)->default(0.0); // Execution time in seconds (microsecond precision)
            
            // Request and response data (JSON)
            $table->json('request_data')->nullable(); // Sanitized request data
            $table->json('response_data')->nullable(); // Sanitized response data
            
            // Additional context and metadata
            $table->json('additional_context')->nullable(); // Additional context data
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['action_type', 'action_name']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['success', 'timestamp']);
            $table->index(['timestamp', 'action_type']);
            
            // Foreign key constraints
            // Note: We don't enforce foreign key constraints for user_id to avoid
            // dependency on specific user table structure and to allow logging
            // even when user records are deleted
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};