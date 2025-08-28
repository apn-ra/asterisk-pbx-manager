<?php

namespace AsteriskPbxManager\Tests\Unit\Models;

use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CallLogTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up the database schema for testing
        $this->loadLaravelMigrations();
    }

    public function testCallLogCreation()
    {
        $callLog = new CallLog([
            'channel' => 'SIP/1001-12345',
            'caller_id_num' => '1001',
            'caller_id_name' => 'John Doe',
            'connected_to' => '2002',
            'direction' => 'outbound',
            'call_status' => 'answered',
            'start_time' => now(),
            'answer_time' => now()->addSeconds(5),
            'end_time' => now()->addSeconds(35),
            'duration' => 35,
            'talk_time' => 30
        ]);

        $this->assertInstanceOf(CallLog::class, $callLog);
        $this->assertEquals('SIP/1001-12345', $callLog->channel);
        $this->assertEquals('1001', $callLog->caller_id_num);
        $this->assertEquals('outbound', $callLog->direction);
    }

    public function testEventsRelationship()
    {
        $callLog = new CallLog();
        $relationship = $callLog->events();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relationship);
        $this->assertEquals('call_log_id', $relationship->getForeignKeyName());
        $this->assertEquals(AsteriskEvent::class, $relationship->getRelated()::class);
    }

    public function testSignificantEventsRelationship()
    {
        $callLog = new CallLog();
        $relationship = $callLog->significantEvents();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relationship);
    }

    public function testEventsByTypeRelationship()
    {
        $callLog = new CallLog();
        $relationship = $callLog->eventsByType('Dial');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relationship);
    }

    public function testDateRangeScope()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        $query = CallLog::dateRange($startDate, $endDate);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByDirectionScope()
    {
        $query = CallLog::byDirection('inbound');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByStatusScope()
    {
        $query = CallLog::byStatus('answered');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testAnsweredScope()
    {
        $query = CallLog::answered();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testUnansweredScope()
    {
        $query = CallLog::unanswered();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testLongerThanScope()
    {
        $query = CallLog::longerThan(30);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testShorterThanScope()
    {
        $query = CallLog::shorterThan(10);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testInvolvingNumberScope()
    {
        $query = CallLog::involvingNumber('1001');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testQueueCallsScope()
    {
        $query = CallLog::queueCalls('support');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testRecordedScope()
    {
        $query = CallLog::recorded();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testQualityIssuesScope()
    {
        $query = CallLog::qualityIssues(3.5);
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testByAgentScope()
    {
        $query = CallLog::byAgent('agent001');
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function testGetDurationFormattedAttribute()
    {
        $callLog = new CallLog(['duration' => 125]);
        
        $this->assertEquals('02:05', $callLog->duration_formatted);
    }

    public function testGetDurationFormattedAttributeWithNull()
    {
        $callLog = new CallLog(['duration' => null]);
        
        $this->assertEquals('00:00', $callLog->duration_formatted);
    }

    public function testGetCostFormattedAttribute()
    {
        $callLog = new CallLog(['cost' => 1.25, 'cost_currency' => 'USD']);
        
        $this->assertEquals('$1.25', $callLog->cost_formatted);
    }

    public function testGetCostFormattedAttributeWithNullCost()
    {
        $callLog = new CallLog(['cost' => null]);
        
        $this->assertEquals('$0.00', $callLog->cost_formatted);
    }

    public function testGetIsAnsweredAttribute()
    {
        $answeredCall = new CallLog(['call_status' => 'answered']);
        $unansweredCall = new CallLog(['call_status' => 'no_answer']);
        
        $this->assertTrue($answeredCall->is_answered);
        $this->assertFalse($unansweredCall->is_answered);
    }

    public function testGetIsSuccessfulAttribute()
    {
        $successfulCall = new CallLog(['call_status' => 'answered', 'duration' => 30]);
        $unsuccessfulCall = new CallLog(['call_status' => 'busy']);
        
        $this->assertTrue($successfulCall->is_successful);
        $this->assertFalse($unsuccessfulCall->is_successful);
    }

    public function testGetCallOutcomeAttribute()
    {
        $answeredCall = new CallLog(['call_status' => 'answered', 'duration' => 30]);
        $busyCall = new CallLog(['call_status' => 'busy']);
        $noAnswerCall = new CallLog(['call_status' => 'no_answer']);
        
        $this->assertEquals('successful', $answeredCall->call_outcome);
        $this->assertEquals('failed', $busyCall->call_outcome);
        $this->assertEquals('failed', $noAnswerCall->call_outcome);
    }

    public function testSetCallerIdNumAttribute()
    {
        $callLog = new CallLog();
        $callLog->caller_id_num = '+1-555-123-4567';
        
        $this->assertEquals('15551234567', $callLog->caller_id_num);
    }

    public function testSetCallerIdNumAttributeWithNull()
    {
        $callLog = new CallLog();
        $callLog->caller_id_num = null;
        
        $this->assertNull($callLog->caller_id_num);
    }

    public function testSetConnectedToAttribute()
    {
        $callLog = new CallLog();
        $callLog->connected_to = '+1 (555) 987-6543';
        
        $this->assertEquals('15559876543', $callLog->connected_to);
    }

    public function testNormalizePhoneNumber()
    {
        $callLog = new CallLog();
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($callLog);
        $method = $reflection->getMethod('normalizePhoneNumber');
        $method->setAccessible(true);
        
        $this->assertEquals('15551234567', $method->invoke($callLog, '+1-555-123-4567'));
        $this->assertEquals('15551234567', $method->invoke($callLog, '(555) 123-4567'));
        $this->assertEquals('5551234567', $method->invoke($callLog, '555.123.4567'));
        $this->assertNull($method->invoke($callLog, null));
        $this->assertNull($method->invoke($callLog, ''));
    }

    public function testGetCurrencySymbol()
    {
        $callLog = new CallLog();
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($callLog);
        $method = $reflection->getMethod('getCurrencySymbol');
        $method->setAccessible(true);
        
        $this->assertEquals('$', $method->invoke($callLog, 'USD'));
        $this->assertEquals('€', $method->invoke($callLog, 'EUR'));
        $this->assertEquals('£', $method->invoke($callLog, 'GBP'));
        $this->assertEquals('$', $method->invoke($callLog, 'UNKNOWN'));
    }

    public function testGetStatisticsReturnsArray()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        $stats = CallLog::getStatistics($startDate, $endDate);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_calls', $stats);
        $this->assertArrayHasKey('answered_calls', $stats);
        $this->assertArrayHasKey('missed_calls', $stats);
        $this->assertArrayHasKey('average_duration', $stats);
    }

    public function testGetHourlyVolumeReturnsArray()
    {
        $date = Carbon::now();
        
        $volume = CallLog::getHourlyVolume($date);
        
        $this->assertIsArray($volume);
        $this->assertCount(24, $volume); // Should have 24 hours
    }

    public function testHasFactoryTrait()
    {
        $this->assertTrue(method_exists(CallLog::class, 'factory'));
    }

    public function testUsesSoftDeletes()
    {
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(CallLog::class)));
    }

    public function testFillableAttributes()
    {
        $callLog = new CallLog();
        $fillable = $callLog->getFillable();
        
        $this->assertContains('channel', $fillable);
        $this->assertContains('caller_id_num', $fillable);
        $this->assertContains('direction', $fillable);
        $this->assertContains('call_status', $fillable);
    }

    public function testCastsAttributes()
    {
        $callLog = new CallLog();
        $casts = $callLog->getCasts();
        
        $this->assertArrayHasKey('start_time', $casts);
        $this->assertArrayHasKey('answer_time', $casts);
        $this->assertArrayHasKey('end_time', $casts);
    }

    public function testTableName()
    {
        $callLog = new CallLog();
        
        $this->assertEquals('asterisk_call_logs', $callLog->getTable());
    }
}