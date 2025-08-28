<?php

namespace AsteriskPbxManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsteriskEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'asterisk_events';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_name',
        'event_type',
        'sub_event',
        'event_timestamp',
        'received_at',
        'processed_at',
        'channel',
        'dest_channel',
        'unique_id',
        'dest_unique_id',
        'linked_id',
        'caller_id_num',
        'caller_id_name',
        'connected_line_num',
        'connected_line_name',
        'context',
        'extension',
        'dest_context',
        'dest_extension',
        'priority',
        'application',
        'application_data',
        'dial_string',
        'dial_status',
        'bridge_id',
        'bridge_type',
        'bridge_technology',
        'bridge_creator',
        'bridge_name',
        'bridge_num_channels',
        'queue',
        'interface',
        'member_name',
        'state_interface',
        'penalty',
        'calls_taken',
        'last_call',
        'last_pause',
        'in_call',
        'paused',
        'pause_reason',
        'status',
        'reason',
        'ring_time',
        'talk_time',
        'hold_time',
        'cause',
        'cause_code',
        'cause_txt',
        'channel_state',
        'channel_state_desc',
        'digit',
        'direction',
        'duration_ms',
        'filename',
        'format',
        'variable',
        'value',
        'event_data',
        'parsed_data',
        'metadata',
        'server_id',
        'asterisk_version',
        'server_ip',
        'processing_status',
        'is_significant',
        'needs_action',
        'error_message',
        'call_log_id',
        'parent_event_id',
        'correlation_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'event_timestamp'     => 'datetime',
        'received_at'         => 'datetime',
        'processed_at'        => 'datetime',
        'penalty'             => 'integer',
        'calls_taken'         => 'integer',
        'last_call'           => 'integer',
        'last_pause'          => 'integer',
        'ring_time'           => 'integer',
        'talk_time'           => 'integer',
        'hold_time'           => 'integer',
        'cause_code'          => 'integer',
        'channel_state'       => 'integer',
        'duration_ms'         => 'integer',
        'bridge_num_channels' => 'integer',
        'in_call'             => 'boolean',
        'paused'              => 'boolean',
        'is_significant'      => 'boolean',
        'needs_action'        => 'boolean',
        'event_data'          => 'array',
        'parsed_data'         => 'array',
        'metadata'            => 'array',
        'server_ip'           => 'encrypted',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'event_data',
        'server_ip',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'event_category',
        'duration_formatted',
        'is_call_event',
        'is_queue_event',
        'is_bridge_event',
    ];

    /**
     * Get the call log that this event belongs to.
     */
    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class, 'call_log_id');
    }

    /**
     * Get the parent event that this event belongs to.
     */
    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(AsteriskEvent::class, 'parent_event_id');
    }

    /**
     * Get the child events that belong to this event.
     */
    public function childEvents(): HasMany
    {
        return $this->hasMany(AsteriskEvent::class, 'parent_event_id');
    }

    /**
     * Get related events with the same correlation ID.
     */
    public function relatedEvents(): HasMany
    {
        return $this->hasMany(AsteriskEvent::class, 'correlation_id', 'correlation_id')
                    ->where('id', '!=', $this->id);
    }

    /**
     * Scope a query to only include events from a specific date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('event_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include events by name.
     */
    public function scopeByEventName(Builder $query, string $eventName): Builder
    {
        return $query->where('event_name', $eventName);
    }

    /**
     * Scope a query to only include events by type.
     */
    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include events by channel.
     */
    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where(function ($q) use ($channel) {
            $q->where('channel', $channel)
              ->orWhere('dest_channel', $channel);
        });
    }

    /**
     * Scope a query to only include events by unique ID.
     */
    public function scopeByUniqueId(Builder $query, string $uniqueId): Builder
    {
        return $query->where(function ($q) use ($uniqueId) {
            $q->where('unique_id', $uniqueId)
              ->orWhere('dest_unique_id', $uniqueId)
              ->orWhere('linked_id', $uniqueId);
        });
    }

    /**
     * Scope a query to only include significant events.
     */
    public function scopeSignificant(Builder $query): Builder
    {
        return $query->where('is_significant', true);
    }

    /**
     * Scope a query to only include events that need action.
     */
    public function scopeNeedsAction(Builder $query): Builder
    {
        return $query->where('needs_action', true);
    }

    /**
     * Scope a query to only include processed events.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('processing_status', 'processed');
    }

    /**
     * Scope a query to only include pending events.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Scope a query to only include failed events.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope a query to only include call-related events.
     */
    public function scopeCallEvents(Builder $query): Builder
    {
        return $query->whereIn('event_name', [
            'DialBegin', 'DialEnd', 'Hangup', 'Bridge', 'Unbridge',
            'NewCallerid', 'NewConnectedLine', 'NewExten', 'NewState',
        ]);
    }

    /**
     * Scope a query to only include queue-related events.
     */
    public function scopeQueueEvents(Builder $query): Builder
    {
        return $query->whereIn('event_name', [
            'QueueMemberAdded', 'QueueMemberRemoved', 'QueueMemberPause',
            'QueueCallerJoin', 'QueueCallerLeave', 'AgentConnect', 'AgentComplete',
        ]);
    }

    /**
     * Scope a query to only include bridge-related events.
     */
    public function scopeBridgeEvents(Builder $query): Builder
    {
        return $query->whereIn('event_name', [
            'BridgeCreate', 'BridgeDestroy', 'BridgeEnter', 'BridgeLeave',
        ]);
    }

    /**
     * Scope a query to only include events by queue.
     */
    public function scopeByQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope a query to only include events by caller ID.
     */
    public function scopeByCallerId(Builder $query, string $callerId): Builder
    {
        return $query->where('caller_id_num', 'like', "%{$callerId}%");
    }

    /**
     * Scope a query to only include events by application.
     */
    public function scopeByApplication(Builder $query, string $application): Builder
    {
        return $query->where('application', $application);
    }

    /**
     * Scope a query to only include events by bridge ID.
     */
    public function scopeByBridgeId(Builder $query, string $bridgeId): Builder
    {
        return $query->where('bridge_id', $bridgeId);
    }

    /**
     * Scope a query to only include events with errors.
     */
    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Scope a query to only include events by server.
     */
    public function scopeByServer(Builder $query, string $serverId): Builder
    {
        return $query->where('server_id', $serverId);
    }

    /**
     * Get the event category attribute.
     */
    public function getEventCategoryAttribute(): string
    {
        return match ($this->event_name) {
            'DialBegin', 'DialEnd', 'Hangup', 'NewCallerid', 'NewConnectedLine' => 'call',
            'BridgeCreate', 'BridgeDestroy', 'BridgeEnter', 'BridgeLeave' => 'bridge',
            'QueueMemberAdded', 'QueueMemberRemoved', 'QueueMemberPause', 'QueueCallerJoin', 'QueueCallerLeave' => 'queue',
            'AgentConnect', 'AgentComplete', 'AgentLogin', 'AgentLogoff' => 'agent',
            'VarSet', 'UserEvent' => 'variable',
            'MonitorStart', 'MonitorStop' => 'monitoring',
            'DTMFBegin', 'DTMFEnd' => 'dtmf',
            'NewExten', 'NewState' => 'state',
            default => 'other'
        };
    }

    /**
     * Get the formatted duration attribute.
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        $seconds = intval($this->duration_ms / 1000);
        $milliseconds = $this->duration_ms % 1000;

        return sprintf('%d.%03ds', $seconds, $milliseconds);
    }

    /**
     * Get the is call event attribute.
     */
    public function getIsCallEventAttribute(): bool
    {
        return $this->event_category === 'call';
    }

    /**
     * Get the is queue event attribute.
     */
    public function getIsQueueEventAttribute(): bool
    {
        return $this->event_category === 'queue';
    }

    /**
     * Get the is bridge event attribute.
     */
    public function getIsBridgeEventAttribute(): bool
    {
        return $this->event_category === 'bridge';
    }

    /**
     * Set the caller ID number attribute.
     */
    public function setCallerIdNumAttribute($value): void
    {
        $this->attributes['caller_id_num'] = $this->normalizePhoneNumber($value);
    }

    /**
     * Set the connected line number attribute.
     */
    public function setConnectedLineNumAttribute($value): void
    {
        $this->attributes['connected_line_num'] = $this->normalizePhoneNumber($value);
    }

    /**
     * Mark this event as processed.
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'processing_status' => 'processed',
            'processed_at'      => now(),
        ]);
    }

    /**
     * Mark this event as failed with error message.
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'processing_status' => 'failed',
            'error_message'     => $errorMessage,
            'processed_at'      => now(),
        ]);
    }

    /**
     * Mark this event as significant.
     */
    public function markAsSignificant(): bool
    {
        return $this->update(['is_significant' => true]);
    }

    /**
     * Mark this event as needing action.
     */
    public function markAsNeedsAction(): bool
    {
        return $this->update(['needs_action' => true]);
    }

    /**
     * Get event statistics for a given time period.
     */
    public static function getStatistics(Carbon $startDate, Carbon $endDate): array
    {
        $query = static::dateRange($startDate, $endDate);

        return [
            'total_events'       => $query->count(),
            'significant_events' => $query->significant()->count(),
            'processed_events'   => $query->processed()->count(),
            'pending_events'     => $query->pending()->count(),
            'failed_events'      => $query->failed()->count(),
            'call_events'        => $query->callEvents()->count(),
            'queue_events'       => $query->queueEvents()->count(),
            'bridge_events'      => $query->bridgeEvents()->count(),
            'events_with_errors' => $query->withErrors()->count(),
            'events_by_type'     => $query->selectRaw('event_name, COUNT(*) as count')
                                    ->groupBy('event_name')
                                    ->orderBy('count', 'desc')
                                    ->limit(10)
                                    ->pluck('count', 'event_name')
                                    ->toArray(),
        ];
    }

    /**
     * Get event volume by hour for a given date.
     */
    public static function getHourlyVolume(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $events = static::dateRange($startOfDay, $endOfDay)
            ->selectRaw('HOUR(event_timestamp) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyData = array_fill(0, 24, 0);

        foreach ($events as $event) {
            $hourlyData[$event->hour] = $event->count;
        }

        return $hourlyData;
    }

    /**
     * Find events by correlation ID.
     */
    public static function findByCorrelationId(string $correlationId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('correlation_id', $correlationId)
                    ->orderBy('event_timestamp')
                    ->get();
    }

    /**
     * Create a correlation ID for related events.
     */
    public static function generateCorrelationId(): string
    {
        return 'corr_'.uniqid().'_'.time();
    }

    /**
     * Normalize phone number format.
     */
    protected function normalizePhoneNumber(?string $number): ?string
    {
        if (!$number) {
            return null;
        }

        // Remove common prefixes and formatting
        $normalized = preg_replace('/[^\d+]/', '', $number);

        // Handle anonymous/private numbers
        if (in_array(strtolower($number), ['anonymous', 'private', 'unavailable', 'unknown'])) {
            return strtolower($number);
        }

        return $normalized;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \AsteriskPbxManager\Database\Factories\AsteriskEventFactory::new();
    }
}
