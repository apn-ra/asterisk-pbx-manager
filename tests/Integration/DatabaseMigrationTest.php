<?php

namespace AsteriskPbxManager\Tests\Integration;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseMigrationTest extends IntegrationTestCase
{
    use RefreshDatabase;

    public function test_call_logs_migration_creates_table()
    {
        $this->assertTrue(Schema::hasTable('asterisk_call_logs'));
    }

    public function test_asterisk_events_migration_creates_table()
    {
        $this->assertTrue(Schema::hasTable('asterisk_events'));
    }

    public function test_call_logs_table_has_expected_columns()
    {
        $expectedColumns = [
            'id', 'channel', 'unique_id', 'linked_id', 'caller_id_num', 'caller_id_name',
            'connected_to', 'connected_name', 'context', 'extension', 'priority',
            'direction', 'call_type', 'started_at', 'answered_at', 'ended_at',
            'ring_duration', 'talk_duration', 'total_duration', 'call_status',
            'hangup_cause', 'hangup_reason', 'codec_used', 'quality_score', 'quality_metrics',
            'queue_name', 'queue_wait_time', 'agent_id', 'transfer_type', 'transferred_to',
            'transferred_by', 'recorded', 'recording_filename', 'recording_path', 'recording_size',
            'account_code', 'cost', 'cost_currency', 'cost_per_minute', 'metadata',
            'channel_variables', 'asterisk_server', 'processed_by', 'processed_at',
            'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('asterisk_call_logs', $column),
                "Column '{$column}' does not exist in asterisk_call_logs table"
            );
        }
    }

    public function test_asterisk_events_table_has_expected_columns()
    {
        $expectedColumns = [
            'id', 'event_name', 'event_type', 'sub_event', 'event_timestamp', 'received_at',
            'processed_at', 'channel', 'dest_channel', 'unique_id', 'dest_unique_id', 'linked_id',
            'caller_id_num', 'caller_id_name', 'connected_line_num', 'connected_line_name',
            'context', 'extension', 'dest_context', 'dest_extension', 'priority', 'application',
            'application_data', 'dial_string', 'dial_status', 'bridge_id', 'bridge_type',
            'bridge_technology', 'bridge_creator', 'bridge_name', 'bridge_num_channels',
            'queue', 'interface', 'member_name', 'state_interface', 'penalty', 'calls_taken',
            'last_call', 'last_pause', 'in_call', 'paused', 'pause_reason', 'status', 'reason',
            'ring_time', 'talk_time', 'hold_time', 'cause', 'cause_code', 'cause_txt',
            'channel_state', 'channel_state_desc', 'digit', 'direction', 'duration_ms',
            'filename', 'format', 'variable', 'value', 'event_data', 'parsed_data', 'metadata',
            'server_id', 'asterisk_version', 'server_ip', 'processing_status', 'is_significant',
            'needs_action', 'error_message', 'call_log_id', 'parent_event_id', 'correlation_id',
            'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('asterisk_events', $column),
                "Column '{$column}' does not exist in asterisk_events table"
            );
        }
    }

    public function test_call_logs_table_has_expected_indexes()
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes('asterisk_call_logs');

        $expectedIndexes = [
            'idx_asterisk_call_logs_channel',
            'idx_asterisk_call_logs_unique_id',
            'idx_asterisk_call_logs_linked_id',
            'idx_asterisk_call_logs_caller_id_num',
            'idx_asterisk_call_logs_connected_to',
            'idx_asterisk_call_logs_context',
            'idx_asterisk_call_logs_extension',
            'idx_asterisk_call_logs_direction',
            'idx_asterisk_call_logs_started_at',
            'idx_asterisk_call_logs_answered_at',
            'idx_asterisk_call_logs_ended_at',
            'idx_asterisk_call_logs_hangup_cause',
            'idx_asterisk_call_logs_queue_name',
            'idx_asterisk_call_logs_agent_id',
            'idx_asterisk_call_logs_recorded',
            'idx_asterisk_call_logs_account_code',
            'idx_asterisk_call_logs_asterisk_server',
        ];

        foreach ($expectedIndexes as $expectedIndex) {
            $this->assertTrue(
                isset($indexes[$expectedIndex]),
                "Index '{$expectedIndex}' does not exist on asterisk_call_logs table"
            );
        }
    }

    public function test_asterisk_events_table_has_expected_indexes()
    {
        $connection = Schema::getConnection();
        $indexes = $connection->getDoctrineSchemaManager()->listTableIndexes('asterisk_events');

        $expectedIndexes = [
            'idx_asterisk_events_event_name',
            'idx_asterisk_events_event_type',
            'idx_asterisk_events_event_timestamp',
            'idx_asterisk_events_received_at',
            'idx_asterisk_events_processed_at',
            'idx_asterisk_events_channel',
            'idx_asterisk_events_dest_channel',
            'idx_asterisk_events_unique_id',
            'idx_asterisk_events_dest_unique_id',
            'idx_asterisk_events_linked_id',
            'idx_asterisk_events_caller_id_num',
            'idx_asterisk_events_context',
            'idx_asterisk_events_extension',
            'idx_asterisk_events_application',
            'idx_asterisk_events_dial_status',
            'idx_asterisk_events_bridge_id',
            'idx_asterisk_events_queue',
            'idx_asterisk_events_interface',
            'idx_asterisk_events_paused',
            'idx_asterisk_events_status',
            'idx_asterisk_events_cause',
            'idx_asterisk_events_cause_code',
            'idx_asterisk_events_channel_state_desc',
            'idx_asterisk_events_variable',
            'idx_asterisk_events_server_id',
            'idx_asterisk_events_processing_status',
            'idx_asterisk_events_is_significant',
            'idx_asterisk_events_needs_action',
            'idx_asterisk_events_call_log_id',
            'idx_asterisk_events_parent_event_id',
            'idx_asterisk_events_correlation_id',
        ];

        foreach ($expectedIndexes as $expectedIndex) {
            $this->assertTrue(
                isset($indexes[$expectedIndex]),
                "Index '{$expectedIndex}' does not exist on asterisk_events table"
            );
        }
    }

    public function test_asterisk_events_has_foreign_key_to_call_logs()
    {
        $connection = Schema::getConnection();
        $foreignKeys = $connection->getDoctrineSchemaManager()->listTableForeignKeys('asterisk_events');

        $callLogForeignKeyExists = false;
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getName() === 'fk_asterisk_events_call_log_id') {
                $this->assertEquals(['call_log_id'], $foreignKey->getLocalColumns());
                $this->assertEquals('asterisk_call_logs', $foreignKey->getForeignTableName());
                $this->assertEquals(['id'], $foreignKey->getForeignColumns());
                $callLogForeignKeyExists = true;
                break;
            }
        }

        $this->assertTrue($callLogForeignKeyExists, 'Foreign key from asterisk_events.call_log_id to asterisk_call_logs.id does not exist');
    }

    public function test_asterisk_events_has_self_referential_foreign_key()
    {
        $connection = Schema::getConnection();
        $foreignKeys = $connection->getDoctrineSchemaManager()->listTableForeignKeys('asterisk_events');

        $selfReferencingForeignKeyExists = false;
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getName() === 'fk_asterisk_events_parent_event_id') {
                $this->assertEquals(['parent_event_id'], $foreignKey->getLocalColumns());
                $this->assertEquals('asterisk_events', $foreignKey->getForeignTableName());
                $this->assertEquals(['id'], $foreignKey->getForeignColumns());
                $selfReferencingForeignKeyExists = true;
                break;
            }
        }

        $this->assertTrue($selfReferencingForeignKeyExists, 'Self-referential foreign key for parent_event_id does not exist');
    }

    public function test_call_log_model_can_be_created()
    {
        $callLog = CallLog::create([
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'caller_id_num' => '1001',
            'caller_id_name' => 'John Doe',
            'connected_to' => '1002',
            'context' => 'internal',
            'extension' => '1002',
            'direction' => 'outbound',
            'call_type' => 'voice',
            'started_at' => now(),
            'call_status' => 'connected',
        ]);

        $this->assertInstanceOf(CallLog::class, $callLog);
        $this->assertEquals('SIP/1001-00000001', $callLog->channel);
        $this->assertEquals('1001', $callLog->caller_id_num);
        $this->assertEquals('outbound', $callLog->direction);
    }

    public function test_asterisk_event_model_can_be_created()
    {
        $event = AsteriskEvent::create([
            'event_name' => 'Dial',
            'event_type' => 'call',
            'event_timestamp' => now(),
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'caller_id_num' => '1001',
            'context' => 'internal',
            'extension' => '1002',
            'processing_status' => 'pending',
            'is_significant' => false,
            'needs_action' => false,
        ]);

        $this->assertInstanceOf(AsteriskEvent::class, $event);
        $this->assertEquals('Dial', $event->event_name);
        $this->assertEquals('SIP/1001-00000001', $event->channel);
        $this->assertEquals('pending', $event->processing_status);
    }

    public function test_call_log_has_many_events_relationship()
    {
        $callLog = CallLog::create([
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'caller_id_num' => '1001',
            'started_at' => now(),
            'call_status' => 'connected',
        ]);

        $event = AsteriskEvent::create([
            'event_name' => 'Dial',
            'event_timestamp' => now(),
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'call_log_id' => $callLog->id,
            'processing_status' => 'pending',
        ]);

        $this->assertTrue($callLog->events()->exists());
        $this->assertEquals(1, $callLog->events()->count());
        $this->assertEquals($event->id, $callLog->events()->first()->id);
    }

    public function test_asterisk_event_belongs_to_call_log_relationship()
    {
        $callLog = CallLog::create([
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'caller_id_num' => '1001',
            'started_at' => now(),
            'call_status' => 'connected',
        ]);

        $event = AsteriskEvent::create([
            'event_name' => 'Dial',
            'event_timestamp' => now(),
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'call_log_id' => $callLog->id,
            'processing_status' => 'pending',
        ]);

        $this->assertNotNull($event->callLog);
        $this->assertEquals($callLog->id, $event->callLog->id);
        $this->assertEquals($callLog->channel, $event->callLog->channel);
    }

    public function test_asterisk_event_self_referential_relationship()
    {
        $parentEvent = AsteriskEvent::create([
            'event_name' => 'DialBegin',
            'event_timestamp' => now(),
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'processing_status' => 'processed',
        ]);

        $childEvent = AsteriskEvent::create([
            'event_name' => 'DialEnd',
            'event_timestamp' => now()->addSeconds(10),
            'channel' => 'SIP/1001-00000001',
            'unique_id' => '1234567890.1',
            'parent_event_id' => $parentEvent->id,
            'processing_status' => 'pending',
        ]);

        // Test parent -> child relationship
        $this->assertTrue($parentEvent->childEvents()->exists());
        $this->assertEquals(1, $parentEvent->childEvents()->count());
        $this->assertEquals($childEvent->id, $parentEvent->childEvents()->first()->id);

        // Test child -> parent relationship
        $this->assertNotNull($childEvent->parentEvent);
        $this->assertEquals($parentEvent->id, $childEvent->parentEvent->id);
    }

    public function test_call_log_model_scopes_work()
    {
        // Create test data
        CallLog::create([
            'channel' => 'SIP/1001-00000001',
            'caller_id_num' => '1001',
            'direction' => 'inbound',
            'call_status' => 'connected',
            'started_at' => now()->subHours(2),
            'total_duration' => 120,
        ]);

        CallLog::create([
            'channel' => 'SIP/1002-00000002',
            'caller_id_num' => '1002',
            'direction' => 'outbound',
            'call_status' => 'missed',
            'started_at' => now()->subHour(),
            'total_duration' => 0,
        ]);

        // Test scopes
        $this->assertEquals(1, CallLog::byDirection('inbound')->count());
        $this->assertEquals(1, CallLog::byDirection('outbound')->count());
        $this->assertEquals(1, CallLog::byStatus('connected')->count());
        $this->assertEquals(1, CallLog::byStatus('missed')->count());
        $this->assertEquals(1, CallLog::answered()->count());
        $this->assertEquals(1, CallLog::unanswered()->count());
        $this->assertEquals(1, CallLog::longerThan(60)->count());
        $this->assertEquals(1, CallLog::shorterThan(60)->count());
    }

    public function test_asterisk_event_model_scopes_work()
    {
        // Create test data
        AsteriskEvent::create([
            'event_name' => 'Dial',
            'event_type' => 'call',
            'event_timestamp' => now()->subHours(2),
            'channel' => 'SIP/1001-00000001',
            'processing_status' => 'processed',
            'is_significant' => true,
            'needs_action' => false,
        ]);

        AsteriskEvent::create([
            'event_name' => 'QueueMemberAdded',
            'event_type' => 'queue',
            'event_timestamp' => now()->subHour(),
            'queue' => 'support',
            'processing_status' => 'pending',
            'is_significant' => false,
            'needs_action' => true,
        ]);

        // Test scopes
        $this->assertEquals(1, AsteriskEvent::byEventName('Dial')->count());
        $this->assertEquals(1, AsteriskEvent::byEventName('QueueMemberAdded')->count());
        $this->assertEquals(1, AsteriskEvent::byEventType('call')->count());
        $this->assertEquals(1, AsteriskEvent::byEventType('queue')->count());
        $this->assertEquals(1, AsteriskEvent::processed()->count());
        $this->assertEquals(1, AsteriskEvent::pending()->count());
        $this->assertEquals(1, AsteriskEvent::significant()->count());
        $this->assertEquals(1, AsteriskEvent::needsAction()->count());
    }

    public function test_models_can_handle_json_fields()
    {
        $metadata = [
            'custom_field' => 'custom_value',
            'quality_metrics' => ['jitter' => 0.5, 'latency' => 120],
        ];

        $callLog = CallLog::create([
            'channel' => 'SIP/1001-00000001',
            'caller_id_num' => '1001',
            'started_at' => now(),
            'call_status' => 'connected',
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $callLog->metadata);
        $this->assertEquals('custom_value', $callLog->metadata['custom_field']);
        $this->assertEquals(0.5, $callLog->metadata['quality_metrics']['jitter']);

        $eventData = [
            'raw_event' => ['Event' => 'Dial', 'Channel' => 'SIP/1001'],
            'parsed_fields' => ['direction' => 'outbound'],
        ];

        $event = AsteriskEvent::create([
            'event_name' => 'Dial',
            'event_timestamp' => now(),
            'channel' => 'SIP/1001-00000001',
            'processing_status' => 'pending',
            'event_data' => $eventData,
        ]);

        $this->assertEquals($eventData, $event->event_data);
        $this->assertEquals('Dial', $event->event_data['raw_event']['Event']);
        $this->assertEquals('outbound', $event->event_data['parsed_fields']['direction']);
    }
}