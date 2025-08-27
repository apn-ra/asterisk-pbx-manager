<?php

namespace AsteriskPbxManager\Exceptions;

use Exception;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Response\ResponseMessage;

/**
 * Exception thrown when AMI action execution fails.
 */
class ActionExecutionException extends Exception
{
    /**
     * The action that failed.
     *
     * @var ActionMessage|null
     */
    protected ?ActionMessage $action = null;

    /**
     * The response from Asterisk.
     *
     * @var ResponseMessage|null
     */
    protected ?ResponseMessage $response = null;

    /**
     * Create a new action execution exception instance.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = 'AMI action execution failed', int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for action failure with response.
     *
     * @param ActionMessage $action
     * @param ResponseMessage $response
     * @return static
     */
    public static function actionFailed(ActionMessage $action, ResponseMessage $response): static
    {
        $actionName = $action->getAction();
        $responseMessage = $response->getMessage() ?? 'Unknown error';
        
        $exception = new static("Action '{$actionName}' failed: {$responseMessage}");
        $exception->setAction($action);
        $exception->setResponse($response);
        
        return $exception;
    }

    /**
     * Create exception for action timeout.
     *
     * @param ActionMessage $action
     * @param int $timeout
     * @return static
     */
    public static function timeout(ActionMessage $action, int $timeout): static
    {
        $actionName = $action->getAction();
        
        $exception = new static("Action '{$actionName}' timed out after {$timeout} seconds");
        $exception->setAction($action);
        
        return $exception;
    }

    /**
     * Create exception for invalid action parameters.
     *
     * @param string $actionName
     * @param string $parameter
     * @param string $value
     * @return static
     */
    public static function invalidParameter(string $actionName, string $parameter, string $value): static
    {
        return new static("Invalid parameter '{$parameter}' with value '{$value}' for action '{$actionName}'");
    }

    /**
     * Create exception for missing required parameter.
     *
     * @param string $actionName
     * @param string $parameter
     * @return static
     */
    public static function missingParameter(string $actionName, string $parameter): static
    {
        return new static("Missing required parameter '{$parameter}' for action '{$actionName}'");
    }

    /**
     * Create exception for permission denied.
     *
     * @param string $actionName
     * @param string $username
     * @return static
     */
    public static function permissionDenied(string $actionName, string $username): static
    {
        return new static("Permission denied for action '{$actionName}' - user '{$username}' lacks required privileges");
    }

    /**
     * Set the action that failed.
     *
     * @param ActionMessage $action
     * @return $this
     */
    public function setAction(ActionMessage $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Get the action that failed.
     *
     * @return ActionMessage|null
     */
    public function getAction(): ?ActionMessage
    {
        return $this->action;
    }

    /**
     * Set the response from Asterisk.
     *
     * @param ResponseMessage $response
     * @return $this
     */
    public function setResponse(ResponseMessage $response): self
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get the response from Asterisk.
     *
     * @return ResponseMessage|null
     */
    public function getResponse(): ?ResponseMessage
    {
        return $this->response;
    }

    /**
     * Get additional context for debugging.
     *
     * @return array
     */
    public function getContext(): array
    {
        $context = [];

        if ($this->action) {
            $context['action'] = $this->action->getAction();
            $context['action_id'] = $this->action->getActionId();
        }

        if ($this->response) {
            $context['response_message'] = $this->response->getMessage();
            $context['response_success'] = $this->response->isSuccess();
        }

        return $context;
    }
}