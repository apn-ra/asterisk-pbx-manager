<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Events\AsteriskEvent;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use Illuminate\Support\Facades\Log;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Action\CoreStatusAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Response\ResponseMessage;

/**
 * Main service class for Asterisk Manager Interface operations.
 */
class AsteriskManagerService
{
    /**
     * The PAMI client instance.
     *
     * @var ClientImpl
     */
    protected ClientImpl $client;

    /**
     * Connection status flag.
     *
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Reconnection attempts counter.
     *
     * @var int
     */
    protected int $reconnectionAttempts = 0;

    /**
     * Maximum reconnection attempts.
     *
     * @var int
     */
    protected int $maxReconnectionAttempts;

    /**
     * Reconnection delay in seconds.
     *
     * @var int
     */
    protected int $reconnectionDelay;

    /**
     * Event listeners registry.
     *
     * @var array
     */
    protected array $eventListeners = [];

    /**
     * AMI input sanitizer instance.
     *
     * @var AmiInputSanitizer
     */
    protected AmiInputSanitizer $sanitizer;

    /**
     * Audit logging service instance.
     *
     * @var AuditLoggingService
     */
    protected AuditLoggingService $auditLogger;

    /**
     * Create a new Asterisk Manager Service instance.
     *
     * @param ClientImpl          $client
     * @param AmiInputSanitizer   $sanitizer
     * @param AuditLoggingService $auditLogger
     */
    public function __construct(ClientImpl $client, AmiInputSanitizer $sanitizer, AuditLoggingService $auditLogger)
    {
        $this->client = $client;
        $this->sanitizer = $sanitizer;
        $this->auditLogger = $auditLogger;
        $this->maxReconnectionAttempts = config('asterisk-pbx-manager.reconnection.max_attempts', 3);
        $this->reconnectionDelay = config('asterisk-pbx-manager.reconnection.delay_seconds', 5);

        $this->setupEventListeners();
    }

