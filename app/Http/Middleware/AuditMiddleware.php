<?php

namespace App\Http\Middleware;

use Closure;
use AuditLog;

/**
 * Audit Middleware
 *
 * Automatically logs all admin and subscriber actions
 * Phase 1: Foundation - Security Hardening
 */
class AuditMiddleware
{
    /**
     * Actions that should NOT be audited (to prevent log spam)
     */
    private $excludedPaths = [
        'admin/api/health.php',
        'admin/api/metrics.php',
        'heartbeat',
        'ping',
        'status',
    ];

    /**
     * Actions that should NOT log request body (sensitive data)
     */
    private $excludeBodyPaths = [
        'login',
        'password',
        'api_key',
        'secret',
    ];

    /**
     * Handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Skip audit for excluded paths
        if ($this->shouldExclude($request)) {
            return $next($request);
        }

        // Get user info
        $userId = null;
        $userType = 'system';

        if ($request->session()->has('admin_id')) {
            $userId = $request->session()->get('admin_id');
            $userType = 'admin';
        } elseif ($request->session()->has('subscriber_id')) {
            $userId = $request->session()->get('subscriber_id');
            $userType = 'subscriber';
        } elseif ($request->session()->has('reseller_id')) {
            $userId = $request->session()->get('reseller_id');
            $userType = 'reseller';
        }

        // Execute request and capture response
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        // Determine action from request
        $action = $this->determineAction($request);

        // Determine entity from request
        $entity = $this->determineEntity($request);

        // Get request data (excluding sensitive fields)
        $requestData = $this->sanitizeRequestData($request);

        // Determine status from response
        $status = $this->determineStatus($response);

        // Create audit log entry asynchronously (if possible)
        try {
            $this->logAction([
                'user_id' => $userId,
                'user_type' => $userType,
                'action' => $action,
                'entity_type' => $entity['type'] ?? null,
                'entity_id' => $entity['id'] ?? null,
                'old_values' => null, // Would need to fetch from DB before action
                'new_values' => $requestData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $status,
                'duration_ms' => $duration,
                'description' => $this->generateDescription($action, $entity, $userType),
            ]);

        } catch (\Exception $e) {
            // Don't fail request if audit logging fails
            \Log::error('Audit logging failed: ' . $e->getMessage(), [
                'action' => $action,
                'user_id' => $userId,
                'user_type' => $userType,
            ]);
        }

        return $response;
    }

    /**
     * Check if request should be excluded from audit
     */
    private function shouldExclude($request)
    {
        $path = $request->path();

        foreach ($this->excludedPaths as $excluded) {
            if (strpos($path, $excluded) !== false) {
                return true;
            }
        }

        // Skip GET requests to list endpoints (too verbose)
        if ($request->method() === 'GET' && strpos($path, 'api/') !== false && !$request->input('id')) {
            return true;
        }

        return false;
    }

    /**
     * Determine action from request
     */
    private function determineAction($request)
    {
        $path = $request->path();
        $method = $request->method();
        $action = $request->input('action');

        // Map HTTP methods to actions
        $methodMap = [
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
            'GET' => 'view',
        ];

        // Check for explicit action parameter
        if ($action) {
            return $action;
        }

        // Check for specific patterns in path
        if (strpos($path, 'login') !== false) return 'login';
        if (strpos($path, 'logout') !== false) return 'logout';
        if (strpos($path, 'create') !== false) return 'create';
        if (strpos($path, 'update') !== false) return 'update';
        if (strpos($path, 'delete') !== false) return 'delete';
        if (strpos($path, 'enable') !== false) return 'enable';
        if (strpos($path, 'disable') !== false) return 'disable';
        if (strpos($path, 'start') !== false) return 'start';
        if (strpos($path, 'stop') !== false) return 'stop';
        if (strpos($path, 'restart') !== false) return 'restart';

        // Default to method mapping
        return $methodMap[$method] ?? 'unknown';
    }

