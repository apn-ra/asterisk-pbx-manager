<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;
use AsteriskPbxManager\Events\AsteriskEvent;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\DialEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\BridgeEvent;
use PAMI\Message\Event\QueueMemberAddedEvent;
use Mockery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class EventProcessorTest extends UnitTestCase
{
    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processor = new EventProcessor();
    }

    public function testProcessEventWithDialEvent()
    {
        $mockEvent = $this->createMockDialEvent('SIP/1001-12345', '2002', 'ANSWER');

        Event::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(CallConnected::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        $this->processor->processEvent($mockEvent);
    }

    public function testProcessEventWithHangupEvent()
    {
        $mockEvent = $this->createMockHangupEvent('SIP/1001-12345', 'Normal Clearing', 30);

        Event::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(CallEnded::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        $this->processor->processEvent($mockEvent);
    }

    public function testProcessEventWithQueueMemberAddedEvent()
    {
        $mockEvent = $this->createMockQueueMemberAddedEvent('support', 'SIP/1001', 'John Doe');

        Event::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(QueueMemberAdded::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        $this->processor->processEvent($mockEvent);
    }

    public function testProcessEventWithUnknownEvent()
    {
        $mockEvent = $this->createMockEvent('UnknownEvent', [
            'Channel' => 'SIP/1001-12345',
            'Context' => 'default'
        ]);

        Event::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(AsteriskEvent::class));

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Processing unknown AMI event', Mockery::any());

        $this->processor->processEvent($mockEvent);
    }


    public function testRegisterCustomHandler()
    {
        $handler = function($event) {
            // Custom handler logic
        };

        // Since registerCustomHandler doesn't return anything and hasCustomHandler is protected,
        // we just ensure no exception is thrown
        $this->processor->registerCustomHandler('CustomEvent', $handler);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testAddEventFilter()
    {
        $filter = function($event) {
            return $event->getEventName() === 'Dial';
        };

        $this->processor->addEventFilter($filter);
        
        // Since the method doesn't return anything, we just ensure no exception is thrown
        $this->assertTrue(true);
    }


    public function testGetStatistics()
    {
        $stats = $this->processor->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('events_processed', $stats);
        $this->assertArrayHasKey('events_filtered', $stats);
        $this->assertArrayHasKey('custom_handlers', $stats);
        $this->assertArrayHasKey('processing_errors', $stats);
    }

    public function testResetStatistics()
    {
        // Process an event to increment statistics
        $mockEvent = $this->createMockDialEvent('SIP/1001-12345', '2002', 'ANSWER');
        
        Event::shouldReceive('dispatch')->once();
        Log::shouldReceive('info')->twice();
        
        $this->processor->processEvent($mockEvent);
        
        // Reset statistics
        $this->processor->resetStatistics();
        
        $stats = $this->processor->getStatistics();
        $this->assertEquals(0, $stats['events_processed']);
        $this->assertEquals(0, $stats['events_filtered']);
        $this->assertEquals(0, $stats['processing_errors']);
    }

    public function testProcessEventWithFilteredEvent()
    {
        $filter = function($event) {
            return false; // Filter out all events
        };

        $this->processor->addEventFilter($filter);

        $mockEvent = $this->createMockDialEvent('SIP/1001-12345', '2002', 'ANSWER');

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Event filtered out by custom filter', Mockery::any());

        $this->processor->processEvent($mockEvent);
    }

    public function testProcessEventWithException()
    {
        // Mock Event facade to throw exception
        Event::shouldReceive('dispatch')
            ->once()
            ->andThrow(new \Exception('Event dispatch failed'));

        Log::shouldReceive('info')
            ->once()
            ->with('Processing AMI event', Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Error processing AMI event', Mockery::any());

        $mockEvent = $this->createMockDialEvent('SIP/1001-12345', '2002', 'ANSWER');

        $this->processor->processEvent($mockEvent);
    }
}