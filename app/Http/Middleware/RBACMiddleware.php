<?php

namespace App\Http\Middleware;

use Closure;
use Staff;

/**
 * Role-Based Access Control (RBAC) Middleware
 *
 * Enforces role and permission checks for admin users
 * Phase 1: Foundation - Security Hardening
 */
class RBACMiddleware
{
    /**
     * Handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|array  $roles  Comma-separated roles or array
     * @param  string|array  $permissions  Optional permissions
     * @return mixed
     */
    public function handle($request, Closure $next, $roles = null, $permissions = null)
    {
        // Check if admin is authenticated
        if (!$request->session()->has('admin_id')) {
            return $this->unauthorizedResponse('Admin not authenticated');
        }

        $adminId = $request->session()->get('admin_id');

        try {
            $admin = Staff::findOrFail($adminId);

            // Check if admin account is active
            if ($admin->status !== 'active') {
                return $this->unauthorizedResponse('Admin account is not active');
            }

            // If no specific roles required, just check authentication
            if (!$roles) {
                return $next($request);
            }

            // Convert comma-separated string to array
            $requiredRoles = is_string($roles) ? explode(',', $roles) : (array)$roles;
            $requiredPermissions = $permissions ? (is_string($permissions) ? explode(',', $permissions) : (array)$permissions) : [];

            // Check role
            if (!$this->hasRole($admin, $requiredRoles)) {
                \AuditLog::log(
                    'rbac_role_denied',
                    'Admin',
                    $adminId,
                    null,
                    [
                        'required_roles' => $requiredRoles,
                        'admin_role' => $admin->role,
                        'path' => $request->path(),
                    ],
                    'Admin ' . $admin->username . ' denied access due to insufficient role'
                );

                return $this->forbiddenResponse('Insufficient privileges');
            }

            // Check permissions if specified
            if (!empty($requiredPermissions) && !$this->hasPermission($admin, $requiredPermissions)) {
                \AuditLog::log(
                    'rbac_permission_denied',
                    'Admin',
                    $adminId,
                    null,
                    [
                        'required_permissions' => $requiredPermissions,
                        'admin_permissions' => $admin->permissions,
                        'path' => $request->path(),
                    ],
                    'Admin ' . $admin->username . ' denied access due to missing permissions'
                );

                return $this->forbiddenResponse('Missing required permissions');
            }

            // Add admin to request for downstream use
            $request->attributes->set('admin', $admin);

        } catch (\Exception $e) {
            \Log::error('RBAC middleware error: ' . $e->getMessage(), [
                'admin_id' => $adminId,
                'path' => $request->path(),
            ]);

            return $this->unauthorizedResponse('Authorization failed');
        }

        return $next($request);
    }

    /**
     * Check if admin has required role
     */
    private function hasRole($admin, array $requiredRoles)
    {
        // Admins can access everything
        if ($admin->role === 'admin') {
            return true;
        }

        return in_array($admin->role, $requiredRoles);
    }

    /**
     * Check if admin has required permissions
     */
    private function hasPermission($admin, array $requiredPermissions)
    {
        // Admins have all permissions
        if ($admin->role === 'admin') {
            return true;
        }

        // Get admin permissions
        $adminPermissions = $admin->permissions ? json_decode($admin->permissions, true) : [];

        if (!is_array($adminPermissions)) {
            return false;
        }

        // Check if admin has all required permissions
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $adminPermissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse($message)
    {
        if (request()->expectsJson() || request()->is('admin/api/*')) {
            return response()->json([
                'success' => false,
                'error' => $message,
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        return redirect()->route('admin.login')
            ->with('error', $message);
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse($message)
    {
        if (request()->expectsJson() || request()->is('admin/api/*')) {
            return response()->json([
                'success' => false,
                'error' => $message,
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return redirect()->route('admin.dashboard')
            ->with('error', $message);
    }

    /**
     * Role-specific middleware methods for route convenience
     */

    /**
     * Require admin role
     */
    public function requireAdmin($request, Closure $next)
    {
        return $this->handle($request, $next, ['admin']);
    }

    /**
     * Require supervisor or admin role
     */
    public function requireSupervisor($request, Closure $next)
    {
        return $this->handle($request, $next, ['admin', 'supervisor']);
    }

    /**
     * Require any authenticated admin
     */
    public function requireAuth($request, Closure $next)
    {
        return $this->handle($request, $next);
    }

    /**
     * Check specific permission
     */
    public function checkPermission($request, Closure $next, $permission)
    {
        return $this->handle($request, $next, null, $permission);
    }
}

/**
 * Available Roles:
 * - admin: Full system access
 * - supervisor: Limited admin access (can manage content, view reports)
 * - support: Read-only access, can manage support tickets
 *
 * Available Permissions (examples):
 * - streams.create, streams.edit, streams.delete, streams.view
 * - subscribers.create, subscribers.edit, subscribers.delete, subscribers.view
 * - resellers.create, resellers.edit, resellers.delete, resellers.view
 * - settings.edit, settings.view
 * - reports.view
 * - audit.view
 */
