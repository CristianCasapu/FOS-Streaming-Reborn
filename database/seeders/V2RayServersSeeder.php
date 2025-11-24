<?php

require_once __DIR__ . '/../../config.php';

use V2RayServer;

/**
 * V2Ray Servers Seeder
 *
 * Seeds default V2Ray server configurations
 * Phase 4: V2Ray/VMess Traffic Obfuscation
 */
class V2RayServersSeeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        echo "Seeding V2Ray Servers...\n";

        $servers = [
            [
                'tag' => 'primary-v2ray-us',
                'name' => 'Primary US Server',
                'address' => env('V2RAY_SERVER_IP', 'v2ray-us.example.com'),
                'port' => 443,
                'protocol' => 'vmess',
                'transport' => 'ws',
                'tls' => true,
                'location' => 'US',
                'region' => 'North America',
                'enabled' => true,
                'max_connections' => 1000,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => now(),
                'config' => json_encode([
                    'ws_path' => '/v2ray',
                    'ws_host' => env('V2RAY_DOMAIN', 'example.com'),
                    'tls_servername' => env('V2RAY_SNI', 'example.com'),
                    'allow_insecure' => false,
                ]),
                'notes' => 'Primary V2Ray server in US datacenter',
            ],
            [
                'tag' => 'backup-v2ray-eu',
                'name' => 'Backup EU Server',
                'address' => 'v2ray-eu.example.com',
                'port' => 443,
                'protocol' => 'vless',
                'transport' => 'tcp',
                'tls' => true,
                'location' => 'DE',
                'region' => 'Europe',
                'enabled' => true,
                'max_connections' => 500,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => now(),
                'config' => json_encode([
                    'reality' => true,
                    'tls_servername' => 'cloudflare.com',
                    'flow' => 'xtls-rprx-vision',
                ]),
                'notes' => 'Backup server in EU with REALITY protocol',
            ],
            [
                'tag' => 'edge-v2ray-sg',
                'name' => 'Edge Singapore Server',
                'address' => 'v2ray-sg.example.com',
                'port' => 8443,
                'protocol' => 'trojan',
                'transport' => 'grpc',
                'tls' => true,
                'location' => 'SG',
                'region' => 'Asia Pacific',
                'enabled' => true,
                'max_connections' => 750,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'healthy',
                'last_health_check' => now(),
                'config' => json_encode([
                    'grpc_service_name' => 'v2ray',
                    'tls_servername' => 'example.com',
                    'allow_insecure' => false,
                ]),
                'notes' => 'Edge server in Singapore for APAC region',
            ],
            [
                'tag' => 'dev-v2ray-local',
                'name' => 'Development Local Server',
                'address' => '127.0.0.1',
                'port' => 10086,
                'protocol' => 'vmess',
                'transport' => 'tcp',
                'tls' => false,
                'location' => 'LOCAL',
                'region' => 'Development',
                'enabled' => env('APP_ENV') !== 'production',
                'max_connections' => 100,
                'current_connections' => 0,
                'load' => 0.00,
                'health_status' => 'unknown',
                'last_health_check' => now(),
                'config' => json_encode([
                    'allow_insecure' => true,
                ]),
                'notes' => 'Local development server (disabled in production)',
            ],
        ];

        foreach ($servers as $serverData) {
            // Check if already exists
            if (V2RayServer::where('tag', $serverData['tag'])->exists()) {
                echo "  ⚠ Server {$serverData['tag']} already exists\n";
                continue;
            }

            $server = V2RayServer::create($serverData);
            echo "  ✓ Created V2Ray server: {$server->tag} ({$server->name})\n";
        }

        echo "\n  Server Configuration Summary:\n";
        echo "    Primary US:  vmess+ws+tls @ {$servers[0]['address']}:443\n";
        echo "    Backup EU:   vless+tcp+reality @ {$servers[1]['address']}:443\n";
        echo "    Edge SG:     trojan+grpc+tls @ {$servers[2]['address']}:8443\n";
        if (env('APP_ENV') !== 'production') {
            echo "    Dev Local:   vmess+tcp @ 127.0.0.1:10086\n";
        }

        echo "\n✓ V2Ray servers seeded successfully\n";

        // Provide setup instructions
        $this->printSetupInstructions();
    }

    /**
     * Print setup instructions
     */
    private function printSetupInstructions()
    {
        echo "\n" . str_repeat("-", 60) . "\n";
        echo "SETUP INSTRUCTIONS:\n";
        echo str_repeat("-", 60) . "\n";
        echo "1. Update .env with your V2Ray server details:\n";
        echo "   V2RAY_SERVER_IP=your-server-ip\n";
        echo "   V2RAY_DOMAIN=your-domain.com\n";
        echo "   V2RAY_SNI=your-domain.com\n";
        echo "   V2RAY_PORT_START=20000\n";
        echo "   V2RAY_PORT_END=30000\n\n";

        echo "2. Install V2Ray on your server:\n";
        echo "   bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)\n\n";

        echo "3. Configure V2Ray server config.json\n";
        echo "   See: docs/guides/V2RAY_SERVER_SETUP.md\n\n";

        echo "4. Test server connectivity:\n";
        echo "   php test_v2ray.php\n\n";

        echo "5. Run health check:\n";
        echo "   php -r \"require 'config.php'; \\$s = \\V2RayServer::find(1); \\$s->performHealthCheck(); echo \\$s->health_status;\"\n";
        echo str_repeat("-", 60) . "\n";
    }

    /**
     * Rollback the seeder
     */
    public function rollback()
    {
        echo "Rolling back V2Ray Servers...\n";

        $demoServers = [
            'primary-v2ray-us',
            'backup-v2ray-eu',
            'edge-v2ray-sg',
            'dev-v2ray-local',
        ];

        // Delete servers
        V2RayServer::whereIn('tag', $demoServers)->delete();

        echo "✓ V2Ray servers rolled back\n";
    }
}

// Run seeder if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $seeder = new V2RayServersSeeder();

    if (isset($argv[1]) && $argv[1] === '--rollback') {
        $seeder->rollback();
    } else {
        $seeder->run();
    }
}
