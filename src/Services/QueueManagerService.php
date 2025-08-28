<?php

namespace AsteriskPbxManager\Services;

use PAMI\Message\Action\QueueAddAction;
use PAMI\Message\Action\QueueRemoveAction;
use PAMI\Message\Action\QueuePauseAction;
use PAMI\Message\Action\QueuesAction;
use PAMI\Message\Action\QueueStatusAction;
use PAMI\Message\Action\QueueSummaryAction;
use Illuminate\Support\Facades\Log;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

/**
 * Service for managing Asterisk PBX queue operations.
 * 
 * This service provides comprehensive queue management functionality including
 * adding/removing members, pausing members, and retrieving queue status and statistics.
 */
class QueueManagerService
{
    /**
     * The Asterisk Manager Service instance.
     *
     * @var AsteriskManagerService
     */
    protected AsteriskManagerService $asteriskManager;

    /**
     * Create a new queue manager service instance.
     *
     * @param AsteriskManagerService $asteriskManager The Asterisk Manager Service instance
     */
    public function __construct(AsteriskManagerService $asteriskManager)
    {
        $this->asteriskManager = $asteriskManager;
    }

    /**
     * Add a member to a queue.
     *
     * @param string $queue The queue name to add the member to
     * @param string $interface The interface (channel) to add as member (e.g., SIP/1001)
     * @param string|null $memberName Optional member name for identification
     * @param int $penalty Member penalty (0 = highest priority)
     * @return bool True if member was added successfully, false otherwise
     * @throws ActionExecutionException If the add member action fails
     */
    public function addMember(string $queue, string $interface, ?string $memberName = null, int $penalty = 0): bool
    {
        $this->validateQueueName($queue);
        $this->validateInterface($interface);

        try {
            $action = new QueueAddAction($queue, $interface);
            
            if ($memberName) {
                $action->setMemberName($memberName);
            }
            
            if ($penalty > 0) {
                $action->setPenalty($penalty);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Queue member added successfully', [
                    'queue' => $queue,
                    'interface' => $interface,
                    'member_name' => $memberName,
                    'penalty' => $penalty
                ]);
                return true;
            } else {
                Log::warning('Failed to add queue member', [
                    'queue' => $queue,
                    'interface' => $interface,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while adding queue member: ' . $e->getMessage(), [
                'queue' => $queue,
                'interface' => $interface,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to add member to queue: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Remove a member from a queue.
     *
     * @param string $queue The queue name to remove the member from
     * @param string $interface The interface (channel) to remove from queue
     * @return bool True if member was removed successfully, false otherwise
     * @throws ActionExecutionException If the remove member action fails
     */
    public function removeMember(string $queue, string $interface): bool
    {
        $this->validateQueueName($queue);
        $this->validateInterface($interface);

        try {
            $action = new QueueRemoveAction($queue, $interface);
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Queue member removed successfully', [
                    'queue' => $queue,
                    'interface' => $interface
                ]);
                return true;
            } else {
                Log::warning('Failed to remove queue member', [
                    'queue' => $queue,
                    'interface' => $interface,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while removing queue member: ' . $e->getMessage(), [
                'queue' => $queue,
                'interface' => $interface,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to remove member from queue: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Pause or unpause a queue member.
     */
    public function pauseMember(string $queue, string $interface, bool $paused = true, ?string $reason = null): bool
    {
        $this->validateQueueName($queue);
        $this->validateInterface($interface);

        try {
            $action = new QueuePauseAction($interface, $paused);
            $action->setQueue($queue);
            
            if ($reason) {
                $action->setReason($reason);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Queue member pause status updated', [
                    'queue' => $queue,
                    'interface' => $interface,
                    'paused' => $paused,
                    'reason' => $reason
                ]);
                return true;
            } else {
                Log::warning('Failed to update queue member pause status', [
                    'queue' => $queue,
                    'interface' => $interface,
                    'paused' => $paused,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating queue member pause status: ' . $e->getMessage(), [
                'queue' => $queue,
                'interface' => $interface,
                'paused' => $paused,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to pause/unpause queue member: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get status information for all queues or a specific queue.
     */
    public function getQueueStatus(?string $queue = null): array
    {
        if ($queue) {
            $this->validateQueueName($queue);
        }

        try {
            $action = new QueueStatusAction();
            if ($queue) {
                $action->setQueue($queue);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $queueData = $this->parseQueueStatusResponse($response);
                
                Log::info('Queue status retrieved successfully', [
                    'queue' => $queue ?? 'all',
                    'queue_count' => count($queueData)
                ]);
                
                return $queueData;
            } else {
                Log::warning('Failed to get queue status', [
                    'queue' => $queue ?? 'all',
                    'response' => $response->getMessage()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception while getting queue status: ' . $e->getMessage(), [
                'queue' => $queue ?? 'all',
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get queue status: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get queue summary information.
     */
    public function getQueueSummary(?string $queue = null): array
    {
        if ($queue) {
            $this->validateQueueName($queue);
        }

        try {
            $action = new QueueSummaryAction();
            if ($queue) {
                $action->setQueue($queue);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $summaryData = $this->parseQueueSummaryResponse($response);
                
                Log::info('Queue summary retrieved successfully', [
                    'queue' => $queue ?? 'all',
                    'queue_count' => count($summaryData)
                ]);
                
                return $summaryData;
            } else {
                Log::warning('Failed to get queue summary', [
                    'queue' => $queue ?? 'all',
                    'response' => $response->getMessage()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception while getting queue summary: ' . $e->getMessage(), [
                'queue' => $queue ?? 'all',
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get queue summary: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get detailed information about queue members.
     */
    public function getQueueMembers(string $queue): array
    {
        $this->validateQueueName($queue);

        try {
            $queueStatus = $this->getQueueStatus($queue);
            
            if (empty($queueStatus) || !isset($queueStatus[$queue])) {
                return [];
            }
            
            return $queueStatus[$queue]['members'] ?? [];
        } catch (\Exception $e) {
            Log::error('Exception while getting queue members: ' . $e->getMessage(), [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get queue members: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if a member exists in a queue.
     */
    public function memberExists(string $queue, string $interface): bool
    {
        try {
            $members = $this->getQueueMembers($queue);
            
            foreach ($members as $member) {
                if ($member['interface'] === $interface) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Exception while checking member existence: ' . $e->getMessage(), [
                'queue' => $queue,
                'interface' => $interface,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate queue name format.
     */
    protected function validateQueueName(string $queue): void
    {
        if (empty($queue) || !preg_match('/^[a-zA-Z0-9_-]+$/', $queue)) {
            throw new \InvalidArgumentException('Invalid queue name format. Queue name should contain only letters, numbers, underscores, and hyphens.');
        }
    }

    /**
     * Validate interface format.
     */
    protected function validateInterface(string $interface): void
    {
        if (empty($interface)) {
            throw new \InvalidArgumentException('Interface cannot be empty.');
        }
        
        // Basic interface format validation (SIP/1234, PJSIP/user, etc.)
        if (!preg_match('/^[A-Z]+\/[a-zA-Z0-9_@.-]+$/', $interface)) {
            throw new \InvalidArgumentException('Invalid interface format. Expected format: TECHNOLOGY/identifier (e.g., SIP/1234, PJSIP/user@domain).');
        }
    }

    /**
     * Parse queue status response into structured data.
     */
    protected function parseQueueStatusResponse($response): array
    {
        // This method would parse the PAMI response into structured queue data
        // Implementation depends on the specific response format from PAMI
        $queueData = [];
        
        // Placeholder implementation - would need to be adapted based on actual PAMI response format
        $events = $response->getEvents() ?? [];
        
        foreach ($events as $event) {
            if ($event->getName() === 'QueueParams') {
                $queueName = $event->getKey('Queue');
                $queueData[$queueName] = [
                    'name' => $queueName,
                    'strategy' => $event->getKey('Strategy'),
                    'calls' => (int) $event->getKey('Calls'),
                    'hold_time' => (int) $event->getKey('Holdtime'),
                    'talk_time' => (int) $event->getKey('Talktime'),
                    'completed' => (int) $event->getKey('Completed'),
                    'abandoned' => (int) $event->getKey('Abandoned'),
                    'members' => []
                ];
            } elseif ($event->getName() === 'QueueMember') {
                $queueName = $event->getKey('Queue');
                if (isset($queueData[$queueName])) {
                    $queueData[$queueName]['members'][] = [
                        'interface' => $event->getKey('Location'),
                        'member_name' => $event->getKey('MemberName'),
                        'state_interface' => $event->getKey('StateInterface'),
                        'status' => $event->getKey('Status'),
                        'paused' => (bool) $event->getKey('Paused'),
                        'penalty' => (int) $event->getKey('Penalty'),
                        'calls_taken' => (int) $event->getKey('CallsTaken'),
                        'last_call' => (int) $event->getKey('LastCall')
                    ];
                }
            }
        }
        
        return $queueData;
    }

    /**
     * Parse queue summary response into structured data.
     */
    protected function parseQueueSummaryResponse($response): array
    {
        // This method would parse the PAMI summary response
        // Implementation depends on the specific response format from PAMI
        $summaryData = [];
        
        // Placeholder implementation
        $events = $response->getEvents() ?? [];
        
        foreach ($events as $event) {
            if ($event->getName() === 'QueueSummary') {
                $queueName = $event->getKey('Queue');
                $summaryData[$queueName] = [
                    'name' => $queueName,
                    'logged_in' => (int) $event->getKey('LoggedIn'),
                    'available' => (int) $event->getKey('Available'),
                    'callers' => (int) $event->getKey('Callers'),
                    'hold_time' => (int) $event->getKey('Holdtime'),
                    'talk_time' => (int) $event->getKey('Talktime'),
                    'longest_hold_time' => (int) $event->getKey('LongestHoldTime')
                ];
            }
        }
        
        return $summaryData;
    }
}