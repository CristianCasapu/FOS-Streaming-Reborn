<?php

require_once __DIR__ . '/../../config.php';

use Admin;

/**
 * Admin Roles Seeder
 *
 * Seeds default admin roles and permissions
 * Phase 1: Foundation - RBAC Implementation
 */
class AdminRolesSeeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        echo "Seeding Admin Roles...\n";

        // Define role permissions
        $rolePermissions = [
            'admin' => null, // Admins have all permissions
            'supervisor' => [
                'streams.view', 'streams.create', 'streams.edit', 'streams.delete',
                'subscribers.view', 'subscribers.create', 'subscribers.edit',
                'subscriptions.view', 'subscriptions.create', 'subscriptions.edit',
                'packages.view', 'packages.create', 'packages.edit',
                'bouquets.view', 'bouquets.create', 'bouquets.edit',
                'channels.view', 'channels.create', 'channels.edit',
                'reports.view',
                'audit.view',
                'resellers.view',
            ],
            'support' => [
                'streams.view',
                'subscribers.view', 'subscribers.edit',
                'subscriptions.view',
                'packages.view',
                'bouquets.view',
                'channels.view',
                'reports.view',
            ],
        ];

        // Update existing admins with roles
        $this->updateExistingAdmins($rolePermissions);

        // Create example admins for each role
        $this->createExampleAdmins($rolePermissions);

        echo "✓ Admin roles seeded successfully\n";
    }

    /**
     * Update existing admins with appropriate roles
     */
    private function updateExistingAdmins($rolePermissions)
    {
        echo "  Updating existing admins...\n";

        $admins = Admin::all();

        foreach ($admins as $admin) {
            // First admin becomes super admin
            if ($admin->id == 1) {
                $admin->update([
                    'role' => 'admin',
                    'permissions' => null,
                    'status' => 'active',
                ]);
                echo "    ✓ Admin #{$admin->id} ({$admin->username}) → Super Admin\n";
                continue;
            }

            // Others become supervisors if not set
            if (!$admin->role || $admin->role === 'support') {
                $admin->update([
                    'role' => 'supervisor',
                    'permissions' => json_encode($rolePermissions['supervisor']),
                    'status' => 'active',
                ]);
                echo "    ✓ Admin #{$admin->id} ({$admin->username}) → Supervisor\n";
            }
        }
    }

    /**
     * Create example admins for testing (only in development)
     */
    private function createExampleAdmins($rolePermissions)
    {
        if (env('APP_ENV') !== 'production') {
            echo "  Creating example admins for development...\n";

            $exampleAdmins = [
                [
                    'username' => 'supervisor',
                    'email' => 'supervisor@fos.local',
                    'password' => password_hash('supervisor123', PASSWORD_BCRYPT),
                    'role' => 'supervisor',
                    'permissions' => json_encode($rolePermissions['supervisor']),
                    'status' => 'active',
                ],
                [
                    'username' => 'support',
                    'email' => 'support@fos.local',
                    'password' => password_hash('support123', PASSWORD_BCRYPT),
                    'role' => 'support',
                    'permissions' => json_encode($rolePermissions['support']),
                    'status' => 'active',
                ],
            ];

            foreach ($exampleAdmins as $adminData) {
                // Check if already exists
                if (!Admin::where('username', $adminData['username'])->exists()) {
                    Admin::create($adminData);
                    echo "    ✓ Created example {$adminData['role']}: {$adminData['username']}\n";
                } else {
                    echo "    ⚠ Admin {$adminData['username']} already exists\n";
                }
            }

            echo "\n  Example Admin Credentials:\n";
            echo "    Supervisor: supervisor / supervisor123\n";
            echo "    Support:    support / support123\n";
        }
    }

    /**
     * Rollback the seeder (reset to default)
     */
    public function rollback()
    {
        echo "Rolling back Admin Roles...\n";

        // Remove example admins
        Admin::whereIn('username', ['supervisor', 'support'])->delete();

        // Reset all admins to default role
        Admin::query()->update([
            'role' => 'admin',
            'permissions' => null,
        ]);

        echo "✓ Admin roles rolled back\n";
    }
}

// Run seeder if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $seeder = new AdminRolesSeeder();

    if (isset($argv[1]) && $argv[1] === '--rollback') {
        $seeder->rollback();
    } else {
        $seeder->run();
    }
}