    /**
     * Connect to Asterisk Manager Interface.
     *
     * @throws AsteriskConnectionException
     *
     * @return bool
     */
    public function connect(): bool
    {
        $startTime = microtime(true);

        try {
            $this->client->open();
            $this->connected = true;
            $this->reconnectionAttempts = 0;

            $executionTime = microtime(true) - $startTime;

            // Log audit event for successful connection
            $this->auditLogger->logConnection('connect', true, [
                'host'     => config('asterisk-pbx-manager.connection.host'),
                'port'     => config('asterisk-pbx-manager.connection.port'),
                'attempts' => $this->reconnectionAttempts + 1,
            ]);

            $this->logInfo('Connected to Asterisk Manager Interface', [
                'attempts'       => $this->reconnectionAttempts + 1,
                'execution_time' => $executionTime,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->connected = false;
            $executionTime = microtime(true) - $startTime;

            // Log audit event for failed connection
            $this->auditLogger->logConnection('connect', false, [
                'host'     => config('asterisk-pbx-manager.connection.host'),
                'port'     => config('asterisk-pbx-manager.connection.port'),
                'attempts' => $this->reconnectionAttempts + 1,
                'error'    => $e->getMessage(),
            ]);

            $this->logError('Failed to connect to Asterisk AMI', [
                'error'          => $e->getMessage(),
                'attempts'       => $this->reconnectionAttempts + 1,
                'execution_time' => $executionTime,
            ]);

            throw AsteriskConnectionException::networkError(
                config('asterisk-pbx-manager.connection.host', 'localhost'),
                config('asterisk-pbx-manager.connection.port', 5038),
                $e->getMessage()
            );
        }
    }

    /**
     * Disconnect from Asterisk Manager Interface.
     *
     * @return bool
     */
    public function disconnect(): bool
    {
        $startTime = microtime(true);

        try {
            if ($this->connected) {
                $this->client->close();
                $this->connected = false;

                $executionTime = microtime(true) - $startTime;

                // Log audit event for successful disconnection
                $this->auditLogger->logConnection('disconnect', true, [
                    'host' => config('asterisk-pbx-manager.connection.host'),
                    'port' => config('asterisk-pbx-manager.connection.port'),
                ]);

                $this->logInfo('Disconnected from Asterisk Manager Interface', [
                    'execution_time' => $executionTime,
                ]);
            } else {
                // Log audit event for disconnect attempt when not connected
                $this->auditLogger->logConnection('disconnect', true, [
                    'host' => config('asterisk-pbx-manager.connection.host'),
                    'port' => config('asterisk-pbx-manager.connection.port'),
                    'note' => 'Already disconnected',
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            // Log audit event for failed disconnection
            $this->auditLogger->logConnection('disconnect', false, [
                'host'  => config('asterisk-pbx-manager.connection.host'),
                'port'  => config('asterisk-pbx-manager.connection.port'),
                'error' => $e->getMessage(),
            ]);

            $this->logError('Error during disconnection', [
                'error'          => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            return false;
        }
    }

    /**
     * Check if connected to AMI.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Attempt to reconnect to AMI.
     *
     * @return bool
     */
    public function reconnect(): bool
    {
        if (!config('asterisk-pbx-manager.reconnection.enabled', true)) {
            return false;
        }

        if ($this->reconnectionAttempts >= $this->maxReconnectionAttempts) {
            $this->logError('Max reconnection attempts reached', [
                'max_attempts' => $this->maxReconnectionAttempts,
            ]);

            return false;
        }

        $this->reconnectionAttempts++;

        $this->logInfo('Attempting to reconnect to AMI', [
            'attempt'      => $this->reconnectionAttempts,
            'max_attempts' => $this->maxReconnectionAttempts,
        ]);

        // Wait before reconnecting
        if ($this->reconnectionDelay > 0) {
            sleep($this->reconnectionDelay);
        }

        try {
            return $this->connect();
        } catch (AsteriskConnectionException $e) {
            // If this was the last attempt, re-throw the exception
            if ($this->reconnectionAttempts >= $this->maxReconnectionAttempts) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Send an AMI action and return the response.
     *
     * @param ActionMessage $action
     *
     * @throws AsteriskConnectionException
     * @throws ActionExecutionException
     *
     * @return ResponseMessage
     */
    public function send(ActionMessage $action): ResponseMessage
    {
        $startTime = microtime(true);
        $response = null;

        if (!$this->isConnected()) {
            if (!$this->reconnect()) {
                throw new AsteriskConnectionException('Not connected to AMI and reconnection failed');
            }
        }

        try {
            $this->logInfo('Sending AMI action', [
                'action'    => $action->getAction(),
                'action_id' => $action->getActionId(),
            ]);

            $response = $this->client->send($action);
            $executionTime = microtime(true) - $startTime;

            if (!$response->isSuccess()) {
                // Log audit event for failed action (response received but indicates failure)
                $this->auditLogger->logAction($action, $response, $executionTime, [
                    'failure_reason'   => 'action_failed',
                    'response_message' => $response->getMessage(),
                ]);

                throw ActionExecutionException::actionFailed($action, $response);
            }

            // Log audit event for successful action
            $this->auditLogger->logAction($action, $response, $executionTime, [
                'success'          => true,
                'response_message' => $response->getMessage(),
            ]);

            $this->logInfo('AMI action completed successfully', [
                'action'           => $action->getAction(),
                'action_id'        => $action->getActionId(),
                'response_message' => $response->getMessage(),
                'execution_time'   => $executionTime,
            ]);

            return $response;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            // Log audit event for exception during action execution
            $this->auditLogger->logAction($action, $response, $executionTime, [
                'failure_reason' => 'exception',
                'error_type'     => get_class($e),
                'error_message'  => $e->getMessage(),
            ]);

            if ($e instanceof ActionExecutionException) {
                throw $e;
            }

            $this->logError('Error sending AMI action', [
                'action'         => $action->getAction(),
                'error'          => $e->getMessage(),
                'execution_time' => $executionTime,
            ]);

            throw new ActionExecutionException(
                "Failed to send AMI action '{$action->getAction()}': {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Originate a call.
     *
     * @param string $channel
     * @param string $extension
     * @param string $context
     * @param int    $priority
     * @param int    $timeout
     *
     * @throws AsteriskConnectionException
     * @throws ActionExecutionException
     *
     * @return bool
     */
    public function originateCall(
        string $channel,
        string $extension,
        ?string $context = null,
        int $priority = 1,
        int $timeout = 30000
    ): bool {
        // Sanitize inputs for AMI security
        $channel = $this->sanitizer->sanitizeChannel($channel);
        $extension = $this->sanitizer->sanitizeExtension($extension);

        $context = $context ?: config('asterisk-pbx-manager.queues.default_context', 'default');
        if ($context) {
            $context = $this->sanitizer->sanitizeContext($context);
        }
        $priority = $priority ?: config('asterisk-pbx-manager.queues.default_priority', 1);

        $action = new OriginateAction($channel);
        $action->setExtension($extension);
        $action->setContext($context);
        $action->setPriority($priority);
        $action->setTimeout($timeout);

        try {
            $response = $this->send($action);

            return $response->isSuccess();
        } catch (ActionExecutionException $e) {
            $this->logError('Failed to originate call', [
                'channel'   => $channel,
                'extension' => $extension,
                'context'   => $context,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Hangup a call.
     *
     * @param string $channel
     *
     * @throws AsteriskConnectionException
     * @throws ActionExecutionException
     *
     * @return bool
     */
    public function hangupCall(string $channel): bool
    {
        // Sanitize input for AMI security
        $channel = $this->sanitizer->sanitizeChannel($channel);

        $action = new HangupAction($channel);

        try {
            $response = $this->send($action);

            return $response->isSuccess();
        } catch (ActionExecutionException $e) {
            $this->logError('Failed to hangup call', [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get Asterisk core status.
     *
     * @throws AsteriskConnectionException
     * @throws ActionExecutionException
     *
     * @return array
     */
    public function getStatus(): array
    {
        $action = new CoreStatusAction();

        try {
            $response = $this->send($action);

            return [
                'success' => $response->isSuccess(),
                'message' => $response->getMessage(),
                'data'    => $response->getKeys(),
            ];
        } catch (ActionExecutionException $e) {
            $this->logError('Failed to get core status', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Setup event listeners for PAMI client.
     */
    protected function setupEventListeners(): void
    {
        if (!config('asterisk-pbx-manager.events.enabled', true)) {
            return;
        }

        $this->client->registerEventListener(function ($event) {
            $this->handleEvent($event);
        });
    }

    /**
     * Handle incoming AMI events.
     *
     * @param mixed $event
     */
    protected function handleEvent($event): void
    {
        try {
            $this->logInfo('Received AMI event', [
                'event' => $event->getEventName() ?? 'Unknown',
            ]);

            // Fire Laravel event
            event(new AsteriskEvent($event));

            // Call registered event listeners
            foreach ($this->eventListeners as $listener) {
                if (is_callable($listener)) {
                    $listener($event);
                }
            }
        } catch (\Exception $e) {
            $this->logError('Error handling AMI event', [
                'event' => $event->getEventName() ?? 'Unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register an event listener.
     *
     * @param callable $listener
     *
     * @return $this
     */
    public function addEventListener(callable $listener): self
    {
        $this->eventListeners[] = $listener;

        return $this;
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        try {
            if (config('asterisk-pbx-manager.logging.enabled', true)) {
                Log::channel(config('asterisk-pbx-manager.logging.channel', 'default'))
                   ->info($message, $context);
            }
        } catch (\Exception $e) {
            // Silently ignore logging errors during testing or when Laravel context is unavailable
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        try {
            if (config('asterisk-pbx-manager.logging.enabled', true)) {
                Log::channel(config('asterisk-pbx-manager.logging.channel', 'default'))
                   ->error($message, $context);
            }
        } catch (\Exception $e) {
            // Silently ignore logging errors during testing or when Laravel context is unavailable
        }
    }

    /**
     * Destructor - ensure connection is closed.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
