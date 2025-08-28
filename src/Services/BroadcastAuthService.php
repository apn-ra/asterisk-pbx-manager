<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling authentication of broadcast events.
 *
 * This service provides authentication mechanisms for Asterisk event broadcasting,
 * supporting both user-based authentication and token-based authentication.
 *
 * @author Asterisk PBX Manager Package
 *
 * @since 1.0.0
 */
class BroadcastAuthService
{
    /**
     * The authentication factory instance.
     *
     * @var AuthFactory
     */
    protected AuthFactory $auth;

    /**
     * Authentication configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new broadcast authentication service instance.
     *
     * @param AuthFactory $auth
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
        $this->config = config('asterisk-pbx-manager.broadcasting.authentication', []);
    }

    /**
     * Check if broadcast authentication is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * Authenticate a user for broadcast access.
     *
     * @param mixed $user Optional user instance to authenticate
     *
     * @throws ActionExecutionException
     *
     * @return bool
     */
    public function authenticateUser($user = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $guard = $this->getGuard();

            // Use provided user or get from guard
            $authenticatedUser = $user ?? $guard->user();

            if (!$authenticatedUser) {
                Log::warning('Broadcast authentication failed: No authenticated user', [
                    'guard'   => $this->config['guard'] ?? 'web',
                    'context' => 'asterisk_broadcast_auth',
                ]);

                return false;
            }

            // Check permissions if specified
            if ($this->hasPermissionRequirement()) {
                return $this->checkPermissions($authenticatedUser);
            }

            Log::info('Broadcast authentication successful', [
                'user_id' => $authenticatedUser->id ?? null,
                'context' => 'asterisk_broadcast_auth',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Broadcast authentication error', [
                'error'   => $e->getMessage(),
                'context' => 'asterisk_broadcast_auth',
            ]);

            throw new ActionExecutionException(
                'Broadcast authentication failed: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Authenticate using token-based authentication.
     *
     * @param string $token
     *
     * @return bool
     */
    public function authenticateToken(string $token): bool
    {
        if (!$this->isEnabled() || !$this->isTokenAuthEnabled()) {
            return true;
        }

        $allowedTokens = $this->config['allowed_tokens'] ?? [];

        if (empty($allowedTokens)) {
            Log::warning('Token authentication attempted but no tokens configured', [
                'context' => 'asterisk_broadcast_auth',
            ]);

            return false;
        }

        $isValid = in_array($token, $allowedTokens, true);

        if ($isValid) {
            Log::info('Token broadcast authentication successful', [
                'token_hash' => hash('sha256', $token),
                'context'    => 'asterisk_broadcast_auth',
            ]);
        } else {
            Log::warning('Token broadcast authentication failed', [
                'token_hash' => hash('sha256', $token),
                'context'    => 'asterisk_broadcast_auth',
            ]);
        }

        return $isValid;
    }

    /**
     * Check if the user should be allowed to receive broadcast events.
     *
     * @param mixed       $user  Optional user instance
     * @param string|null $token Optional token for token-based auth
     *
     * @return bool
     */
    public function canReceiveBroadcast($user = null, ?string $token = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        // Try token authentication first if enabled and token provided
        if ($this->isTokenAuthEnabled() && $token) {
            return $this->authenticateToken($token);
        }

        // Fall back to user authentication
        return $this->authenticateUser($user);
    }

    /**
     * Get the appropriate authentication guard.
     *
     * @return Guard
     */
    protected function getGuard(): Guard
    {
        $guardName = $this->config['guard'] ?? 'web';

        return $this->auth->guard($guardName);
    }

    /**
     * Check if permission requirements are configured.
     *
     * @return bool
     */
    protected function hasPermissionRequirement(): bool
    {
        $permissions = $this->config['required_permissions'] ?? '';

        return !empty($permissions);
    }

    /**
     * Check user permissions for broadcast access.
     *
     * @param mixed $user
     *
     * @return bool
     */
    protected function checkPermissions($user): bool
    {
        $requiredPermissions = $this->config['required_permissions'] ?? '';

        if (empty($requiredPermissions)) {
            return true;
        }

        // Check if user has a 'can' method (Laravel's authorization)
        if (method_exists($user, 'can')) {
            $hasPermission = $user->can($requiredPermissions);

            Log::info('Permission check for broadcast access', [
                'user_id'    => $user->id ?? null,
                'permission' => $requiredPermissions,
                'granted'    => $hasPermission,
                'context'    => 'asterisk_broadcast_auth',
            ]);

            return $hasPermission;
        }

        // Check if user has hasPermission method (custom implementation)
        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($requiredPermissions);
        }

        // Check if user has permissions property/relation
        if (property_exists($user, 'permissions') && is_array($user->permissions)) {
            return in_array($requiredPermissions, $user->permissions, true);
        }

        Log::warning('Cannot verify permissions - no permission method available', [
            'user_id'    => $user->id ?? null,
            'user_class' => get_class($user),
            'context'    => 'asterisk_broadcast_auth',
        ]);

        // Default to deny if we can't check permissions
        return false;
    }

    /**
     * Check if token-based authentication is enabled.
     *
     * @return bool
     */
    protected function isTokenAuthEnabled(): bool
    {
        return (bool) ($this->config['token_based'] ?? false);
    }

    /**
     * Get the middleware configuration for broadcast authentication.
     *
     * @return string|array
     */
    public function getMiddleware()
    {
        return $this->config['middleware'] ?? 'auth';
    }

    /**
     * Get authentication configuration for debugging.
     *
     * @return array
     */
    public function getConfig(): array
    {
        // Return config without sensitive data
        $safeConfig = $this->config;
        unset($safeConfig['allowed_tokens']);

        return $safeConfig;
    }
}
