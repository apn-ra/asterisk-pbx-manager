<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asterisk_call_logs', function (Blueprint $table) {
            $table->id();

            // Core call identification
            $table->string('channel')->index('idx_asterisk_call_logs_channel');
            $table->string('unique_id')->nullable()->index('idx_asterisk_call_logs_unique_id');
            $table->string('linked_id')->nullable()->index('idx_asterisk_call_logs_linked_id');

            // Call participants
            $table->string('caller_id_num')->nullable()->index('idx_asterisk_call_logs_caller_id_num');
            $table->string('caller_id_name')->nullable();
            $table->string('connected_to')->nullable()->index('idx_asterisk_call_logs_connected_to');
            $table->string('connected_name')->nullable();

            // Call routing information
            $table->string('context', 80)->default('default')->index('idx_asterisk_call_logs_context');
            $table->string('extension', 80)->nullable()->index('idx_asterisk_call_logs_extension');
            $table->integer('priority')->nullable();

            // Call direction and type
            $table->enum('direction', ['inbound', 'outbound', 'internal', 'unknown'])->default('unknown')->index('idx_asterisk_call_logs_direction');
            $table->enum('call_type', ['voice', 'video', 'conference', 'transfer', 'queue', 'other'])->default('voice');

            // Call timing
            $table->timestamp('started_at')->nullable()->index('idx_asterisk_call_logs_started_at');
            $table->timestamp('answered_at')->nullable()->index('idx_asterisk_call_logs_answered_at');
            $table->timestamp('ended_at')->nullable()->index('idx_asterisk_call_logs_ended_at');
            $table->integer('ring_duration')->nullable()->comment('Ring time in seconds');
            $table->integer('talk_duration')->nullable()->comment('Talk time in seconds');
            $table->integer('total_duration')->nullable()->comment('Total call duration in seconds');

            // Call outcome
            $table->enum('call_status', ['connected', 'missed', 'busy', 'no_answer', 'failed', 'abandoned'])->default('connected');
            $table->string('hangup_cause', 50)->nullable()->index('idx_asterisk_call_logs_hangup_cause');
            $table->string('hangup_reason')->nullable();

            // Quality and technical details
            $table->string('codec_used', 20)->nullable();
            $table->decimal('quality_score', 3, 2)->nullable()->comment('Call quality score 0.00-5.00');
            $table->json('quality_metrics')->nullable()->comment('Detailed quality metrics');

            // Queue-specific information
            $table->string('queue_name', 80)->nullable()->index('idx_asterisk_call_logs_queue_name');
            $table->integer('queue_wait_time')->nullable()->comment('Queue wait time in seconds');
            $table->string('agent_id', 80)->nullable()->index('idx_asterisk_call_logs_agent_id');

            // Transfer information
            $table->string('transfer_type', 20)->nullable()->comment('blind, attended, etc.');
            $table->string('transferred_to', 80)->nullable();
            $table->string('transferred_by', 80)->nullable();

            // Recording information
            $table->boolean('recorded')->default(false)->index('idx_asterisk_call_logs_recorded');
            $table->string('recording_filename')->nullable();
            $table->string('recording_path')->nullable();
            $table->integer('recording_size')->nullable()->comment('File size in bytes');

            // Billing and cost tracking
            $table->string('account_code', 80)->nullable()->index('idx_asterisk_call_logs_account_code');
            $table->decimal('cost', 10, 4)->nullable()->comment('Call cost in system currency');
            $table->string('cost_currency', 3)->nullable()->default('USD');
            $table->decimal('cost_per_minute', 8, 4)->nullable();

            // Additional metadata and custom fields
            $table->json('metadata')->nullable()->comment('Additional call metadata and custom fields');
            $table->json('channel_variables')->nullable()->comment('Channel variables at call time');

            // System tracking
            $table->string('asterisk_server', 100)->nullable()->index('idx_asterisk_call_logs_asterisk_server');
            $table->string('processed_by', 100)->nullable()->comment('Processing system/service');
            $table->timestamp('processed_at')->nullable();

            // Standard Laravel timestamps
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['caller_id_num', 'started_at'], 'idx_asterisk_call_logs_caller_time');
            $table->index(['connected_to', 'started_at'], 'idx_asterisk_call_logs_connected_time');
            $table->index(['context', 'extension', 'started_at'], 'idx_asterisk_call_logs_routing_time');
            $table->index(['direction', 'call_status', 'started_at'], 'idx_asterisk_call_logs_direction_status_time');
            $table->index(['queue_name', 'started_at'], 'idx_asterisk_call_logs_queue_time');
            $table->index(['hangup_cause', 'started_at'], 'idx_asterisk_call_logs_hangup_time');

            // Unique constraint to prevent duplicate log entries
            $table->unique(['channel', 'unique_id', 'started_at'], 'uk_asterisk_call_logs_channel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asterisk_call_logs');
    }
};
