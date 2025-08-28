<?php

namespace AsteriskPbxManager\Listeners;

use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\AsteriskEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogCallEvent implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle call connected events.
     */
    public function handleCallConnected(CallConnected $event): void
    {
        try {
            DB::table('asterisk_call_logs')->insert([
                'channel' => $event->channel,
                'caller_id' => $event->callerIdNum,
                'connected_to' => $event->connectedLineNum,
                'context' => $event->context ?? 'default',
                'direction' => $this->determineDirection($event->channel),
                'started_at' => now(),
                'metadata' => json_encode([
                    'event_type' => 'call_connected',
                    'extension' => $event->extension,
                    'raw_data' => $event->rawData ?? []
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Call connected event logged to database', [
                'channel' => $event->channel,
                'caller_id' => $event->callerIdNum,
                'connected_to' => $event->connectedLineNum
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log call connected event: ' . $e->getMessage(), [
                'channel' => $event->channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle call ended events.
     */
    public function handleCallEnded(CallEnded $event): void
    {
        try {
            // Update existing call log record or create new one if not found
            $updated = DB::table('asterisk_call_logs')
                ->where('channel', $event->channel)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => now(),
                    'duration' => $event->duration ?? null,
                    'hangup_cause' => $event->cause ?? 'unknown',
                    'metadata' => DB::raw("JSON_MERGE_PATCH(COALESCE(metadata, '{}'), '" . 
                        json_encode([
                            'event_type' => 'call_ended',
                            'hangup_cause' => $event->cause ?? 'unknown',
                            'raw_data' => $event->rawData ?? []
                        ]) . "')"),
                    'updated_at' => now(),
                ]);

            // If no existing record was updated, create a new one
            if ($updated === 0) {
                DB::table('asterisk_call_logs')->insert([
                    'channel' => $event->channel,
                    'caller_id' => $event->callerIdNum ?? null,
                    'context' => $event->context ?? 'default',
                    'direction' => $this->determineDirection($event->channel),
                    'started_at' => now()->subSeconds($event->duration ?? 0),
                    'ended_at' => now(),
                    'duration' => $event->duration ?? null,
                    'hangup_cause' => $event->cause ?? 'unknown',
                    'metadata' => json_encode([
                        'event_type' => 'call_ended',
                        'hangup_cause' => $event->cause ?? 'unknown',
                        'raw_data' => $event->rawData ?? []
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Call ended event logged to database', [
                'channel' => $event->channel,
                'cause' => $event->cause ?? 'unknown',
                'duration' => $event->duration ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log call ended event: ' . $e->getMessage(), [
                'channel' => $event->channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle generic Asterisk events.
     */
    public function handleAsteriskEvent(AsteriskEvent $event): void
    {
        // Only log if database logging is enabled
        if (!config('asterisk-pbx-manager.events.log_to_database', true)) {
            return;
        }

        try {
            DB::table('asterisk_events')->insert([
                'event_name' => $event->eventName,
                'channel' => $event->channel ?? null,
                'unique_id' => $event->uniqueId ?? null,
                'event_data' => json_encode($event->rawData ?? []),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::debug('Asterisk event logged to database', [
                'event_name' => $event->eventName,
                'channel' => $event->channel ?? 'N/A',
                'unique_id' => $event->uniqueId ?? 'N/A'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log Asterisk event: ' . $e->getMessage(), [
                'event_name' => $event->eventName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine call direction based on channel information.
     */
    protected function determineDirection(string $channel): string
    {
        // Simple heuristic - can be enhanced based on dial plan conventions
        if (preg_match('/^(SIP|PJSIP)\/\d+/', $channel)) {
            return 'outbound';
        } elseif (preg_match('/^(Zap|DAHDI)\//', $channel)) {
            return 'inbound';
        }
        
        return 'unknown';
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CallConnected::class => 'handleCallConnected',
            CallEnded::class => 'handleCallEnded',
            AsteriskEvent::class => 'handleAsteriskEvent',
        ];
    }
}