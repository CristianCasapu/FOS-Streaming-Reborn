<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * UFW Rules Seeder
 *
 * Seeds default firewall rules for FOS-Streaming.
 * These rules ensure essential services are accessible and prevent server lockouts.
 *
 * IMPORTANT: SSH port is auto-detected and marked as protected to prevent lockouts.
 */
class UfwRulesSeeder extends Seeder
{
    public $command;

    /**
     * Detect the current SSH port from sshd configuration
     *
     * Checks multiple sources:
     * 1. /etc/ssh/sshd_config
     * 2. Active sshd process listening ports
     * 3. Fallback to default port 22
     */
    private function detectSshPort(): string
    {
        // Method 1: Check sshd_config file
        $sshdConfig = '/etc/ssh/sshd_config';
        if (file_exists($sshdConfig) && is_readable($sshdConfig)) {
            $content = file_get_contents($sshdConfig);
            if (preg_match('/^Port\s+(\d+)/m', $content, $matches)) {
                return $matches[1];
            }
        }

        // Method 2: Check sshd_config.d directory for custom port
        $configDir = '/etc/ssh/sshd_config.d/';
        if (is_dir($configDir)) {
            $files = glob($configDir . '*.conf');
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $content = file_get_contents($file);
                    if (preg_match('/^Port\s+(\d+)/m', $content, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }

        // Method 3: Check what port sshd is actually listening on
        $netstatCmd = shell_exec('ss -tlnp 2>/dev/null | grep sshd || netstat -tlnp 2>/dev/null | grep sshd');
        if ($netstatCmd && preg_match('/:(\d+)\s/', $netstatCmd, $matches)) {
            return $matches[1];
        }

        // Default fallback
        return '22';
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding UFW Rules...');
        }

        $timestamp = date('Y-m-d H:i:s');

        // Detect SSH port dynamically
        $sshPort = $this->detectSshPort();

        if ($this->command) {
            $this->command->info("  Detected SSH port: {$sshPort}");
        }

        // Default rules - essential services
        // Protected rules cannot be deleted through admin panel
        $rules = [
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => $sshPort,
                'protocol' => 'tcp',
                'comment' => 'SSH - Protected (cannot be deleted to prevent lockout)',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '80',
                'protocol' => 'tcp',
                'comment' => 'HTTP Web Server',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '443',
                'protocol' => 'tcp',
                'comment' => 'HTTPS Web Server',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '1935',
                'protocol' => 'tcp',
                'comment' => 'RTMP Streaming (Ingest)',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '7777',
                'protocol' => 'tcp',
                'comment' => 'FOS Admin Panel (default port)',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '8000',
                'protocol' => 'tcp',
                'comment' => 'HTTP Streaming Port (HLS/DASH)',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '9000',
                'protocol' => 'any',
                'comment' => 'SRT Streaming Port (UDP/TCP)',
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'action' => 'allow',
                'from_ip' => null,
                'to_port' => '3306',
                'protocol' => 'tcp',
                'comment' => 'MariaDB/MySQL (localhost only recommended)',
                'is_active' => 0, // Disabled by default for security
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        $created = 0;
        foreach ($rules as $ruleData) {
            // Check if rule already exists by port and protocol
            $exists = Capsule::table('ufw_rules')
                ->where('to_port', $ruleData['to_port'])
                ->where('protocol', $ruleData['protocol'])
                ->where('action', $ruleData['action'])
                ->exists();

            if ($exists) {
                if ($this->command) {
                    $this->command->warn("  Rule for port {$ruleData['to_port']}/{$ruleData['protocol']} already exists");
                }
                continue;
            }

            Capsule::table('ufw_rules')->insert($ruleData);
            $created++;

            if ($this->command) {
                $status = $ruleData['is_active'] ? 'active' : 'disabled';
                $this->command->info("  Created rule: {$ruleData['action']} {$ruleData['to_port']}/{$ruleData['protocol']} ({$status})");
            }
        }

        if ($this->command) {
            $this->command->info('');
            $this->command->info('  Important: These rules are database records only.');
            $this->command->info('  They must be applied to UFW via the admin panel.');
            $this->command->info("  SSH (port {$sshPort}) is protected and cannot be removed.");
            $this->command->info('');
            $this->command->info("UFW rules seeded successfully! ({$created} created)");
        }
    }
}
