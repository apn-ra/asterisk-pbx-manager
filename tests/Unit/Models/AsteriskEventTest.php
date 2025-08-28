<?php

namespace AsteriskPbxManager\Tests\Unit\Models;

use AsteriskPbxManager\Models\AsteriskEvent;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AsteriskEventTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up the database schema for testing
        $this->loadLaravelMigrations();
    }

    public function testAsteriskEventCreation()
    {
        $event = new AsteriskEvent([
            'event_name'     => 'Dial',
            'event_type'     => 'call',
            'channel'        => 'SIP/1001-12345',
            'unique_id'      => 'unique123',
            'caller_id_num'  => '1001',
            'event_data'     => ['SubEvent' => 'Begin'],
            'received_at'    => now(),
            'is_significant' => true,
        ]);

        $this->assertInstanceOf(AsteriskEvent::class, $event);
        $this->assertEquals('Dial', $event->event_name);
        $this->assertEquals('call', $event->event_type);
        $this->assertEquals('SIP/1001-12345', $event->channel);
    }

    public function testCallLogRelationship()
    {
        $event = new AsteriskEvent();
        $relationship = $event->callLog();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relationship);
        $this->assertEquals('call_log_id', $relationship->getForeignKeyName());
        $this->assertEquals(CallLog::class, $relationship->getRelated()::class);
    }

    public function testParentEventRelationship()
    {
        $event = new AsteriskEvent();
        $relationship = $event->parentEvent();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relationship);
        $this->assertEquals('parent_event_id', $relationship->getForeignKeyName());
        $this->assertEquals(AsteriskEvent::class, $relationship->getRelated()::class);
    }

    public function testChildEventsRelationship()
    {
        $event = new AsteriskEvent();
        $relationship = $event->childEvents();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relationship);
        $this->assertEquals('parent_event_id', $relationship->getForeignKeyName());
        $this->assertEquals(AsteriskEvent::class, $relationship->getRelated()::class);
    }

    public function testRelatedEventsRelationship()
    {
        $event = new AsteriskEvent();
        $relationship = $event->relatedEvents();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relationship);
    }

    public function testDateRangeScope()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $query = AsteriskEvent::dateRange($startDate, $endDate);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByEventNameScope()
    {
        $query = AsteriskEvent::byEventName('Dial');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByEventTypeScope()
    {
        $query = AsteriskEvent::byEventType('call');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByChannelScope()
    {
        $query = AsteriskEvent::byChannel('SIP/1001-12345');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByUniqueIdScope()
    {
        $query = AsteriskEvent::byUniqueId('unique123');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testSignificantScope()
    {
        $query = AsteriskEvent::significant();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testNeedsActionScope()
    {
        $query = AsteriskEvent::needsAction();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testProcessedScope()
    {
        $query = AsteriskEvent::processed();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testPendingScope()
    {
        $query = AsteriskEvent::pending();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testFailedScope()
    {
        $query = AsteriskEvent::failed();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testCallEventsScope()
    {
        $query = AsteriskEvent::callEvents();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testQueueEventsScope()
    {
        $query = AsteriskEvent::queueEvents();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testBridgeEventsScope()
    {
        $query = AsteriskEvent::bridgeEvents();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByQueueScope()
    {
        $query = AsteriskEvent::byQueue('support');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByCallerIdScope()
    {
        $query = AsteriskEvent::byCallerId('1001');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByApplicationScope()
    {
        $query = AsteriskEvent::byApplication('Dial');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByBridgeIdScope()
    {
        $query = AsteriskEvent::byBridgeId('bridge123');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testWithErrorsScope()
    {
        $query = AsteriskEvent::withErrors();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByServerScope()
    {
        $query = AsteriskEvent::byServer('server01');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testGetEventCategoryAttribute()
    {
        $dialEvent = new AsteriskEvent(['event_name' => 'Dial']);
        $hangupEvent = new AsteriskEvent(['event_name' => 'Hangup']);
        $queueEvent = new AsteriskEvent(['event_name' => 'QueueMemberAdded']);
        $bridgeEvent = new AsteriskEvent(['event_name' => 'Bridge']);
        $unknownEvent = new AsteriskEvent(['event_name' => 'CustomEvent']);

        $this->assertEquals('call', $dialEvent->event_category);
        $this->assertEquals('call', $hangupEvent->event_category);
        $this->assertEquals('queue', $queueEvent->event_category);
        $this->assertEquals('bridge', $bridgeEvent->event_category);
        $this->assertEquals('other', $unknownEvent->event_category);
    }

    public function testGetDurationFormattedAttribute()
    {
        $event = new AsteriskEvent(['duration' => 125]);

        $this->assertEquals('02:05', $event->duration_formatted);
    }

    public function testGetDurationFormattedAttributeWithNull()
    {
        $event = new AsteriskEvent(['duration' => null]);

        $this->assertNull($event->duration_formatted);
    }

    public function testGetIsCallEventAttribute()
    {
        $callEvent = new AsteriskEvent(['event_name' => 'Dial']);
        $queueEvent = new AsteriskEvent(['event_name' => 'QueueMemberAdded']);

        $this->assertTrue($callEvent->is_call_event);
        $this->assertFalse($queueEvent->is_call_event);
    }

    public function testGetIsQueueEventAttribute()
    {
        $queueEvent = new AsteriskEvent(['event_name' => 'QueueMemberAdded']);
        $callEvent = new AsteriskEvent(['event_name' => 'Dial']);

        $this->assertTrue($queueEvent->is_queue_event);
        $this->assertFalse($callEvent->is_queue_event);
    }

    public function testGetIsBridgeEventAttribute()
    {
        $bridgeEvent = new AsteriskEvent(['event_name' => 'Bridge']);
        $callEvent = new AsteriskEvent(['event_name' => 'Dial']);

        $this->assertTrue($bridgeEvent->is_bridge_event);
        $this->assertFalse($callEvent->is_bridge_event);
    }

    public function testSetCallerIdNumAttribute()
    {
        $event = new AsteriskEvent();
        $event->caller_id_num = '+1-555-123-4567';

        $this->assertEquals('15551234567', $event->caller_id_num);
    }

    public function testSetCallerIdNumAttributeWithNull()
    {
        $event = new AsteriskEvent();
        $event->caller_id_num = null;

        $this->assertNull($event->caller_id_num);
    }

    public function testSetConnectedLineNumAttribute()
    {
        $event = new AsteriskEvent();
        $event->connected_line_num = '+1 (555) 987-6543';

        $this->assertEquals('15559876543', $event->connected_line_num);
    }

    public function testMarkAsProcessed()
    {
        $event = new AsteriskEvent([
            'event_name'        => 'Dial',
            'processing_status' => 'pending',
        ]);

        $result = $event->markAsProcessed();

        $this->assertTrue($result);
        $this->assertEquals('processed', $event->processing_status);
        $this->assertNotNull($event->processed_at);
    }

    public function testMarkAsFailed()
    {
        $event = new AsteriskEvent([
            'event_name'        => 'Dial',
            'processing_status' => 'pending',
        ]);

        $result = $event->markAsFailed('Processing failed');

        $this->assertTrue($result);
        $this->assertEquals('failed', $event->processing_status);
        $this->assertEquals('Processing failed', $event->error_message);
        $this->assertNotNull($event->failed_at);
    }

    public function testMarkAsSignificant()
    {
        $event = new AsteriskEvent([
            'event_name'     => 'Dial',
            'is_significant' => false,
        ]);

        $result = $event->markAsSignificant();

        $this->assertTrue($result);
        $this->assertTrue($event->is_significant);
    }

    public function testMarkAsNeedsAction()
    {
        $event = new AsteriskEvent([
            'event_name'   => 'Dial',
            'needs_action' => false,
        ]);

        $result = $event->markAsNeedsAction();

        $this->assertTrue($result);
        $this->assertTrue($event->needs_action);
    }

    public function testGetStatisticsReturnsArray()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $stats = AsteriskEvent::getStatistics($startDate, $endDate);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_events', $stats);
        $this->assertArrayHasKey('processed_events', $stats);
        $this->assertArrayHasKey('failed_events', $stats);
        $this->assertArrayHasKey('significant_events', $stats);
        $this->assertArrayHasKey('events_by_type', $stats);
    }

    public function testGetHourlyVolumeReturnsArray()
    {
        $date = Carbon::now();

        $volume = AsteriskEvent::getHourlyVolume($date);

        $this->assertIsArray($volume);
        $this->assertCount(24, $volume); // Should have 24 hours
    }

    public function testFindByCorrelationIdReturnsCollection()
    {
        $events = AsteriskEvent::findByCorrelationId('correlation123');

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $events);
    }

    public function testGenerateCorrelationId()
    {
        $correlationId = AsteriskEvent::generateCorrelationId();

        $this->assertIsString($correlationId);
        $this->assertGreaterThan(0, strlen($correlationId));
    }

    public function testNormalizePhoneNumber()
    {
        $event = new AsteriskEvent();

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('normalizePhoneNumber');
        $method->setAccessible(true);

        $this->assertEquals('15551234567', $method->invoke($event, '+1-555-123-4567'));
        $this->assertEquals('15551234567', $method->invoke($event, '(555) 123-4567'));
        $this->assertEquals('5551234567', $method->invoke($event, '555.123.4567'));
        $this->assertNull($method->invoke($event, null));
        $this->assertNull($method->invoke($event, ''));
    }

    public function testHasFactoryTrait()
    {
        $this->assertTrue(method_exists(AsteriskEvent::class, 'factory'));
    }

    public function testFillableAttributes()
    {
        $event = new AsteriskEvent();
        $fillable = $event->getFillable();

        $this->assertContains('event_name', $fillable);
        $this->assertContains('event_type', $fillable);
        $this->assertContains('channel', $fillable);
        $this->assertContains('unique_id', $fillable);
        $this->assertContains('event_data', $fillable);
    }

    public function testCastsAttributes()
    {
        $event = new AsteriskEvent();
        $casts = $event->getCasts();

        $this->assertArrayHasKey('event_data', $casts);
        $this->assertArrayHasKey('received_at', $casts);
        $this->assertArrayHasKey('processed_at', $casts);
        $this->assertArrayHasKey('failed_at', $casts);
    }

    public function testTableName()
    {
        $event = new AsteriskEvent();

        $this->assertEquals('asterisk_events', $event->getTable());
    }

    public function testEventDataJsonCasting()
    {
        $eventData = ['Channel' => 'SIP/1001', 'SubEvent' => 'Begin'];
        $event = new AsteriskEvent(['event_data' => $eventData]);

        $this->assertIsArray($event->event_data);
        $this->assertEquals('SIP/1001', $event->event_data['Channel']);
        $this->assertEquals('Begin', $event->event_data['SubEvent']);
    }

    public function testBooleanAttributes()
    {
        $event = new AsteriskEvent([
            'is_significant' => true,
            'needs_action'   => false,
            'has_error'      => true,
        ]);

        $this->assertTrue($event->is_significant);
        $this->assertFalse($event->needs_action);
        $this->assertTrue($event->has_error);
    }

    public function testTimestampAttributes()
    {
        $now = Carbon::now();
        $event = new AsteriskEvent([
            'received_at'  => $now,
            'processed_at' => $now->addMinutes(1),
            'failed_at'    => null,
        ]);

        $this->assertInstanceOf(Carbon::class, $event->received_at);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
        $this->assertNull($event->failed_at);
    }

    public function testProcessingStatusValues()
    {
        $pendingEvent = new AsteriskEvent(['processing_status' => 'pending']);
        $processedEvent = new AsteriskEvent(['processing_status' => 'processed']);
        $failedEvent = new AsteriskEvent(['processing_status' => 'failed']);

        $this->assertEquals('pending', $pendingEvent->processing_status);
        $this->assertEquals('processed', $processedEvent->processing_status);
        $this->assertEquals('failed', $failedEvent->processing_status);
    }
}
