<?php

declare(strict_types=1);

namespace AsteriskPbxManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for audit logs.
 *
 * This model represents audit log entries that track all AMI actions
 * performed through the Asterisk PBX Manager package.
 *
 * @property int         $id
 * @property string      $action_type
 * @property string      $action_name
 * @property int|null    $user_id
 * @property string|null $user_name
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property Carbon      $timestamp
 * @property bool        $success
 * @property float       $execution_time
 * @property array|null  $request_data
 * @property array|null  $response_data
 * @property array|null  $additional_context
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'action_type',
        'action_name',
        'user_id',
        'user_name',
        'ip_address',
        'user_agent',
        'session_id',
        'timestamp',
        'success',
        'execution_time',
        'request_data',
        'response_data',
        'additional_context',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id'            => 'integer',
        'timestamp'          => 'datetime',
        'success'            => 'boolean',
        'execution_time'     => 'decimal:6',
        'request_data'       => 'array',
        'response_data'      => 'array',
        'additional_context' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        // Keep all fields visible by default for audit purposes
    ];

    /**
     * Get the user associated with the audit log.
     *
     * Note: This relationship is intentionally flexible and doesn't enforce
     * foreign key constraints to allow logging even when user records are deleted.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|null
     */
    public function user()
    {
        // Check if the User model exists in the application
        if (class_exists('App\Models\User')) {
            return $this->belongsTo('App\Models\User');
        }

        return null;
    }

    /**
     * Scope a query to only include successful actions.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    /**
     * Scope a query to only include failed actions.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    /**
     * Scope a query to only include AMI actions (excluding connection events).
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeAmiActions(Builder $query): Builder
    {
        return $query->where('action_type', 'ami_action');
    }

    /**
     * Scope a query to only include connection events.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeConnectionEvents(Builder $query): Builder
    {
        return $query->where('action_type', 'connection');
    }

    /**
     * Scope a query to filter by specific action name.
     *
     * @param Builder $query
     * @param string  $actionName
     *
     * @return Builder
     */
    public function scopeByAction(Builder $query, string $actionName): Builder
    {
        return $query->where('action_name', $actionName);
    }

    /**
     * Scope a query to filter by user ID.
     *
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by IP address.
     *
     * @param Builder $query
     * @param string  $ipAddress
     *
     * @return Builder
     */
    public function scopeByIpAddress(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope a query to filter records within a date range.
     *
     * @param Builder $query
     * @param Carbon  $startDate
     * @param Carbon  $endDate
     *
     * @return Builder
     */
    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter records from the last N days.
     *
     * @param Builder $query
     * @param int     $days
     *
     * @return Builder
     */
    public function scopeLastDays(Builder $query, int $days): Builder
    {
        return $query->where('timestamp', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope a query to order by most recent first.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('timestamp', 'desc');
    }

    /**
     * Scope a query to order by oldest first.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy('timestamp', 'asc');
    }

    /**
     * Get the execution time in milliseconds.
     *
     * @return float
     */
    public function getExecutionTimeInMilliseconds(): float
    {
        return $this->execution_time * 1000;
    }

    /**
     * Get a human-readable description of the audit log entry.
     *
     * @return string
     */
    public function getDescription(): string
    {
        $user = $this->user_name ? "by {$this->user_name}" : 'by anonymous user';
        $status = $this->success ? 'succeeded' : 'failed';
        $time = number_format($this->getExecutionTimeInMilliseconds(), 2);

        return "Action '{$this->action_name}' {$status} in {$time}ms {$user} from {$this->ip_address}";
    }

    /**
     * Get statistics for audit logs.
     *
     * @param Builder|null $query Optional query builder to apply filters
     *
     * @return array
     */
    public static function getStatistics(?Builder $query = null): array
    {
        if ($query === null) {
            $query = static::query();
        }

        $total = $query->count();
        $successful = (clone $query)->successful()->count();
        $failed = (clone $query)->failed()->count();
        $avgExecutionTime = (clone $query)->avg('execution_time') ?: 0.0;

        return [
            'total_actions'             => $total,
            'successful_actions'        => $successful,
            'failed_actions'            => $failed,
            'success_rate'              => $total > 0 ? ($successful / $total) * 100 : 0.0,
            'failure_rate'              => $total > 0 ? ($failed / $total) * 100 : 0.0,
            'average_execution_time'    => round($avgExecutionTime, 6),
            'average_execution_time_ms' => round($avgExecutionTime * 1000, 2),
        ];
    }

    /**
     * Get the most common actions from audit logs.
     *
     * @param int          $limit
     * @param Builder|null $query Optional query builder to apply filters
     *
     * @return array
     */
    public static function getMostCommonActions(int $limit = 10, ?Builder $query = null): array
    {
        if ($query === null) {
            $query = static::query();
        }

        return $query->select('action_name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(execution_time) as avg_execution_time')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failure_count')
            ->groupBy('action_name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item->success_rate = $item->count > 0 ? ($item->success_count / $item->count) * 100 : 0.0;
                $item->avg_execution_time_ms = round($item->avg_execution_time * 1000, 2);

                return $item;
            })
            ->toArray();
    }

    /**
     * Clean up old audit log entries.
     *
     * @param int $daysToKeep Number of days to keep audit logs
     *
     * @return int Number of deleted records
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);

        return static::where('timestamp', '<', $cutoffDate)->delete();
    }
}
