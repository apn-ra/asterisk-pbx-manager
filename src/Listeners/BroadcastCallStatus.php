<?php

namespace AsteriskPbxManager\Listeners;

use AsteriskPbxManager\Events\AsteriskEvent;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class BroadcastCallStatus implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle call connected events for broadcasting.
     */
    public function handleCallConnected(CallConnected $event): void
    {
        // Only broadcast if broadcasting is enabled
        if (!config('asterisk-pbx-manager.events.broadcast', true)) {
            return;
        }

        try {
            $broadcastData = [
                'event_type'   => 'call_connected',
                'timestamp'    => now()->toISOString(),
                'channel'      => $event->channel,
                'caller_id'    => $event->callerIdNum,
                'connected_to' => $event->connectedLineNum,
                'extension'    => $event->extension,
                'context'      => $event->context ?? 'default',
                'status'       => 'connected',
            ];

            // Broadcast to specific channel based on extension or general channel
            $channelName = $event->extension ? "asterisk.extension.{$event->extension}" : 'asterisk.calls';

            Broadcast::channel($channelName)->send('call.status.update', $broadcastData);

            // Also broadcast to general asterisk events channel
            Broadcast::channel('asterisk.events')->send('call.connected', $broadcastData);

            Log::info('Call connected status broadcasted', [
                'channel'           => $event->channel,
                'extension'         => $event->extension,
                'broadcast_channel' => $channelName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast call connected status: '.$e->getMessage(), [
                'channel' => $event->channel,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle call ended events for broadcasting.
     */
    public function handleCallEnded(CallEnded $event): void
    {
        // Only broadcast if broadcasting is enabled
        if (!config('asterisk-pbx-manager.events.broadcast', true)) {
            return;
        }

        try {
            $broadcastData = [
                'event_type'   => 'call_ended',
                'timestamp'    => now()->toISOString(),
                'channel'      => $event->channel,
                'caller_id'    => $event->callerIdNum ?? 'Unknown',
                'duration'     => $event->duration ?? 0,
                'hangup_cause' => $event->cause ?? 'unknown',
                'context'      => $event->context ?? 'default',
                'status'       => 'ended',
            ];

            // Broadcast to specific channel based on extension or general channel
            $channelName = $event->extension ? "asterisk.extension.{$event->extension}" : 'asterisk.calls';

            Broadcast::channel($channelName)->send('call.status.update', $broadcastData);

            // Also broadcast to general asterisk events channel
            Broadcast::channel('asterisk.events')->send('call.ended', $broadcastData);

            Log::info('Call ended status broadcasted', [
                'channel'           => $event->channel,
                'cause'             => $event->cause ?? 'unknown',
                'duration'          => $event->duration ?? 0,
                'broadcast_channel' => $channelName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast call ended status: '.$e->getMessage(), [
                'channel' => $event->channel,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle queue member added events for broadcasting.
     */
    public function handleQueueMemberAdded(QueueMemberAdded $event): void
    {
        // Only broadcast if broadcasting is enabled
        if (!config('asterisk-pbx-manager.events.broadcast', true)) {
            return;
        }

        try {
            $broadcastData = [
                'event_type'  => 'queue_member_added',
                'timestamp'   => now()->toISOString(),
                'queue'       => $event->queue,
                'location'    => $event->location,
                'member_name' => $event->memberName ?? 'Unknown',
                'interface'   => $event->interface ?? $event->location,
                'status'      => 'added',
            ];

            // Broadcast to queue-specific channel
            $channelName = "asterisk.queue.{$event->queue}";

            Broadcast::channel($channelName)->send('queue.member.update', $broadcastData);

            // Also broadcast to general asterisk events channel
            Broadcast::channel('asterisk.events')->send('queue.member.added', $broadcastData);

            Log::info('Queue member added status broadcasted', [
                'queue'             => $event->queue,
                'location'          => $event->location,
                'broadcast_channel' => $channelName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast queue member added status: '.$e->getMessage(), [
                'queue'    => $event->queue,
                'location' => $event->location,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle generic Asterisk events for broadcasting.
     */
    public function handleAsteriskEvent(AsteriskEvent $event): void
    {
        // Only broadcast if broadcasting is enabled
        if (!config('asterisk-pbx-manager.events.broadcast', true)) {
            return;
        }

        // Only broadcast significant events to avoid noise
        $significantEvents = [
            'DialBegin',
            'DialEnd',
            'Hangup',
            'Bridge',
            'QueueMemberStatus',
            'QueueCallerJoin',
            'QueueCallerLeave',
            'AgentConnect',
            'AgentComplete',
        ];

        if (!in_array($event->eventName, $significantEvents)) {
            return;
        }

        try {
            $broadcastData = [
                'event_type' => 'asterisk_event',
                'timestamp'  => now()->toISOString(),
                'event_name' => $event->eventName,
                'channel'    => $event->channel ?? null,
                'unique_id'  => $event->uniqueId ?? null,
                'data'       => $event->rawData ?? [],
            ];

            // Broadcast to general asterisk events channel
            Broadcast::channel('asterisk.events')->send('asterisk.event', $broadcastData);

            // If there's a specific channel, also broadcast to channel-specific events
            if ($event->channel) {
                $channelName = "asterisk.channel.{$event->channel}";
                Broadcast::channel($channelName)->send('channel.event', $broadcastData);
            }

            Log::debug('Asterisk event broadcasted', [
                'event_name' => $event->eventName,
                'channel'    => $event->channel ?? 'N/A',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast Asterisk event: '.$e->getMessage(), [
                'event_name' => $event->eventName,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast system status update.
     */
    public function broadcastSystemStatus(array $statusData): void
    {
        // Only broadcast if broadcasting is enabled
        if (!config('asterisk-pbx-manager.events.broadcast', true)) {
            return;
        }

        try {
            $broadcastData = [
                'event_type' => 'system_status',
                'timestamp'  => now()->toISOString(),
                'status'     => $statusData,
            ];

            Broadcast::channel('asterisk.system')->send('system.status', $broadcastData);

            Log::info('System status broadcasted', [
                'connection_status' => $statusData['connected'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast system status: '.$e->getMessage(), [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CallConnected::class    => 'handleCallConnected',
            CallEnded::class        => 'handleCallEnded',
            QueueMemberAdded::class => 'handleQueueMemberAdded',
            AsteriskEvent::class    => 'handleAsteriskEvent',
        ];
    }
}
