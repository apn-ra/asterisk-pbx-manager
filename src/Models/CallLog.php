<?php

namespace AsteriskPbxManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CallLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'asterisk_call_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'channel',
        'unique_id',
        'linked_id',
        'caller_id_num',
        'caller_id_name',
        'connected_to',
        'connected_name',
        'context',
        'extension',
        'priority',
        'direction',
        'call_type',
        'started_at',
        'answered_at',
        'ended_at',
        'ring_duration',
        'talk_duration',
        'total_duration',
        'call_status',
        'hangup_cause',
        'hangup_reason',
        'codec_used',
        'quality_score',
        'quality_metrics',
        'queue_name',
        'queue_wait_time',
        'agent_id',
        'transfer_type',
        'transferred_to',
        'transferred_by',
        'recorded',
        'recording_filename',
        'recording_path',
        'recording_size',
        'account_code',
        'cost',
        'cost_currency',
        'cost_per_minute',
        'metadata',
        'channel_variables',
        'asterisk_server',
        'processed_by',
        'processed_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'processed_at' => 'datetime',
        'ring_duration' => 'integer',
        'talk_duration' => 'integer',
        'total_duration' => 'integer',
        'queue_wait_time' => 'integer',
        'recording_size' => 'integer',
        'quality_score' => 'decimal:2',
        'cost' => 'decimal:4',
        'cost_per_minute' => 'decimal:4',
        'recorded' => 'boolean',
        'quality_metrics' => 'array',
        'metadata' => 'array',
        'channel_variables' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'channel_variables',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'duration_formatted',
        'cost_formatted',
        'is_answered',
        'is_successful',
        'call_outcome'
    ];

    /**
     * Get the events associated with this call log.
     */
    public function events(): HasMany
    {
        return $this->hasMany(AsteriskEvent::class, 'call_log_id');
    }

    /**
     * Get the significant events associated with this call log.
     */
    public function significantEvents(): HasMany
    {
        return $this->hasMany(AsteriskEvent::class, 'call_log_id')
                    ->where('is_significant', true)
                    ->orderBy('event_timestamp');
    }

    /**
     * Get events by type.
     */
    public function eventsByType(string $eventType): HasMany
    {
        return $this->hasMany(AsteriskEvent::class, 'call_log_id')
                    ->where('event_name', $eventType)
                    ->orderBy('event_timestamp');
    }

    /**
     * Scope a query to only include calls from a specific date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include calls by direction.
     */
    public function scopeByDirection(Builder $query, string $direction): Builder
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope a query to only include calls by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('call_status', $status);
    }

    /**
     * Scope a query to only include answered calls.
     */
    public function scopeAnswered(Builder $query): Builder
    {
        return $query->whereNotNull('answered_at')
                    ->whereIn('call_status', ['connected']);
    }

    /**
     * Scope a query to only include unanswered calls.
     */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereNull('answered_at')
                    ->orWhereIn('call_status', ['missed', 'no_answer', 'busy']);
    }

    /**
     * Scope a query to only include calls longer than specified duration.
     */
    public function scopeLongerThan(Builder $query, int $seconds): Builder
    {
        return $query->where('total_duration', '>', $seconds);
    }

    /**
     * Scope a query to only include calls shorter than specified duration.
     */
    public function scopeShorterThan(Builder $query, int $seconds): Builder
    {
        return $query->where('total_duration', '<', $seconds);
    }

    /**
     * Scope a query to only include calls involving a specific number.
     */
    public function scopeInvolvingNumber(Builder $query, string $number): Builder
    {
        return $query->where(function ($q) use ($number) {
            $q->where('caller_id_num', 'like', "%{$number}%")
              ->orWhere('connected_to', 'like', "%{$number}%");
        });
    }

    /**
     * Scope a query to only include queue calls.
     */
    public function scopeQueueCalls(Builder $query, ?string $queueName = null): Builder
    {
        $query = $query->whereNotNull('queue_name');
        
        if ($queueName) {
            $query->where('queue_name', $queueName);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include recorded calls.
     */
    public function scopeRecorded(Builder $query): Builder
    {
        return $query->where('recorded', true);
    }

    /**
     * Scope a query to only include calls with quality issues.
     */
    public function scopeQualityIssues(Builder $query, float $threshold = 3.0): Builder
    {
        return $query->where('quality_score', '<', $threshold)
                    ->whereNotNull('quality_score');
    }

    /**
     * Scope a query to only include calls by agent.
     */
    public function scopeByAgent(Builder $query, string $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Get the formatted duration attribute.
     */
    public function getDurationFormattedAttribute(): string
    {
        if (!$this->total_duration) {
            return '00:00';
        }

        $hours = intval($this->total_duration / 3600);
        $minutes = intval(($this->total_duration % 3600) / 60);
        $seconds = $this->total_duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get the formatted cost attribute.
     */
    public function getCostFormattedAttribute(): string
    {
        if (!$this->cost) {
            return '0.00';
        }

        $currency = $this->cost_currency ?? 'USD';
        $symbol = $this->getCurrencySymbol($currency);

        return $symbol . number_format($this->cost, 2);
    }

    /**
     * Get the is answered attribute.
     */
    public function getIsAnsweredAttribute(): bool
    {
        return !is_null($this->answered_at) && $this->call_status === 'connected';
    }

    /**
     * Get the is successful attribute.
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->is_answered && !is_null($this->talk_duration) && $this->talk_duration > 0;
    }

    /**
     * Get the call outcome attribute.
     */
    public function getCallOutcomeAttribute(): string
    {
        if ($this->is_successful) {
            return 'successful';
        }

        if ($this->is_answered) {
            return 'answered_no_talk';
        }

        return match($this->call_status) {
            'busy' => 'busy',
            'no_answer' => 'no_answer',
            'missed' => 'missed',
            'failed' => 'failed',
            'abandoned' => 'abandoned',
            default => 'unknown'
        };
    }

    /**
     * Set the caller ID number attribute.
     */
    public function setCallerIdNumAttribute($value): void
    {
        $this->attributes['caller_id_num'] = $this->normalizePhoneNumber($value);
    }

    /**
     * Set the connected to attribute.
     */
    public function setConnectedToAttribute($value): void
    {
        $this->attributes['connected_to'] = $this->normalizePhoneNumber($value);
    }

    /**
     * Calculate call statistics for a given time period.
     */
    public static function getStatistics(Carbon $startDate, Carbon $endDate): array
    {
        $query = static::dateRange($startDate, $endDate);

        return [
            'total_calls' => $query->count(),
            'answered_calls' => $query->answered()->count(),
            'unanswered_calls' => $query->unanswered()->count(),
            'total_talk_time' => $query->sum('talk_duration'),
            'average_talk_time' => $query->answered()->avg('talk_duration'),
            'total_cost' => $query->sum('cost'),
            'inbound_calls' => $query->byDirection('inbound')->count(),
            'outbound_calls' => $query->byDirection('outbound')->count(),
            'internal_calls' => $query->byDirection('internal')->count(),
            'queue_calls' => $query->queueCalls()->count(),
            'recorded_calls' => $query->recorded()->count(),
        ];
    }

    /**
     * Get call volume by hour for a given date.
     */
    public static function getHourlyVolume(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $calls = static::dateRange($startOfDay, $endOfDay)
            ->selectRaw('HOUR(started_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyData = array_fill(0, 24, 0);
        
        foreach ($calls as $call) {
            $hourlyData[$call->hour] = $call->count;
        }

        return $hourlyData;
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
     * Get currency symbol for a given currency code.
     */
    protected function getCurrencySymbol(string $currency): string
    {
        return match(strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            default => $currency . ' '
        };
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \AsteriskPbxManager\Database\Factories\CallLogFactory::new();
    }
}