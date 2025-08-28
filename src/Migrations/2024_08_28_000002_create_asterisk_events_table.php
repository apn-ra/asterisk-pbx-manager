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
        Schema::create('asterisk_events', function (Blueprint $table) {
            $table->id();

            // Core event identification
            $table->string('event_name', 50)->index('idx_asterisk_events_event_name');
            $table->string('event_type', 30)->nullable()->index('idx_asterisk_events_event_type');
            $table->string('sub_event', 50)->nullable()->comment('Sub-event type like DialBegin, DialEnd, etc.');

            // Event source and timing
            $table->timestamp('event_timestamp')->index('idx_asterisk_events_event_timestamp');
            $table->timestamp('received_at')->nullable()->index('idx_asterisk_events_received_at');
            $table->timestamp('processed_at')->nullable()->index('idx_asterisk_events_processed_at');

            // Channel and call identification
            $table->string('channel')->nullable()->index('idx_asterisk_events_channel');
            $table->string('dest_channel')->nullable()->index('idx_asterisk_events_dest_channel');
            $table->string('unique_id')->nullable()->index('idx_asterisk_events_unique_id');
            $table->string('dest_unique_id')->nullable()->index('idx_asterisk_events_dest_unique_id');
            $table->string('linked_id')->nullable()->index('idx_asterisk_events_linked_id');

            // Call participant information
            $table->string('caller_id_num')->nullable()->index('idx_asterisk_events_caller_id_num');
            $table->string('caller_id_name')->nullable();
            $table->string('connected_line_num')->nullable();
            $table->string('connected_line_name')->nullable();

            // Call routing and context
            $table->string('context', 80)->nullable()->index('idx_asterisk_events_context');
            $table->string('extension', 80)->nullable()->index('idx_asterisk_events_extension');
            $table->string('dest_context', 80)->nullable();
            $table->string('dest_extension', 80)->nullable();
            $table->integer('priority')->nullable();

            // Application and dial information
            $table->string('application', 50)->nullable()->index('idx_asterisk_events_application');
            $table->string('application_data')->nullable();
            $table->string('dial_string')->nullable();
            $table->string('dial_status', 20)->nullable()->index('idx_asterisk_events_dial_status');

            // Bridge and conference information
            $table->string('bridge_id')->nullable()->index('idx_asterisk_events_bridge_id');
            $table->string('bridge_type', 20)->nullable();
            $table->string('bridge_technology', 30)->nullable();
            $table->string('bridge_creator', 50)->nullable();
            $table->string('bridge_name')->nullable();
            $table->integer('bridge_num_channels')->nullable();

            // Queue-specific information
            $table->string('queue', 80)->nullable()->index('idx_asterisk_events_queue');
            $table->string('interface', 100)->nullable()->index('idx_asterisk_events_interface');
            $table->string('member_name', 100)->nullable();
            $table->string('state_interface', 100)->nullable();
            $table->integer('penalty')->nullable();
            $table->integer('calls_taken')->nullable();
            $table->integer('last_call')->nullable();
            $table->integer('last_pause')->nullable();
            $table->boolean('in_call')->nullable();
            $table->boolean('paused')->nullable()->index('idx_asterisk_events_paused');
            $table->string('pause_reason')->nullable();

            // Agent and member status
            $table->string('status', 20)->nullable()->index('idx_asterisk_events_status');
            $table->string('reason', 50)->nullable();
            $table->integer('ring_time')->nullable();
            $table->integer('talk_time')->nullable();
            $table->integer('hold_time')->nullable();

            // Hangup and cause information
            $table->string('cause', 50)->nullable()->index('idx_asterisk_events_cause');
            $table->integer('cause_code')->nullable()->index('idx_asterisk_events_cause_code');
            $table->string('cause_txt')->nullable();

            // Channel state information
            $table->integer('channel_state')->nullable();
            $table->string('channel_state_desc', 50)->nullable()->index('idx_asterisk_events_channel_state_desc');

            // DTMF and interaction information
            $table->string('digit', 5)->nullable();
            $table->string('direction', 10)->nullable();
            $table->integer('duration_ms')->nullable();

            // Recording and monitoring
            $table->string('filename')->nullable();
            $table->string('format', 10)->nullable();

            // Variable and custom data
            $table->string('variable')->nullable()->index('idx_asterisk_events_variable');
            $table->text('value')->nullable();

            // Raw event data and metadata
            $table->json('event_data')->nullable()->comment('Complete raw event data from Asterisk');
            $table->json('parsed_data')->nullable()->comment('Processed and structured event data');
            $table->json('metadata')->nullable()->comment('Additional metadata and processing information');

            // System and server information
            $table->string('server_id', 50)->nullable()->index('idx_asterisk_events_server_id');
            $table->string('asterisk_version', 20)->nullable();
            $table->ipAddress('server_ip')->nullable();

            // Processing status and flags
            $table->enum('processing_status', ['pending', 'processed', 'failed', 'ignored'])->default('pending')->index('idx_asterisk_events_processing_status');
            $table->boolean('is_significant')->default(false)->index('idx_asterisk_events_is_significant');
            $table->boolean('needs_action')->default(false)->index('idx_asterisk_events_needs_action');
            $table->string('error_message')->nullable();

            // Correlation and relationships
            $table->unsignedBigInteger('call_log_id')->nullable()->index('idx_asterisk_events_call_log_id');
            $table->unsignedBigInteger('parent_event_id')->nullable()->index('idx_asterisk_events_parent_event_id');
            $table->string('correlation_id')->nullable()->index('idx_asterisk_events_correlation_id');

            // Standard Laravel timestamps
            $table->timestamps();

            // Composite indexes for common query patterns
            $table->index(['event_name', 'event_timestamp'], 'idx_asterisk_events_name_timestamp');
            $table->index(['channel', 'event_timestamp'], 'idx_asterisk_events_channel_timestamp');
            $table->index(['unique_id', 'event_timestamp'], 'idx_asterisk_events_unique_timestamp');
            $table->index(['queue', 'event_timestamp'], 'idx_asterisk_events_queue_timestamp');
            $table->index(['caller_id_num', 'event_timestamp'], 'idx_asterisk_events_caller_timestamp');
            $table->index(['bridge_id', 'event_timestamp'], 'idx_asterisk_events_bridge_timestamp');
            $table->index(['processing_status', 'event_timestamp'], 'idx_asterisk_events_status_timestamp');
            $table->index(['is_significant', 'event_timestamp'], 'idx_asterisk_events_significant_timestamp');

            // Foreign key constraints
            $table->foreign('call_log_id')
                  ->references('id')
                  ->on('asterisk_call_logs')
                  ->onDelete('set null')
                  ->name('fk_asterisk_events_call_log_id');

            $table->foreign('parent_event_id')
                  ->references('id')
                  ->on('asterisk_events')
                  ->onDelete('set null')
                  ->name('fk_asterisk_events_parent_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asterisk_events');
    }
};