    /**
     * Determine entity from request
     */
    private function determineEntity($request)
    {
        $path = $request->path();
        $id = $request->input('id') ?? $request->route('id') ?? null;

        // Map paths to entity types
        if (strpos($path, 'streams') !== false) {
            return ['type' => 'Stream', 'id' => $id];
        }
        if (strpos($path, 'subscribers') !== false) {
            return ['type' => 'Subscriber', 'id' => $id];
        }
        if (strpos($path, 'subscriptions') !== false) {
            return ['type' => 'Subscription', 'id' => $id];
        }
        if (strpos($path, 'resellers') !== false) {
            return ['type' => 'Reseller', 'id' => $id];
        }
        if (strpos($path, 'packages') !== false) {
            return ['type' => 'Package', 'id' => $id];
        }
        if (strpos($path, 'bouquets') !== false) {
            return ['type' => 'Bouquet', 'id' => $id];
        }
        if (strpos($path, 'channels') !== false) {
            return ['type' => 'Channel', 'id' => $id];
        }
        if (strpos($path, 'admins') !== false) {
            return ['type' => 'Admin', 'id' => $id];
        }
        if (strpos($path, 'settings') !== false) {
            return ['type' => 'Settings', 'id' => $id];
        }

        return ['type' => null, 'id' => $id];
    }

    /**
     * Sanitize request data (remove sensitive fields)
     */
    private function sanitizeRequestData($request)
    {
        $data = $request->all();

        // Remove sensitive fields
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'api_key',
            'api_secret',
            'secret',
            'token',
            'private_key',
            'encryption_key',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        // Check if path contains sensitive keywords
        foreach ($this->excludeBodyPaths as $keyword) {
            if (strpos($request->path(), $keyword) !== false) {
                return ['[REDACTED]' => 'Sensitive data excluded from audit log'];
            }
        }

        return $data;
    }

    /**
     * Determine status from response
     */
    private function determineStatus($response)
    {
        $statusCode = $response->status();

        if ($statusCode >= 200 && $statusCode < 300) {
            return 'success';
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'failed';
        }

        if ($statusCode >= 500) {
            return 'error';
        }

        return 'unknown';
    }

    /**
     * Generate human-readable description
     */
    private function generateDescription($action, $entity, $userType)
    {
        $entityType = $entity['type'] ?? 'Resource';
        $entityId = $entity['id'] ?? '';

        $descriptions = [
            'create' => ucfirst($userType) . " created {$entityType}" . ($entityId ? " #{$entityId}" : ''),
            'update' => ucfirst($userType) . " updated {$entityType}" . ($entityId ? " #{$entityId}" : ''),
            'delete' => ucfirst($userType) . " deleted {$entityType}" . ($entityId ? " #{$entityId}" : ''),
            'view' => ucfirst($userType) . " viewed {$entityType}" . ($entityId ? " #{$entityId}" : ''),
            'login' => ucfirst($userType) . " logged in",
            'logout' => ucfirst($userType) . " logged out",
            'enable' => ucfirst($userType) . " enabled {$entityType}" . ($entityId ? " #{$entityId}" : ''),
            'disable' => ucfirst($userType) . " disabled {$entityType}" . ($entityId ? " #{$entityId}" : ''),
        ];

        return $descriptions[$action] ?? ucfirst($userType) . " performed {$action}";
    }

    /**
     * Log action to audit log
     */
    private function logAction(array $data)
    {
        // Use queue if available for async logging
        if (class_exists('Illuminate\Support\Facades\Queue')) {
            // Queue::push(new LogAuditJob($data));
        }

        // Synchronous logging for now
        AuditLog::create($data);
    }

    /**
     * Terminate middleware - log after response sent
     * This allows capturing changes made during request
     */
    public function terminate($request, $response)
    {
        // Could be used to log changes detected after request processing
        // For example, comparing database state before/after
    }
}
