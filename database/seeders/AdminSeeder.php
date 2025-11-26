<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Staff/Admin Seeder
 *
 * Creates default admin account for initial system access
 * This seeder is non-interactive for use with artisan commands
 */
class AdminSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Default admin credentials
        $defaultAdmin = [
            'username' => 'admin',
            'email' => 'admin@fosstreaming.local',
            'password' => md5('admin'), // Default password: admin
            'role' => 'admin',
            'full_name' => 'System Administrator',
            'status' => 'active',
            'permissions' => json_encode([
                'manage_admins',
                'manage_subscribers',
                'manage_streams',
                'manage_packages',
                'manage_settings',
                'view_logs',
                'manage_security'
            ]),
            'login_count' => 0,
            'force_password_change' => 1, // Force password change on first login
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ];

        // Check if admin already exists
        $existingAdmin = Capsule::table('staff')->where('username', 'admin')->first();

        if ($existingAdmin) {
            if ($this->command) {
                $this->command->warn('Default admin account already exists (username: admin)');
            }
            return;
        }

        // Create default admin account
        Capsule::table('staff')->insert($defaultAdmin);

        if ($this->command) {
            $this->command->info('Default admin account created successfully!');
            $this->command->info('');
            $this->command->info('╔═══════════════════════════════════════╗');
            $this->command->info('║     DEFAULT ADMIN CREDENTIALS         ║');
            $this->command->info('╠═══════════════════════════════════════╣');
            $this->command->info('║  Username: admin                      ║');
            $this->command->info('║  Password: admin                      ║');
            $this->command->info('║  Role: Administrator                  ║');
            $this->command->info('╠═══════════════════════════════════════╣');
            $this->command->info('║  ⚠ SECURITY WARNING:                  ║');
            $this->command->info('║  Change the default password          ║');
            $this->command->info('║  immediately after first login!       ║');
            $this->command->info('╚═══════════════════════════════════════╝');
        }
    }
}
