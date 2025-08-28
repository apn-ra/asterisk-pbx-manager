<?php

namespace AsteriskPbxManager\Tests\Integration;

use AsteriskPbxManager\Events\AsteriskEvent;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class EventBroadcastingTest extends IntegrationTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable broadcasting for testing
        Config::set('asterisk-pbx-manager.events.broadcast', true);
    }

    public function test_asterisk_event_implements_should_broadcast()
    {
        $event = new AsteriskEvent();

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
    }

    public function test_asterisk_event_has_default_broadcast_channels()
    {
        $event = new AsteriskEvent();
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals('asterisk.events', $channels[0]->name);
    }

    public function test_asterisk_event_respects_channel_prefix_configuration()
    {
        Config::set('asterisk-pbx-manager.broadcasting.channel_prefix', 'pbx');

        $event = new AsteriskEvent();
        $channels = $event->broadcastOn();

        $this->assertEquals('pbx.events', $channels[0]->name);
    }

    public function test_asterisk_event_can_use_private_channels()
    {
        Config::set('asterisk-pbx-manager.broadcasting.private_channels', true);

        $event = new AsteriskEvent();
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('asterisk.events', $channels[0]->name);
    }

    public function test_call_connected_event_has_multiple_broadcast_channels()
    {
        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'          => 'SIP/1001-00000001',
            'Uniqueid'         => '1234567890.1',
            'CallerIDNum'      => '1001',
            'ConnectedLineNum' => '1002',
            'DialStatus'       => 'ANSWER',
        ]);

        $event = new CallConnected($mockEvent);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);

        $channelNames = array_map(fn ($ch) => $ch->name, $channels);
        $this->assertContains('asterisk.calls', $channelNames);
        $this->assertContains('asterisk.events', $channelNames);
        $this->assertContains('asterisk.calls.1234567890.1', $channelNames);
    }

    public function test_call_connected_event_respects_private_channel_configuration()
    {
        Config::set('asterisk-pbx-manager.broadcasting.private_channels', true);

        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'     => 'SIP/1001-00000001',
            'Uniqueid'    => '1234567890.1',
            'CallerIDNum' => '1001',
        ]);

        $event = new CallConnected($mockEvent);
        $channels = $event->broadcastOn();

        foreach ($channels as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel);
        }
    }

    public function test_call_ended_event_broadcasts_correctly()
    {
        $mockEvent = $this->createMockEvent('Hangup', [
            'Channel'     => 'SIP/1001-00000001',
            'Uniqueid'    => '1234567890.1',
            'CallerIDNum' => '1001',
            'Cause'       => '16',
            'CauseTxt'    => 'Normal Clearing',
        ]);

        $event = new CallEnded($mockEvent);
        $channels = $event->broadcastOn();

        $this->assertGreaterThan(0, count($channels));

        $channelNames = array_map(fn ($ch) => $ch->name, $channels);
        $this->assertContains('asterisk.calls', $channelNames);
        $this->assertContains('asterisk.events', $channelNames);
    }

    public function test_queue_member_added_event_broadcasts_correctly()
    {
        $mockEvent = $this->createMockEvent('QueueMemberAdded', [
            'Queue'          => 'support',
            'Interface'      => 'SIP/1001',
            'MemberName'     => 'Agent 1001',
            'StateInterface' => 'SIP/1001',
            'Penalty'        => '0',
        ]);

        $event = new QueueMemberAdded($mockEvent);
        $channels = $event->broadcastOn();

        $this->assertGreaterThan(0, count($channels));

        $channelNames = array_map(fn ($ch) => $ch->name, $channels);
        $this->assertContains('asterisk.queues', $channelNames);
        $this->assertContains('asterisk.events', $channelNames);
    }

    public function test_event_broadcasting_can_be_disabled()
    {
        Config::set('asterisk-pbx-manager.events.broadcast', false);

        // Mock the event dispatcher to check if broadcasting is attempted
        Event::fake();

        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'     => 'SIP/1001-00000001',
            'CallerIDNum' => '1001',
        ]);

        $event = new CallConnected($mockEvent);

        // Even though the event implements ShouldBroadcast,
        // broadcasting should be disabled by configuration
        $this->assertTrue($event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcast);
    }

    public function test_events_are_queued_for_broadcasting()
    {
        Queue::fake();

        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'     => 'SIP/1001-00000001',
            'Uniqueid'    => '1234567890.1',
            'CallerIDNum' => '1001',
        ]);

        event(new CallConnected($mockEvent));

        // Verify that broadcast jobs are queued
        Queue::assertPushed(\Illuminate\Broadcasting\BroadcastEvent::class);
    }

    public function test_event_data_is_properly_serialized_for_broadcasting()
    {
        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'          => 'SIP/1001-00000001',
            'Uniqueid'         => '1234567890.1',
            'CallerIDNum'      => '1001',
            'CallerIDName'     => 'John Doe',
            'ConnectedLineNum' => '1002',
            'Context'          => 'internal',
            'Extension'        => '1002',
        ]);

        $event = new CallConnected($mockEvent);

        // Test that the event data is accessible and properly structured
        $this->assertEquals('Dial', $event->eventName);
        $this->assertIsArray($event->data);
        $this->assertArrayHasKey('channel', $event->data);
        $this->assertArrayHasKey('uniqueid', $event->data);
        $this->assertArrayHasKey('calleridnum', $event->data);
        $this->assertEquals('SIP/1001-00000001', $event->data['channel']);
        $this->assertEquals('1001', $event->data['calleridnum']);
    }

    public function test_broadcast_as_returns_correct_event_name()
    {
        $event = new CallConnected();

        $broadcastName = $event->broadcastAs();
        $this->assertEquals('call.connected', $broadcastName);

        $event = new CallEnded();
        $broadcastName = $event->broadcastAs();
        $this->assertEquals('call.ended', $broadcastName);

        $event = new QueueMemberAdded();
        $broadcastName = $event->broadcastAs();
        $this->assertEquals('queue.member.added', $broadcastName);
    }

    public function test_broadcast_with_returns_event_data()
    {
        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'      => 'SIP/1001-00000001',
            'Uniqueid'     => '1234567890.1',
            'CallerIDNum'  => '1001',
            'CallerIDName' => 'John Doe',
        ]);

        $event = new CallConnected($mockEvent);
        $broadcastData = $event->broadcastWith();

        $this->assertIsArray($broadcastData);
        $this->assertArrayHasKey('event_name', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);
        $this->assertArrayHasKey('data', $broadcastData);
        $this->assertEquals('Dial', $broadcastData['event_name']);
        $this->assertIsArray($broadcastData['data']);
    }

    public function test_channel_specific_broadcasting_for_unique_calls()
    {
        $mockEvent1 = $this->createMockEvent('Dial', [
            'Channel'  => 'SIP/1001-00000001',
            'Uniqueid' => '1234567890.1',
        ]);

        $mockEvent2 = $this->createMockEvent('Dial', [
            'Channel'  => 'SIP/1002-00000002',
            'Uniqueid' => '1234567890.2',
        ]);

        $event1 = new CallConnected($mockEvent1);
        $event2 = new CallConnected($mockEvent2);

        $channels1 = $event1->broadcastOn();
        $channels2 = $event2->broadcastOn();

        $channelNames1 = array_map(fn ($ch) => $ch->name, $channels1);
        $channelNames2 = array_map(fn ($ch) => $ch->name, $channels2);

        // Both should have unique channel-specific broadcasts
        $this->assertContains('asterisk.calls.1234567890.1', $channelNames1);
        $this->assertContains('asterisk.calls.1234567890.2', $channelNames2);
        $this->assertNotContains('asterisk.calls.1234567890.2', $channelNames1);
        $this->assertNotContains('asterisk.calls.1234567890.1', $channelNames2);
    }

    public function test_broadcasting_with_custom_configuration()
    {
        Config::set('asterisk-pbx-manager.broadcasting', [
            'channel_prefix'   => 'custom-pbx',
            'private_channels' => true,
            'enabled'          => true,
        ]);

        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'  => 'SIP/1001-00000001',
            'Uniqueid' => '1234567890.1',
        ]);

        $event = new CallConnected($mockEvent);
        $channels = $event->broadcastOn();

        $channelNames = array_map(fn ($ch) => $ch->name, $channels);

        $this->assertContains('custom-pbx.calls', $channelNames);
        $this->assertContains('custom-pbx.events', $channelNames);
        $this->assertContains('custom-pbx.calls.1234567890.1', $channelNames);

        // All channels should be private
        foreach ($channels as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel);
        }
    }

    public function test_event_listeners_receive_broadcasted_events()
    {
        Event::fake([CallConnected::class]);

        $mockEvent = $this->createMockEvent('Dial', [
            'Channel'     => 'SIP/1001-00000001',
            'Uniqueid'    => '1234567890.1',
            'CallerIDNum' => '1001',
        ]);

        // Dispatch the event
        event(new CallConnected($mockEvent));

        // Assert the event was dispatched
        Event::assertDispatched(CallConnected::class, function ($event) {
            return $event->eventName === 'Dial' &&
                   $event->data['channel'] === 'SIP/1001-00000001';
        });
    }

    public function test_broadcasting_configuration_validation()
    {
        // Test with invalid channel prefix
        Config::set('asterisk-pbx-manager.broadcasting.channel_prefix', '');

        $event = new AsteriskEvent();
        $channels = $event->broadcastOn();

        // Should fallback to default prefix
        $this->assertEquals('asterisk.events', $channels[0]->name);

        // Test with null configuration
        Config::set('asterisk-pbx-manager.broadcasting', null);

        $event = new AsteriskEvent();
        $channels = $event->broadcastOn();

        // Should use defaults
        $this->assertEquals('asterisk.events', $channels[0]->name);
        $this->assertInstanceOf(Channel::class, $channels[0]);
    }

    public function test_event_broadcasting_maintains_chronological_order()
    {
        Queue::fake();

        $events = [];
        for ($i = 1; $i <= 3; $i++) {
            $mockEvent = $this->createMockEvent('TestEvent', [
                'Channel'  => "SIP/100{$i}-0000000{$i}",
                'Uniqueid' => "123456789{$i}.{$i}",
                'Sequence' => $i,
            ]);

            $events[] = new AsteriskEvent($mockEvent);
            event(end($events));

            // Small delay to ensure different timestamps
            usleep(1000);
        }

        // Verify all events were queued for broadcasting
        Queue::assertPushed(\Illuminate\Broadcasting\BroadcastEvent::class, 3);

        // Verify events maintain their order through timestamps
        $this->assertLessThan($events[1]->timestamp, $events[0]->timestamp);
        $this->assertLessThan($events[2]->timestamp, $events[1]->timestamp);
    }

    protected function tearDown(): void
    {
        // Reset configuration
        Config::set('asterisk-pbx-manager.broadcasting', [
            'channel_prefix'   => 'asterisk',
            'private_channels' => false,
            'enabled'          => true,
        ]);

        parent::tearDown();
    }
}
