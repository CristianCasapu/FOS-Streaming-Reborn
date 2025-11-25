<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Admin Roles Seeder
 *
 * Seeds default admin roles and permissions
 * Phase 1: Foundation - RBAC Implementation
 */
class AdminRolesSeeder extends Seeder
{
    public $command;

    /**
     * Role permissions definitions
     */
    private array $rolePermissions = [
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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding Admin Roles...');
        }

        // Update existing admins with roles
        $this->updateExistingAdmins();

        // Create example admins for each role (only in non-production)
        $this->createExampleAdmins();

        if ($this->command) {
            $this->command->info('Admin roles seeded successfully!');
        }
    }

    /**
     * Update existing admins with appropriate roles
     */
    private function updateExistingAdmins(): void
    {
        if ($this->command) {
            $this->command->line('  Updating existing admins...');
        }

        $admins = Capsule::table('staff')->get();

        foreach ($admins as $admin) {
            // First admin becomes super admin
            if ($admin->id == 1) {
                Capsule::table('staff')->where('id', $admin->id)->update([
                    'role' => 'admin',
                    'permissions' => null,
                    'status' => 'active',
                ]);
                if ($this->command) {
                    $this->command->line("    Admin #{$admin->id} ({$admin->username}) -> Super Admin");
                }
                continue;
            }

            // Others become supervisors if not set
            if (!$admin->role || $admin->role === 'support') {
                Capsule::table('staff')->where('id', $admin->id)->update([
                    'role' => 'supervisor',
                    'permissions' => json_encode($this->rolePermissions['supervisor']),
                    'status' => 'active',
                ]);
                if ($this->command) {
                    $this->command->line("    Admin #{$admin->id} ({$admin->username}) -> Supervisor");
                }
            }
        }
    }

    /**
     * Create example admins for testing (only in development)
     */
    private function createExampleAdmins(): void
    {
        $appEnv = env('APP_ENV', 'production');

        if ($appEnv === 'production') {
            return;
        }

        if ($this->command) {
            $this->command->line('  Creating example admins for development...');
        }

        $timestamp = date('Y-m-d H:i:s');

        $exampleAdmins = [
            [
                'username' => 'supervisor',
                'email' => 'supervisor@fos.local',
                'password' => password_hash('supervisor123', PASSWORD_BCRYPT),
                'role' => 'supervisor',
                'permissions' => json_encode($this->rolePermissions['supervisor']),
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'username' => 'support',
                'email' => 'support@fos.local',
                'password' => password_hash('support123', PASSWORD_BCRYPT),
                'role' => 'support',
                'permissions' => json_encode($this->rolePermissions['support']),
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($exampleAdmins as $adminData) {
            // Check if already exists
            $exists = Capsule::table('staff')->where('username', $adminData['username'])->exists();

            if (!$exists) {
                Capsule::table('staff')->insert($adminData);
                if ($this->command) {
                    $this->command->line("    Created example {$adminData['role']}: {$adminData['username']}");
                }
            } else {
                if ($this->command) {
                    $this->command->warn("    Admin {$adminData['username']} already exists");
                }
            }
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->line('  Example Admin Credentials:');
            $this->command->line('    Supervisor: supervisor / supervisor123');
            $this->command->line('    Support:    support / support123');
        }
    }
}
