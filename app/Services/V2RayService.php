<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class V2RayService
{
    private $protocols = ['vmess', 'vless', 'trojan', 'shadowsocks'];
    private $obfuscations = ['none', 'websocket', 'http', 'tls', 'grpc'];

    /**
     * Generate V2Ray client configuration
     */
    public function generateClientConfig($subscriber, $stream, $protocol = 'vmess')
    {
        $subscriberId = $subscriber->id;
        $config = [];

        switch ($protocol) {
            case 'vmess':
                $config = $this->generateVMessConfig($subscriber, $stream);
                break;

            case 'vless':
                $config = $this->generateVLESSConfig($subscriber, $stream);
                break;

            case 'trojan':
                $config = $this->generateTrojanConfig($subscriber, $stream);
                break;

            case 'shadowsocks':
                $config = $this->generateShadowsocksConfig($subscriber, $stream);
                break;

            default:
                throw new \Exception("Unsupported protocol: $protocol");
        }

        // Store configuration in Redis
        Redis::setex("v2ray:config:$subscriberId:$stream->id", 3600, json_encode($config));

        // Log configuration generation
        $this->logConfigGeneration($subscriber, $stream, $protocol);

        return $config;
    }

    /**
     * Generate VMess configuration
     */
    private function generateVMessConfig($subscriber, $stream)
    {
        $uuid = Str::uuid()->toString();
        $port = $this->getAvailablePort();

        $config = [
            'v' => '2',
            'ps' => "FOS-{$subscriber->username}-{$stream->name}",
            'add' => env('V2RAY_SERVER_IP', request()->getHost()),
            'port' => $port,
            'id' => $uuid,
            'aid' => 64, // AlterID for additional security
            'scy' => 'auto', // Security: auto, aes-128-gcm, chacha20-poly1305
            'net' => 'ws', // Network: ws, tcp, kcp, quic, h2, grpc
            'type' => 'none',
            'host' => env('V2RAY_DOMAIN', request()->getHost()),
            'path' => "/stream/{$stream->id}/{$subscriber->id}",
            'tls' => 'tls',
            'sni' => env('V2RAY_SNI', ''),
            'alpn' => 'h2,http/1.1',
            'fp' => 'chrome' // Fingerprint for TLS
        ];

        // Add obfuscation settings
        $proxySettings = json_decode($stream->proxy_settings, true);
        if (isset($proxySettings['obfuscation'])) {
            $config = $this->applyObfuscation($config, $proxySettings['obfuscation']);
        }

        // Store user mapping
        \DB::table('v2ray_users')->updateOrInsert(
            ['subscriber_id' => $subscriber->id],
            [
                'vmess_id' => $uuid,
                'port' => $port,
                'protocol' => 'vmess',
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Generate shareable link
        $config['link'] = $this->generateVMessLink($config);

        // Generate QR code
        $config['qr'] = $this->generateQRCode($config['link']);

        return $config;
    }

    /**
     * Generate VLESS configuration
     */
    private function generateVLESSConfig($subscriber, $stream)
    {
        $uuid = Str::uuid()->toString();
        $port = $this->getAvailablePort();

        $config = [
            'protocol' => 'vless',
            'id' => $uuid,
            'add' => env('V2RAY_SERVER_IP', request()->getHost()),
            'port' => $port,
            'encryption' => 'none', // VLESS doesn't need encryption
            'flow' => 'xtls-rprx-direct', // XTLS flow control
            'net' => 'tcp',
            'type' => 'none',
            'host' => env('V2RAY_DOMAIN', request()->getHost()),
            'path' => '',
            'tls' => 'xtls', // XTLS for better performance
            'sni' => env('V2RAY_SNI', ''),
            'alpn' => 'h2,http/1.1',
            'fp' => 'chrome'
        ];

        // VLESS with XTLS for maximum performance
        if ($stream->stream_mode === 'proxy') {
            $config['flow'] = 'xtls-rprx-splice'; // Direct mode for proxy
        }

        // Store configuration
        \DB::table('v2ray_users')->updateOrInsert(
            ['subscriber_id' => $subscriber->id],
            [
                'vmess_id' => $uuid,
                'port' => $port,
                'protocol' => 'vless',
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Generate shareable link
        $config['link'] = $this->generateVLESSLink($config);
        $config['qr'] = $this->generateQRCode($config['link']);

        return $config;
    }

    /**
     * Generate Trojan configuration
     */
    private function generateTrojanConfig($subscriber, $stream)
    {
        $password = $this->generateSecurePassword();
        $port = 443; // Trojan typically uses 443

        $config = [
            'protocol' => 'trojan',
            'password' => $password,
            'remote_addr' => env('V2RAY_SERVER_IP', request()->getHost()),
            'remote_port' => $port,
            'sni' => env('V2RAY_DOMAIN', request()->getHost()),
            'alpn' => ['h2', 'http/1.1'],
            'fingerprint' => 'chrome',
            'skip_cert_verify' => false,
            'udp' => true,
            'mux' => [
                'enabled' => false,
                'concurrency' => 8
            ]
        ];

        // Trojan-Go enhancements
        if (env('TROJAN_GO_ENABLED', false)) {
            $config['websocket'] = [
                'enabled' => true,
                'path' => "/trojan/{$subscriber->id}",
                'host' => env('V2RAY_DOMAIN', request()->getHost())
            ];
            $config['shadowsocks'] = [
                'enabled' => false
            ];
        }

        // Store configuration
        \DB::table('v2ray_users')->updateOrInsert(
            ['subscriber_id' => $subscriber->id],
            [
                'vmess_id' => $password,
                'port' => $port,
                'protocol' => 'trojan',
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Generate shareable link
        $config['link'] = $this->generateTrojanLink($config);
        $config['qr'] = $this->generateQRCode($config['link']);

        return $config;
    }

    /**
     * Generate Shadowsocks configuration
     */
    private function generateShadowsocksConfig($subscriber, $stream)
    {
        $password = base64_encode(random_bytes(32));
        $port = $this->getAvailablePort();

        $config = [
            'protocol' => 'shadowsocks',
            'server' => env('V2RAY_SERVER_IP', request()->getHost()),
            'server_port' => $port,
            'password' => $password,
            'method' => 'chacha20-ietf-poly1305', // Modern cipher
            'timeout' => 300,
            'fast_open' => true,
            'mode' => 'tcp_and_udp',
            'plugin' => 'v2ray-plugin',
            'plugin_opts' => [
                'mode' => 'websocket',
                'host' => env('V2RAY_DOMAIN', request()->getHost()),
                'path' => "/ss/{$subscriber->id}",
                'tls' => true,
                'mux' => 1
            ]
        ];

        // Store configuration
        \DB::table('v2ray_users')->updateOrInsert(
            ['subscriber_id' => $subscriber->id],
            [
                'vmess_id' => $password,
                'port' => $port,
                'protocol' => 'shadowsocks',
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Generate shareable link
        $config['link'] = $this->generateShadowsocksLink($config);
        $config['qr'] = $this->generateQRCode($config['link']);

        return $config;
    }

    /**
     * Apply obfuscation to configuration
     */
    private function applyObfuscation($config, $type)
    {
        switch ($type) {
            case 'websocket':
                $config['net'] = 'ws';
                $config['path'] = '/ws/' . Str::random(10);
                $config['headers'] = [
                    'Host' => env('V2RAY_DOMAIN', request()->getHost())
                ];
                break;

            case 'http':
                $config['net'] = 'tcp';
                $config['type'] = 'http';
                $config['http'] = [
                    'host' => [env('V2RAY_DOMAIN', 'www.microsoft.com')],
                    'path' => '/'
                ];
                break;

            case 'grpc':
                $config['net'] = 'grpc';
                $config['serviceName'] = 'GrpcService';
                $config['multiMode'] = false;
                break;

            case 'quic':
                $config['net'] = 'quic';
                $config['quicSecurity'] = 'aes-128-gcm';
                $config['key'] = Str::random(10);
                $config['header'] = [
                    'type' => 'wechat-video'
                ];
                break;

            case 'kcp':
                $config['net'] = 'kcp';
                $config['type'] = 'wechat-video'; // Disguise as WeChat video
                break;
        }

        return $config;
    }

    /**
     * Generate VMess shareable link
     */
    private function generateVMessLink($config)
    {
        $vmessData = base64_encode(json_encode($config));
        return "vmess://$vmessData";
    }

    /**
     * Generate VLESS shareable link
     */
    private function generateVLESSLink($config)
    {
        $params = http_build_query([
            'encryption' => $config['encryption'],
            'flow' => $config['flow'],
            'security' => $config['tls'],
            'sni' => $config['sni'],
            'alpn' => implode(',', explode(',', $config['alpn'])),
            'fp' => $config['fp'],
            'type' => $config['net'],
            'host' => $config['host'],
            'path' => $config['path']
        ]);

        return "vless://{$config['id']}@{$config['add']}:{$config['port']}?$params#FOS-Stream";
    }

    /**
     * Generate Trojan shareable link
     */
    private function generateTrojanLink($config)
    {
        $params = http_build_query([
            'sni' => $config['sni'],
            'alpn' => implode(',', $config['alpn']),
            'fp' => $config['fingerprint']
        ]);

        return "trojan://{$config['password']}@{$config['remote_addr']}:{$config['remote_port']}?$params#FOS-Stream";
    }

    /**
     * Generate Shadowsocks shareable link
     */
    private function generateShadowsocksLink($config)
    {
        $userinfo = base64_encode("{$config['method']}:{$config['password']}");
        $params = http_build_query([
            'plugin' => $config['plugin'],
            'mode' => $config['plugin_opts']['mode'],
            'host' => $config['plugin_opts']['host'],
            'path' => $config['plugin_opts']['path'],
            'tls' => $config['plugin_opts']['tls'] ? 'true' : 'false'
        ]);

        return "ss://$userinfo@{$config['server']}:{$config['server_port']}?$params#FOS-Stream";
    }

    /**
     * Generate QR code for configuration
     */
    private function generateQRCode($data)
    {
        // Using simple QR code generation
        $qrApi = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($data);
        return $qrApi;
    }

    /**
     * Get available port
     */
    private function getAvailablePort()
    {
        $startPort = env('V2RAY_PORT_START', 20000);
        $endPort = env('V2RAY_PORT_END', 30000);

        // Get used ports from database
        $usedPorts = \DB::table('v2ray_users')
            ->pluck('port')
            ->toArray();

        // Find available port
        for ($port = $startPort; $port <= $endPort; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        throw new \Exception('No available ports');
    }

    /**
     * Generate secure password
     */
    private function generateSecurePassword()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Log configuration generation
     */
    private function logConfigGeneration($subscriber, $stream, $protocol)
    {
        \DB::table('v2ray_logs')->insert([
            'subscriber_id' => $subscriber->id,
            'stream_id' => $stream->id,
            'action' => 'config_generated',
            'protocol' => $protocol,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()
        ]);
    }

    /**
     * Validate V2Ray connection
     */
    public function validateConnection($subscriberId, $protocol, $uuid)
    {
        $user = \DB::table('v2ray_users')
            ->where('subscriber_id', $subscriberId)
            ->where('protocol', $protocol)
            ->where('vmess_id', $uuid)
            ->first();

        if (!$user) {
            return false;
        }

        // Check subscription status
        $subscription = \DB::table('subscriptions')
            ->where('subscriber_id', $subscriberId)
            ->where('is_active', 1)
            ->where('expire_date', '>', now())
            ->first();

        if (!$subscription) {
            return false;
        }

        // Update last connection
        \DB::table('v2ray_users')
            ->where('id', $user->id)
            ->update(['last_connected' => now()]);

        return true;
    }

    /**
     * Get traffic statistics
     */
    public function getTrafficStats($subscriberId)
    {
        $stats = Redis::hgetall("v2ray:traffic:$subscriberId");

        return [
            'upload' => $stats['upload'] ?? 0,
            'download' => $stats['download'] ?? 0,
            'total' => ($stats['upload'] ?? 0) + ($stats['download'] ?? 0),
            'connections' => $stats['connections'] ?? 0,
            'last_connected' => $stats['last_connected'] ?? null
        ];
    }

    /**
     * Domain fronting configuration
     */
    public function configureDomainFronting($stream, $frontDomain = 'www.cloudflare.com')
    {
        $config = [
            'enabled' => true,
            'front_domain' => $frontDomain,
            'actual_domain' => env('V2RAY_DOMAIN', request()->getHost()),
            'sni' => $frontDomain,
            'headers' => [
                'Host' => env('V2RAY_DOMAIN', request()->getHost())
            ],
            'tls' => [
                'serverName' => $frontDomain,
                'allowInsecure' => false,
                'alpn' => ['h2', 'http/1.1'],
                'fingerprint' => 'chrome'
            ]
        ];

        // Update stream configuration
        $proxySettings = json_decode($stream->proxy_settings, true);
        $proxySettings['domain_fronting'] = $config;

        $stream->update([
            'proxy_settings' => json_encode($proxySettings)
        ]);

        return $config;
    }

    /**
     * Generate batch configurations for resellers
     */
    public function generateBatchConfigs($resellerId, $count, $protocol = 'vmess')
    {
        $configs = [];

        for ($i = 0; $i < $count; $i++) {
            $tempSubscriber = (object)[
                'id' => "reseller-{$resellerId}-{$i}",
                'username' => "user-{$i}"
            ];

            $tempStream = (object)[
                'id' => 1,
                'name' => 'Default Stream',
                'stream_mode' => 'proxy',
                'proxy_settings' => '{}'
            ];

            $configs[] = $this->generateClientConfig($tempSubscriber, $tempStream, $protocol);
        }

        // Store batch in Redis
        Redis::setex("v2ray:batch:$resellerId", 86400, json_encode($configs));

        return $configs;
    }

    /**
     * Get server load balancing configuration
     */
    public function getLoadBalancingConfig()
    {
        $servers = \DB::table('v2ray_servers')
            ->where('enabled', 1)
            ->orderBy('load', 'asc')
            ->get();

        $config = [
            'balancer' => 'leastPing', // or 'random', 'leastLoad'
            'servers' => []
        ];

        foreach ($servers as $server) {
            $config['servers'][] = [
                'tag' => $server->tag,
                'address' => $server->address,
                'port' => $server->port,
                'weight' => $server->weight,
                'health_check' => [
                    'interval' => '30s',
                    'timeout' => '5s',
                    'destination' => $server->health_check_url
                ]
            ];
        }

        return $config;
    }
}