<?php

namespace AsteriskPbxManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for Asterisk Manager Service.
 *
 * @method static bool connect()
 * @method static bool disconnect()
 * @method static bool isConnected()
 * @method static bool reconnect()
 * @method static \PAMI\Message\Response\ResponseMessage send(\PAMI\Message\Action\ActionMessage $action)
 * @method static bool originateCall(string $channel, string $extension, string $context = null, int $priority = 1, int $timeout = 30000)
 * @method static bool hangupCall(string $channel)
 * @method static array getStatus()
 * @method static \AsteriskPbxManager\Services\AsteriskManagerService addEventListener(callable $listener)
 *
 * @see \AsteriskPbxManager\Services\AsteriskManagerService
 */
class AsteriskManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'asterisk-manager';
    }
}