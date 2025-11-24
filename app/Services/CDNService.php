<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

/**
 * CDN Integration Service
 *
 * Cloudflare and Sucuri integration for DDoS protection and CDN
 * Phase 7: Security Hardening
 */
class CDNService
{
    private $provider;
    private $client;
    private $config;

    public function __construct($provider = null)
    {
        $this->provider = $provider ?? env('CDN_PROVIDER', 'cloudflare');
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);

        $this->config = $this->getProviderConfig();
    }

    /**
     * Get provider configuration
     */
    private function getProviderConfig()
    {
        switch ($this->provider) {
            case 'cloudflare':
                return [
                    'api_url' => 'https://api.cloudflare.com/client/v4',
                    'api_key' => env('CLOUDFLARE_API_KEY'),
                    'api_email' => env('CLOUDFLARE_EMAIL'),
                    'zone_id' => env('CLOUDFLARE_ZONE_ID'),
                ];

            case 'sucuri':
                return [
                    'api_url' => 'https://waf.sucuri.net/api',
                    'api_key' => env('SUCURI_API_KEY'),
                    'api_secret' => env('SUCURI_API_SECRET'),
                ];

            case 'bunny':
                return [
                    'api_url' => 'https://api.bunny.net',
                    'api_key' => env('BUNNY_API_KEY'),
                    'zone_id' => env('BUNNY_ZONE_ID'),
                ];

            default:
                throw new \Exception("Unsupported CDN provider: {$this->provider}");
        }
    }

    /**
     * Purge cache for specific URLs
     */
    public function purgeCache($patterns)
    {
        if (!is_array($patterns)) {
            $patterns = [$patterns];
        }

        switch ($this->provider) {
            case 'cloudflare':
                return $this->cloudfarePurge($patterns);
            case 'sucuri':
                return $this->sucuriPurge($patterns);
            case 'bunny':
                return $this->bunnyPurge($patterns);
        }

        return false;
    }

    /**
     * Cloudflare cache purge
     */
    private function cloudfarePurge($patterns)
    {
        try {
            $response = $this->client->post(
                "{$this->config['api_url']}/zones/{$this->config['zone_id']}/purge_cache",
                [
                    'headers' => [
                        'X-Auth-Email' => $this->config['api_email'],
                        'X-Auth-Key' => $this->config['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'files' => $patterns,
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);
            return $result['success'] ?? false;

        } catch (\Exception $e) {
            \Log::error("Cloudflare purge failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sucuri cache purge
     */
    private function sucuriPurge($patterns)
    {
        try {
            $response = $this->client->post(
                "{$this->config['api_url']}/cache/clear",
                [
                    'form_params' => [
                        'k' => $this->config['api_key'],
                        's' => $this->config['api_secret'],
                    ],
                ]
            );

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            \Log::error("Sucuri purge failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bunny CDN cache purge
     */
    private function bunnyPurge($patterns)
    {
        try {
            foreach ($patterns as $pattern) {
                $response = $this->client->post(
                    "{$this->config['api_url']}/pullzone/{$this->config['zone_id']}/purgeCache",
                    [
                        'headers' => [
                            'AccessKey' => $this->config['api_key'],
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'url' => $pattern,
                        ],
                    ]
                );
            }

            return true;

        } catch (\Exception $e) {
            \Log::error("Bunny CDN purge failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Purge all cache
     */
    public function purgeAll()
    {
        switch ($this->provider) {
            case 'cloudflare':
                return $this->cloudflarePurgeAll();
            case 'sucuri':
                return $this->sucuriPurge([]);
            case 'bunny':
                return $this->bunnyPurgeAll();
        }

        return false;
    }

    /**
     * Cloudflare purge all
     */
    private function cloudfarePurgeAll()
    {
        try {
            $response = $this->client->post(
                "{$this->config['api_url']}/zones/{$this->config['zone_id']}/purge_cache",
                [
                    'headers' => [
                        'X-Auth-Email' => $this->config['api_email'],
                        'X-Auth-Key' => $this->config['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'purge_everything' => true,
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);
            return $result['success'] ?? false;

        } catch (\Exception $e) {
            \Log::error("Cloudflare purge all failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bunny purge all
     */
    private function bunnyPurgeAll()
    {
        try {
            $response = $this->client->post(
                "{$this->config['api_url']}/pullzone/{$this->config['zone_id']}/purgeCache",
                [
                    'headers' => [
                        'AccessKey' => $this->config['api_key'],
                    ],
                ]
            );

            return $response->getStatusCode() === 204;

        } catch (\Exception $e) {
            \Log::error("Bunny purge all failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Preload content to edge servers
     */
    public function preloadContent($urls)
    {
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        $results = [];

        foreach ($urls as $url) {
            $results[$url] = $this->preloadUrl($url);
        }

        return $results;
    }

    /**
     * Preload single URL
     */
    private function preloadUrl($url)
    {
        try {
            // Make request to warm cache
            $response = $this->client->get($url, [
                'headers' => [
                    'User-Agent' => 'FOS-Streaming-CDN-Preload/1.0',
                ],
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            \Log::error("Preload failed for {$url}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add firewall rule (Cloudflare only)
     */
    public function addFirewallRule($expression, $action = 'block', $description = null)
    {
        if ($this->provider !== 'cloudflare') {
            return false;
        }

        try {
            $response = $this->client->post(
                "{$this->config['api_url']}/zones/{$this->config['zone_id']}/firewall/rules",
                [
                    'headers' => [
                        'X-Auth-Email' => $this->config['api_email'],
                        'X-Auth-Key' => $this->config['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        [
                            'filter' => [
                                'expression' => $expression,
                                'paused' => false,
                            ],
                            'action' => $action,
                            'description' => $description ?? "FOS Streaming Rule",
                        ],
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);
            return $result['success'] ?? false;

        } catch (\Exception $e) {
            \Log::error("Firewall rule creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Block IP address
     */
    public function blockIP($ip, $note = null)
    {
        return $this->addFirewallRule(
            "(ip.src eq {$ip})",
            'block',
            $note ?? "Blocked by FOS Streaming: {$ip}"
        );
    }

    /**
     * Block country
     */
    public function blockCountry($countryCode)
    {
        return $this->addFirewallRule(
            "(ip.geoip.country eq \"{$countryCode}\")",
            'block',
            "Block country: {$countryCode}"
        );
    }

    /**
     * Get CDN analytics
     */
    public function getAnalytics($period = '24h')
    {
        $cacheKey = "cdn:analytics:{$this->provider}:{$period}";

        return Cache::remember($cacheKey, 300, function() use ($period) {
            switch ($this->provider) {
                case 'cloudflare':
                    return $this->getCloudflareAnalytics($period);
                default:
                    return null;
            }
        });
    }

    /**
     * Get Cloudflare analytics
     */
    private function getCloudflareAnalytics($period)
    {
        try {
            $since = match($period) {
                '1h' => now()->subHour()->toIso8601String(),
                '24h' => now()->subDay()->toIso8601String(),
                '7d' => now()->subDays(7)->toIso8601String(),
                '30d' => now()->subDays(30)->toIso8601String(),
                default => now()->subDay()->toIso8601String(),
            };

            $response = $this->client->get(
                "{$this->config['api_url']}/zones/{$this->config['zone_id']}/analytics/dashboard",
                [
                    'headers' => [
                        'X-Auth-Email' => $this->config['api_email'],
                        'X-Auth-Key' => $this->config['api_key'],
                    ],
                    'query' => [
                        'since' => $since,
                        'continuous' => true,
                    ],
                ]
            );

            $result = json_decode($response->getBody(), true);
            return $result['result'] ?? null;

        } catch (\Exception $e) {
            \Log::error("Cloudflare analytics failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get cache hit rate
     */
    public function getCacheHitRate()
    {
        $analytics = $this->getAnalytics('24h');

        if (!$analytics) {
            return null;
        }

        $totals = $analytics['totals'] ?? [];
        $requests = $totals['requests']['all'] ?? 1;
        $cached = $totals['requests']['cached'] ?? 0;

        return round(($cached / $requests) * 100, 2);
    }

    /**
     * Test CDN connection
     */
    public function testConnection()
    {
        try {
            switch ($this->provider) {
                case 'cloudflare':
                    $response = $this->client->get(
                        "{$this->config['api_url']}/user/tokens/verify",
                        [
                            'headers' => [
                                'Authorization' => "Bearer {$this->config['api_key']}",
                            ],
                        ]
                    );
                    break;

                default:
                    return false;
            }

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            return false;
        }
    }
}
