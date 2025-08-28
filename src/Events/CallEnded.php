<?php

namespace AsteriskPbxManager\Events;

use Illuminate\Broadcasting\Channel;
use PAMI\Message\Event\EventMessage;

/**
 * Event fired when a call ends/hangs up.
 */
class CallEnded extends AsteriskEvent
{
    /**
     * The hangup cause.
     *
     * @var string|null
     */
    public ?string $cause = null;

    /**
     * The hangup cause text.
     *
     * @var string|null
     */
    public ?string $causeText = null;

    /**
     * Call duration in seconds.
     *
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * Billable seconds.
     *
     * @var int|null
     */
    public ?int $billableSeconds = null;

    /**
     * Account code.
     *
     * @var string|null
     */
    public ?string $accountCode = null;

    /**
     * Create a new call ended event instance.
     *
     * @param EventMessage|null $event
     */
    public function __construct(?EventMessage $event = null)
    {
        parent::__construct($event);
        
        if ($event) {
            $this->extractHangupData($event);
        }
    }

    /**
     * Extract hangup-specific data from the AMI event.
     *
     * @param EventMessage $event
     */
    protected function extractHangupData(EventMessage $event): void
    {
        // Extract hangup cause information
        $this->cause = $event->getKey('Cause');
        $this->causeText = $event->getKey('Cause-txt') ?? $this->getHangupCauseText($this->cause);
        
        // Extract duration and billing information
        $this->duration = $this->extractDuration($event);
        $this->billableSeconds = $this->extractBillableSeconds($event);
        $this->accountCode = $event->getKey('AccountCode');
        
        // Add hangup-specific data to the data array
        $this->data = array_merge($this->data, [
            'cause' => $this->cause,
            'cause_text' => $this->causeText,
            'duration' => $this->duration,
            'billable_seconds' => $this->billableSeconds,
            'account_code' => $this->accountCode,
            'end_time' => $event->getKey('EndTime') ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Extract call duration from event data.
     *
     * @param EventMessage $event
     * @return int|null
     */
    protected function extractDuration(EventMessage $event): ?int
    {
        // Try different duration fields
        $duration = $event->getKey('Duration') ?? 
                   $event->getKey('duration') ?? 
                   $event->getKey('BillableSeconds');
        
        return $duration ? (int) $duration : null;
    }

    /**
     * Extract billable seconds from event data.
     *
     * @param EventMessage $event
     * @return int|null
     */
    protected function extractBillableSeconds(EventMessage $event): ?int
    {
        $billable = $event->getKey('BillableSeconds') ?? 
                   $event->getKey('billableseconds') ?? 
                   $event->getKey('Duration');
        
        return $billable ? (int) $billable : null;
    }

    /**
     * Get hangup cause text from cause code.
     *
     * @param string|null $cause
     * @return string|null
     */
    protected function getHangupCauseText(?string $cause): ?string
    {
        if (!$cause) {
            return null;
        }

        $causeCodes = [
            '0' => 'Unknown',
            '1' => 'Unassigned number',
            '2' => 'No route to destination',
            '3' => 'No route to destination',
            '16' => 'Normal call clearing',
            '17' => 'User busy',
            '18' => 'No user responding',
            '19' => 'No answer from user',
            '20' => 'Subscriber absent',
            '21' => 'Call rejected',
            '22' => 'Number changed',
            '27' => 'Destination out of order',
            '28' => 'Invalid number format',
            '29' => 'Facility rejected',
            '31' => 'Normal, unspecified',
            '34' => 'No circuit/channel available',
            '38' => 'Network out of order',
            '41' => 'Temporary failure',
            '42' => 'Switching equipment congestion',
            '43' => 'Access information discarded',
            '44' => 'Requested channel not available',
            '50' => 'Requested facility not subscribed',
            '57' => 'Bearer capability not authorized',
            '58' => 'Bearer capability not available',
            '65' => 'Bearer capability not implemented',
            '66' => 'Channel type not implemented',
            '69' => 'Requested facility not implemented',
            '79' => 'Service or option not implemented',
            '87' => 'Called party not member of CUG',
            '88' => 'Incompatible destination',
            '95' => 'Invalid message',
            '96' => 'Mandatory information element is missing',
            '97' => 'Message type non-existent',
            '98' => 'Wrong message',
            '99' => 'Information element non-existent',
            '100' => 'Invalid information element contents',
            '101' => 'Message not compatible with call state',
            '102' => 'Recovery on timer expiry',
            '111' => 'Protocol error',
            '127' => 'Internetworking',
        ];

        return $causeCodes[$cause] ?? "Unknown cause ({$cause})";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        $channelPrefix = config('asterisk-pbx-manager.broadcasting.channel_prefix', 'asterisk');
        $isPrivate = config('asterisk-pbx-manager.broadcasting.private_channels', false);

        $channels = [
            "{$channelPrefix}.calls",
            "{$channelPrefix}.events",
        ];

        // Add channel-specific broadcasting if channel is available
        if ($this->getChannel()) {
            $channels[] = "{$channelPrefix}.calls.{$this->getUniqueId()}";
        }

        return $isPrivate 
            ? array_map(fn($ch) => new \Illuminate\Broadcasting\PrivateChannel($ch), $channels)
            : array_map(fn($ch) => new Channel($ch), $channels);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'call.ended';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'call_ended',
            'timestamp' => $this->timestamp,
            'channel' => $this->getChannel(),
            'unique_id' => $this->getUniqueId(),
            'caller_id_num' => $this->getCallerIdNum(),
            'caller_id_name' => $this->getCallerIdName(),
            'cause' => $this->cause,
            'cause_text' => $this->causeText,
            'duration' => $this->duration,
            'billable_seconds' => $this->billableSeconds,
            'account_code' => $this->accountCode,
            'extension' => $this->getExtension(),
            'context' => $this->getContext(),
        ];
    }

    /**
     * Get the hangup cause.
     *
     * @return string|null
     */
    public function getCause(): ?string
    {
        return $this->cause;
    }

    /**
     * Get the hangup cause text.
     *
     * @return string|null
     */
    public function getCauseText(): ?string
    {
        return $this->causeText;
    }

    /**
     * Get the call duration in seconds.
     *
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->duration;
    }

    /**
     * Get the billable seconds.
     *
     * @return int|null
     */
    public function getBillableSeconds(): ?int
    {
        return $this->billableSeconds;
    }

    /**
     * Get the account code.
     *
     * @return string|null
     */
    public function getAccountCode(): ?string
    {
        return $this->accountCode;
    }

    /**
     * Check if the call was answered (duration > 0).
     *
     * @return bool
     */
    public function wasAnswered(): bool
    {
        return $this->duration > 0 || $this->billableSeconds > 0;
    }

    /**
     * Check if the call ended normally.
     *
     * @return bool
     */
    public function wasNormalHangup(): bool
    {
        return in_array($this->cause, ['16', '31']); // Normal call clearing or Normal, unspecified
    }

    /**
     * Check if the call was busy.
     *
     * @return bool
     */
    public function wasBusy(): bool
    {
        return $this->cause === '17'; // User busy
    }

    /**
     * Check if there was no answer.
     *
     * @return bool
     */
    public function wasNoAnswer(): bool
    {
        return in_array($this->cause, ['18', '19']); // No user responding or No answer from user
    }

    /**
     * Check if the call was rejected.
     *
     * @return bool
     */
    public function wasRejected(): bool
    {
        return $this->cause === '21'; // Call rejected
    }

    /**
     * Get formatted duration as H:MM:SS.
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
        if ($this->duration === null) {
            return '00:00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}