<?php

namespace AsteriskPbxManager\Tests\Unit\Generators;

use AsteriskPbxManager\Tests\Unit\Mocks\PamiMockFactory;
use Carbon\Carbon;
use Mockery\MockInterface;

/**
 * Generator for fake Asterisk events for testing purposes.
 */
class EventGenerator
{
    /**
     * Generate a complete outbound call flow sequence.
     *
     * @param array $config Call configuration
     * @return array Array of mock events
     */
    public static function generateOutboundCallFlow(array $config = []): array
    {
        $config = array_merge([
            'caller_channel' => 'SIP/1001-00000001',
            'caller_id' => '1001',
            'caller_name' => 'John Doe',
            'destination_channel' => 'SIP/1002-00000002',
            'destination_number' => '1002',
            'destination_name' => 'Jane Smith',
            'unique_id' => '1234567890.1',
            'dest_unique_id' => '1234567890.2',
            'context' => 'internal',
            'extension' => '1002',
            'call_duration' => 30,
            'success' => true,
        ], $config);

        $events = [];
        $timestamp = time();

        // 1. Dial Begin
        $events[] = PamiMockFactory::createDialEvent([
            'Channel' => $config['caller_channel'],
            'CallerIDNum' => $config['caller_id'],
            'CallerIDName' => $config['caller_name'],
            'DestChannel' => $config['destination_channel'],
            'ConnectedLineNum' => $config['destination_number'],
            'ConnectedLineName' => $config['destination_name'],
            'UniqueID' => $config['unique_id'],
            'DestUniqueID' => $config['dest_unique_id'],
            'Context' => $config['context'],
            'Extension' => $config['extension'],
            'SubEvent' => 'Begin',
            'DialStatus' => '',
            'Timestamp' => $timestamp,
        ]);

        $timestamp += 2; // 2 seconds later

        if ($config['success']) {
            // 2. Dial End (Answer)
            $events[] = PamiMockFactory::createDialEvent([
                'Channel' => $config['caller_channel'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'DestChannel' => $config['destination_channel'],
                'ConnectedLineNum' => $config['destination_number'],
                'ConnectedLineName' => $config['destination_name'],
                'UniqueID' => $config['unique_id'],
                'DestUniqueID' => $config['dest_unique_id'],
                'Context' => $config['context'],
                'Extension' => $config['extension'],
                'SubEvent' => 'End',
                'DialStatus' => 'ANSWER',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 3; // 3 seconds later

            // 3. Bridge Create
            $events[] = PamiMockFactory::createBridgeEvent([
                'Channel1' => $config['caller_channel'],
                'Channel2' => $config['destination_channel'],
                'UniqueID1' => $config['unique_id'],
                'UniqueID2' => $config['dest_unique_id'],
                'CallerID1' => $config['caller_id'],
                'CallerID2' => $config['destination_number'],
                'Bridgestate' => 'Link',
                'Bridgetype' => 'core',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += $config['call_duration']; // Call duration

            // 4. Bridge Destroy
            $events[] = PamiMockFactory::createBridgeEvent([
                'Channel1' => $config['caller_channel'],
                'Channel2' => $config['destination_channel'],
                'UniqueID1' => $config['unique_id'],
                'UniqueID2' => $config['dest_unique_id'],
                'CallerID1' => $config['caller_id'],
                'CallerID2' => $config['destination_number'],
                'Bridgestate' => 'Unlink',
                'Bridgetype' => 'core',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 1;

            // 5. Hangup (Destination)
            $events[] = PamiMockFactory::createHangupEvent([
                'Channel' => $config['destination_channel'],
                'UniqueID' => $config['dest_unique_id'],
                'CallerIDNum' => $config['destination_number'],
                'CallerIDName' => $config['destination_name'],
                'Cause' => '16',
                'CauseTxt' => 'Normal Clearing',
                'Context' => $config['context'],
                'Extension' => $config['extension'],
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 1;

            // 6. Hangup (Caller)
            $events[] = PamiMockFactory::createHangupEvent([
                'Channel' => $config['caller_channel'],
                'UniqueID' => $config['unique_id'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'Cause' => '16',
                'CauseTxt' => 'Normal Clearing',
                'Context' => $config['context'],
                'Extension' => $config['extension'],
                'Timestamp' => $timestamp,
            ]);
        } else {
            $timestamp += 15; // Ring timeout

            // 2. Dial End (No Answer)
            $events[] = PamiMockFactory::createDialEvent([
                'Channel' => $config['caller_channel'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'DestChannel' => $config['destination_channel'],
                'ConnectedLineNum' => $config['destination_number'],
                'ConnectedLineName' => $config['destination_name'],
                'UniqueID' => $config['unique_id'],
                'DestUniqueID' => $config['dest_unique_id'],
                'Context' => $config['context'],
                'Extension' => $config['extension'],
                'SubEvent' => 'End',
                'DialStatus' => 'NOANSWER',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 1;

            // 3. Hangup (Caller)
            $events[] = PamiMockFactory::createHangupEvent([
                'Channel' => $config['caller_channel'],
                'UniqueID' => $config['unique_id'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'Cause' => '19',
                'CauseTxt' => 'No Answer',
                'Context' => $config['context'],
                'Extension' => $config['extension'],
                'Timestamp' => $timestamp,
            ]);
        }

        return $events;
    }

    /**
     * Generate an inbound call flow sequence.
     *
     * @param array $config Call configuration
     * @return array Array of mock events
     */
    public static function generateInboundCallFlow(array $config = []): array
    {
        $config = array_merge([
            'caller_channel' => 'SIP/trunk-00000001',
            'caller_id' => '+15551234567',
            'caller_name' => 'External Caller',
            'destination_channel' => 'SIP/1001-00000002',
            'destination_number' => '1001',
            'destination_name' => 'Reception',
            'unique_id' => '1234567890.1',
            'dest_unique_id' => '1234567890.2',
            'context' => 'incoming',
            'extension' => '100',
            'call_duration' => 45,
            'success' => true,
        ], $config);

        // Use same flow logic but with inbound characteristics
        $config['context'] = 'incoming';
        return self::generateOutboundCallFlow($config);
    }

    /**
     * Generate queue call flow events.
     *
     * @param array $config Queue call configuration
     * @return array Array of mock events
     */
    public static function generateQueueCallFlow(array $config = []): array
    {
        $config = array_merge([
            'caller_channel' => 'SIP/trunk-00000001',
            'caller_id' => '+15551234567',
            'caller_name' => 'Customer',
            'queue_name' => 'support',
            'agent_channel' => 'SIP/1001-00000002',
            'agent_interface' => 'SIP/1001',
            'agent_name' => 'Agent Smith',
            'unique_id' => '1234567890.1',
            'wait_time' => 30,
            'talk_time' => 120,
            'success' => true,
        ], $config);

        $events = [];
        $timestamp = time();

        // 1. Queue Join
        $events[] = PamiMockFactory::createEvent('QueueCallerJoin', [
            'Channel' => $config['caller_channel'],
            'CallerIDNum' => $config['caller_id'],
            'CallerIDName' => $config['caller_name'],
            'Queue' => $config['queue_name'],
            'Position' => '1',
            'Count' => '1',
            'UniqueID' => $config['unique_id'],
            'Timestamp' => $timestamp,
        ]);

        $timestamp += $config['wait_time'];

        if ($config['success']) {
            // 2. Agent Connect
            $events[] = PamiMockFactory::createEvent('AgentConnect', [
                'Channel' => $config['caller_channel'],
                'Member' => $config['agent_interface'],
                'MemberName' => $config['agent_name'],
                'Queue' => $config['queue_name'],
                'UniqueID' => $config['unique_id'],
                'Holdtime' => $config['wait_time'],
                'BridgedChannel' => $config['agent_channel'],
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 2;

            // 3. Bridge Create
            $events[] = PamiMockFactory::createBridgeEvent([
                'Channel1' => $config['caller_channel'],
                'Channel2' => $config['agent_channel'],
                'UniqueID1' => $config['unique_id'],
                'UniqueID2' => $config['unique_id'] . '.2',
                'CallerID1' => $config['caller_id'],
                'CallerID2' => $config['agent_interface'],
                'Bridgestate' => 'Link',
                'Bridgetype' => 'core',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += $config['talk_time'];

            // 4. Agent Complete
            $events[] = PamiMockFactory::createEvent('AgentComplete', [
                'Channel' => $config['caller_channel'],
                'Member' => $config['agent_interface'],
                'MemberName' => $config['agent_name'],
                'Queue' => $config['queue_name'],
                'UniqueID' => $config['unique_id'],
                'HoldTime' => $config['wait_time'],
                'TalkTime' => $config['talk_time'],
                'Reason' => 'caller',
                'Timestamp' => $timestamp,
            ]);

            $timestamp += 1;

            // 5. Queue Leave
            $events[] = PamiMockFactory::createEvent('QueueCallerLeave', [
                'Channel' => $config['caller_channel'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'Queue' => $config['queue_name'],
                'Count' => '0',
                'Position' => '1',
                'UniqueID' => $config['unique_id'],
                'Timestamp' => $timestamp,
            ]);
        } else {
            $timestamp += 30; // Additional wait time before abandoning

            // 2. Queue Abandon
            $events[] = PamiMockFactory::createEvent('QueueCallerAbandon', [
                'Channel' => $config['caller_channel'],
                'CallerIDNum' => $config['caller_id'],
                'CallerIDName' => $config['caller_name'],
                'Queue' => $config['queue_name'],
                'Position' => '1',
                'OriginalPosition' => '1',
                'HoldTime' => $config['wait_time'] + 30,
                'UniqueID' => $config['unique_id'],
                'Timestamp' => $timestamp,
            ]);
        }

        return $events;
    }

    /**
     * Generate queue management events sequence.
     *
     * @param array $config Queue management configuration
     * @return array Array of mock events
     */
    public static function generateQueueManagementEvents(array $config = []): array
    {
        $config = array_merge([
            'queue_name' => 'support',
            'agents' => [
                ['interface' => 'SIP/1001', 'name' => 'Agent Smith'],
                ['interface' => 'SIP/1002', 'name' => 'Agent Jones'],
                ['interface' => 'SIP/1003', 'name' => 'Agent Brown'],
            ],
        ], $config);

        $events = [];
        $timestamp = time();

        foreach ($config['agents'] as $index => $agent) {
            // Add member
            $events[] = PamiMockFactory::createQueueMemberAddedEvent([
                'Queue' => $config['queue_name'],
                'Interface' => $agent['interface'],
                'MemberName' => $agent['name'],
                'StateInterface' => $agent['interface'],
                'Membership' => 'dynamic',
                'Penalty' => '0',
                'CallsTaken' => '0',
                'Status' => '1', // Available
                'Paused' => '0',
                'Timestamp' => $timestamp + $index,
            ]);

            // Pause member after some time
            if ($index === 1) {
                $events[] = PamiMockFactory::createEvent('QueueMemberPause', [
                    'Queue' => $config['queue_name'],
                    'Interface' => $agent['interface'],
                    'MemberName' => $agent['name'],
                    'Paused' => '1',
                    'Reason' => 'Break time',
                    'Timestamp' => $timestamp + 30,
                ]);
            }
        }

        return $events;
    }

    /**
     * Generate conference/meetme events.
     *
     * @param array $config Conference configuration
     * @return array Array of mock events
     */
    public static function generateConferenceEvents(array $config = []): array
    {
        $config = array_merge([
            'conference' => '1001',
            'participants' => [
                ['channel' => 'SIP/1001-00000001', 'user' => '1', 'caller_id' => '1001'],
                ['channel' => 'SIP/1002-00000002', 'user' => '2', 'caller_id' => '1002'],
                ['channel' => 'SIP/1003-00000003', 'user' => '3', 'caller_id' => '1003'],
            ],
        ], $config);

        $events = [];
        $timestamp = time();

        foreach ($config['participants'] as $index => $participant) {
            // Join conference
            $events[] = PamiMockFactory::createEvent('MeetmeJoin', [
                'Meetme' => $config['conference'],
                'User' => $participant['user'],
                'Channel' => $participant['channel'],
                'CallerIDNum' => $participant['caller_id'],
                'CallerIDName' => "User {$participant['caller_id']}",
                'Count' => (string)($index + 1),
                'Timestamp' => $timestamp + $index * 5,
            ]);
        }

        // Mute/unmute events
        $events[] = PamiMockFactory::createEvent('MeetmeMute', [
            'Meetme' => $config['conference'],
            'User' => '2',
            'Channel' => 'SIP/1002-00000002',
            'Status' => 'on',
            'Timestamp' => $timestamp + 60,
        ]);

        // Leave conference
        $events[] = PamiMockFactory::createEvent('MeetmeLeave', [
            'Meetme' => $config['conference'],
            'User' => '3',
            'Channel' => 'SIP/1003-00000003',
            'CallerIDNum' => '1003',
            'CallerIDName' => 'User 1003',
            'Count' => '2',
            'Timestamp' => $timestamp + 120,
        ]);

        return $events;
    }

    /**
     * Generate a sequence of random events for stress testing.
     *
     * @param int $count Number of events to generate
     * @param array $eventTypes Event types to include
     * @return array Array of mock events
     */
    public static function generateRandomEvents(int $count = 100, array $eventTypes = null): array
    {
        $eventTypes = $eventTypes ?: ['Dial', 'Hangup', 'Bridge', 'QueueMemberAdded', 'NewChannel'];
        $events = [];
        $timestamp = time();

        for ($i = 0; $i < $count; $i++) {
            $eventType = $eventTypes[array_rand($eventTypes)];
            $channelId = sprintf('%04d', rand(1000, 9999));
            $uniqueId = $timestamp . rand(1000, 9999) . '.' . $i;

            switch ($eventType) {
                case 'Dial':
                    $events[] = PamiMockFactory::createDialEvent([
                        'Channel' => "SIP/{$channelId}-" . sprintf('%08d', $i + 1),
                        'UniqueID' => $uniqueId,
                        'CallerIDNum' => $channelId,
                        'Timestamp' => $timestamp + $i,
                    ]);
                    break;

                case 'Hangup':
                    $events[] = PamiMockFactory::createHangupEvent([
                        'Channel' => "SIP/{$channelId}-" . sprintf('%08d', $i + 1),
                        'UniqueID' => $uniqueId,
                        'CallerIDNum' => $channelId,
                        'Timestamp' => $timestamp + $i,
                    ]);
                    break;

                case 'QueueMemberAdded':
                    $events[] = PamiMockFactory::createQueueMemberAddedEvent([
                        'Queue' => 'queue_' . ($i % 3 + 1),
                        'Interface' => "SIP/{$channelId}",
                        'MemberName' => "Agent {$channelId}",
                        'Timestamp' => $timestamp + $i,
                    ]);
                    break;

                default:
                    $events[] = PamiMockFactory::createEvent($eventType, [
                        'Channel' => "SIP/{$channelId}-" . sprintf('%08d', $i + 1),
                        'UniqueID' => $uniqueId,
                        'Timestamp' => $timestamp + $i,
                    ]);
                    break;
            }
        }

        return $events;
    }

    /**
     * Generate events based on time-based scenario.
     *
     * @param Carbon $startTime Start time
     * @param Carbon $endTime End time
     * @param int $eventsPerMinute Average events per minute
     * @return array Array of mock events with realistic timestamps
     */
    public static function generateTimeBasedEvents(
        Carbon $startTime,
        Carbon $endTime,
        int $eventsPerMinute = 5
    ): array {
        $events = [];
        $current = $startTime->copy();
        $eventId = 1;

        while ($current->lt($endTime)) {
            $eventsThisMinute = rand(1, $eventsPerMinute * 2);

            for ($i = 0; $i < $eventsThisMinute; $i++) {
                $eventTime = $current->copy()->addSeconds(rand(0, 59));
                
                $events[] = PamiMockFactory::createDialEvent([
                    'Channel' => 'SIP/' . sprintf('%04d', rand(1001, 1010)) . '-' . sprintf('%08d', $eventId),
                    'UniqueID' => $eventTime->timestamp . '.' . $eventId,
                    'CallerIDNum' => sprintf('%04d', rand(1001, 1010)),
                    'Timestamp' => $eventTime->timestamp,
                ]);

                $eventId++;
            }

            $current->addMinute();
        }

        // Sort events by timestamp
        usort($events, function ($a, $b) {
            return $a->getKey('Timestamp') <=> $b->getKey('Timestamp');
        });

        return $events;
    }
}