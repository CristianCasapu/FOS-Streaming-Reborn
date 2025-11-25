<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * V2Ray Servers Seeder
 *
 * Seeds default V2Ray server configurations
 * Phase 4: V2Ray/VMess Traffic Obfuscation
 */
class V2RayServersSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding V2Ray Servers...');
        }

        $timestamp = date('Y-m-d H:i:s');
        $appEnv = env('APP_ENV', 'production');

        $servers = [
            [
                'tag' => 'primary-v2ray-us',
                'name' => 'Primary US Server',
                'address' => env('V2RAY_SERVER_IP', 'v2ray-us.example.com'),
                'port' => 443,
                'location' => 'us',
                'country_code' => 'US',
                'weight' => 100,
                'enabled' => 1,
                'max_connections' => 1000,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => $timestamp,
                'config' => json_encode([
                    'protocol' => 'vmess',
                    'transport' => 'ws',
                    'tls' => true,
                    'ws_path' => '/v2ray',
                    'ws_host' => env('V2RAY_DOMAIN', 'example.com'),
                    'tls_servername' => env('V2RAY_SNI', 'example.com'),
                    'allow_insecure' => false,
                ]),
                'health_check_url' => null,
                'notes' => 'Primary V2Ray server in US datacenter',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'tag' => 'backup-v2ray-eu',
                'name' => 'Backup EU Server',
                'address' => 'v2ray-eu.example.com',
                'port' => 443,
                'location' => 'eu',
                'country_code' => 'DE',
                'weight' => 80,
                'enabled' => 1,
                'max_connections' => 500,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => $timestamp,
                'config' => json_encode([
                    'protocol' => 'vless',
                    'transport' => 'tcp',
                    'tls' => true,
                    'reality' => true,
                    'tls_servername' => 'cloudflare.com',
                    'flow' => 'xtls-rprx-vision',
                ]),
                'health_check_url' => null,
                'notes' => 'Backup server in EU with REALITY protocol',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'tag' => 'edge-v2ray-sg',
                'name' => 'Edge Singapore Server',
                'address' => 'v2ray-sg.example.com',
                'port' => 8443,
                'location' => 'asia',
                'country_code' => 'SG',
                'weight' => 60,
                'enabled' => 1,
                'max_connections' => 750,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => $timestamp,
                'config' => json_encode([
                    'protocol' => 'trojan',
                    'transport' => 'grpc',
                    'tls' => true,
                    'grpc_service_name' => 'v2ray',
                    'tls_servername' => 'example.com',
                    'allow_insecure' => false,
                ]),
                'health_check_url' => null,
                'notes' => 'Edge server in Singapore for APAC region',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'tag' => 'dev-v2ray-local',
                'name' => 'Development Local Server',
                'address' => '127.0.0.1',
                'port' => 10086,
                'location' => 'other',
                'country_code' => null,
                'weight' => 10,
                'enabled' => $appEnv !== 'production' ? 1 : 0,
                'max_connections' => 100,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'unknown',
                'last_health_check' => $timestamp,
                'config' => json_encode([
                    'protocol' => 'vmess',
                    'transport' => 'tcp',
                    'tls' => false,
                    'allow_insecure' => true,
                ]),
                'health_check_url' => null,
                'notes' => 'Local development server (disabled in production)',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($servers as $serverData) {
            // Check if already exists
            $exists = Capsule::table('v2ray_servers')->where('tag', $serverData['tag'])->exists();

            if ($exists) {
                if ($this->command) {
                    $this->command->warn("  Server {$serverData['tag']} already exists");
                }
                continue;
            }

            Capsule::table('v2ray_servers')->insert($serverData);

            if ($this->command) {
                $this->command->line("  Created V2Ray server: {$serverData['tag']} ({$serverData['name']})");
            }
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->line('  Server Configuration Summary:');
            $this->command->line("    Primary US:  vmess+ws+tls @ {$servers[0]['address']}:443");
            $this->command->line("    Backup EU:   vless+tcp+reality @ {$servers[1]['address']}:443");
            $this->command->line("    Edge SG:     trojan+grpc+tls @ {$servers[2]['address']}:8443");
            if ($appEnv !== 'production') {
                $this->command->line('    Dev Local:   vmess+tcp @ 127.0.0.1:10086');
            }
            $this->command->newLine();
            $this->command->info('V2Ray servers seeded successfully!');
        }
    }
}
