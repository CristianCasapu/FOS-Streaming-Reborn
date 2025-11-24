<?php

namespace App\Services;

use Stream;
use Subscriber;
use Illuminate\Support\Facades\Redis;

/**
 * SRT (Secure Reliable Transport) Service
 *
 * Server-side SRT configuration and management
 * Phase 2: SRT Implementation
 */
class SRTService
{
    private $passphrase;
    private $keyLength = 32; // AES-256
    private $defaultPort = 9000;
    private $defaultLatency = 120; // milliseconds

    public function __construct()
    {
        $this->passphrase = env('SRT_PASSPHRASE', $this->generatePassphrase());
    }

    /**
     * Generate secure SRT passphrase
     */
    public function generatePassphrase()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate stream-specific encryption key
     */
    public function generateStreamKey($subscriberId, $streamId)
    {
        return hash_hmac('sha256', "{$subscriberId}:{$streamId}", $this->passphrase);
    }

    /**
     * Create SRT URL for subscriber
     */
    public function createSRTUrl($stream, $subscriber)
    {
        $key = $this->generateStreamKey($subscriber->id, $stream->id);
        $port = $this->getSRTPort($stream);

        return sprintf(
            'srt://%s:%d?streamid=%s&passphrase=%s&pbkeylen=%d&latency=%d',
            env('SRT_HOST', request()->getHost()),
            $port,
            $this->generateStreamId($subscriber, $stream),
            $key,
            $this->keyLength,
            $stream->srt_latency ?? $this->defaultLatency
        );
    }

    /**
     * Generate stream ID for SRT connection
     */
    private function generateStreamId($subscriber, $stream)
    {
        // Format: subscriber_id:stream_id:timestamp:signature
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$subscriber->id}:{$stream->id}:{$timestamp}", $this->passphrase);

        return base64_encode("{$subscriber->id}:{$stream->id}:{$timestamp}:{$signature}");
    }

    /**
     * Parse and validate stream ID
     */
    public function parseStreamId($streamId)
    {
        try {
            $decoded = base64_decode($streamId);
            [$subscriberId, $streamId, $timestamp, $signature] = explode(':', $decoded);

            // Verify signature
            $expectedSignature = hash_hmac('sha256', "{$subscriberId}:{$streamId}:{$timestamp}", $this->passphrase);

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            // Check timestamp (valid for 24 hours)
            if (time() - $timestamp > 86400) {
                return null;
            }

            return [
                'subscriber_id' => (int)$subscriberId,
                'stream_id' => (int)$streamId,
                'timestamp' => $timestamp,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get SRT port for stream
     */
    public function getSRTPort($stream)
    {
        // Use proxy_port if set, otherwise calculate based on stream ID
        if ($stream->proxy_port) {
            return $stream->proxy_port;
        }

        $basePort = env('SRT_PORT_START', 9000);
        return $basePort + $stream->id;
    }

    /**
     * Configure SRT stream
     */
    public function configureStream($stream, $options = [])
    {
        $defaultOptions = [
            'latency' => $this->defaultLatency,
            'maxbw' => 0, // Unlimited
            'pbkeylen' => $this->keyLength,
            'passphrase' => $this->generateStreamKey(0, $stream->id),
            'mode' => 'listener', // caller or listener
            'transtype' => 'live', // live or file
            'rcvbuf' => 8192000, // 8MB receive buffer
            'sndbuf' => 8192000, // 8MB send buffer
            'lossmaxttl' => 0, // Unlimited packet retransmission
            'conntimeo' => 3000, // 3 second connection timeout
            'peeridletimeo' => 5000, // 5 second peer idle timeout
            'tlpktdrop' => true, // Drop too-late packets
            'nakreport' => true, // Periodic NAK reports
            'minversion' => 0x010300, // SRT 1.3.0
        ];

        $config = array_merge($defaultOptions, $options);

        // Store configuration
        $encryptionSettings = json_decode($stream->encryption_settings, true) ?? [];
        $encryptionSettings['srt'] = $config;

        $stream->update([
            'srt_enabled' => true,
            'protocol' => 'srt',
            'encryption_settings' => json_encode($encryptionSettings),
            'proxy_port' => $this->getSRTPort($stream),
        ]);

        return $config;
    }

    /**
     * Validate SRT connection
     */
    public function validateConnection($subscriberId, $streamId, $streamIdParam)
    {
        $parsed = $this->parseStreamId($streamIdParam);

        if (!$parsed) {
            return false;
        }

        if ($parsed['subscriber_id'] !== $subscriberId || $parsed['stream_id'] !== $streamId) {
            return false;
        }

        // Check subscriber subscription status
        $subscriber = Subscriber::find($subscriberId);
        if (!$subscriber || !$subscriber->hasActiveSubscription()) {
            return false;
        }

        // Check stream status
        $stream = Stream::find($streamId);
        if (!$stream || !$stream->enabled) {
            return false;
        }

        return true;
    }

    /**
     * Get SRT statistics
     */
    public function getStreamStats($streamId)
    {
        $stats = Redis::get("srt:stats:stream:{$streamId}");

        if ($stats) {
            return json_decode($stats, true);
        }

        return [
            'stream_id' => $streamId,
            'connections' => 0,
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'packets_sent' => 0,
            'packets_received' => 0,
            'packets_lost' => 0,
            'rtt' => 0,
            'bandwidth' => 0,
        ];
    }

    /**
     * Update SRT statistics
     */
    public function updateStats($streamId, $stats)
    {
        $key = "srt:stats:stream:{$streamId}";
        Redis::setex($key, 300, json_encode($stats)); // 5 minutes TTL

        // Also store in database for historical analysis
        \DB::table('stream_health_logs')->insert([
            'stream_id' => $streamId,
            'protocol' => 'srt',
            'status' => 'online',
            'metrics' => json_encode($stats),
            'checked_at' => now(),
        ]);
    }

    /**
     * Generate SRT server configuration
     */
    public function generateServerConfig($streams = null)
    {
        if (!$streams) {
            $streams = Stream::where('srt_enabled', true)
                ->where('enabled', true)
                ->get();
        }

        $config = [
            'version' => '1.0',
            'srt' => [
                'enabled' => true,
                'default_latency' => $this->defaultLatency,
                'max_bandwidth' => 0,
                'encryption' => [
                    'enabled' => true,
                    'pbkeylen' => $this->keyLength,
                ],
            ],
            'listeners' => [],
        ];

        foreach ($streams as $stream) {
            $port = $this->getSRTPort($stream);
            $encryptionSettings = json_decode($stream->encryption_settings, true) ?? [];
            $srtConfig = $encryptionSettings['srt'] ?? [];

            $config['listeners'][] = [
                'stream_id' => $stream->id,
                'port' => $port,
                'source_url' => $stream->source_url,
                'passphrase' => $srtConfig['passphrase'] ?? $this->generateStreamKey(0, $stream->id),
                'latency' => $srtConfig['latency'] ?? $this->defaultLatency,
                'maxbw' => $srtConfig['maxbw'] ?? 0,
                'max_connections' => $stream->max_connections ?? 0,
            ];
        }

        return $config;
    }

    /**
     * Test SRT connection
     */
    public function testConnection($host, $port, $streamId = null)
    {
        try {
            // Use srt-live-transmit or ffmpeg to test
            $url = "srt://{$host}:{$port}";
            if ($streamId) {
                $url .= "?streamid={$streamId}";
            }

            // Simple connectivity test using fsockopen
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);

            if ($socket) {
                fclose($socket);
                return [
                    'success' => true,
                    'message' => 'SRT port is reachable',
                ];
            }

            return [
                'success' => false,
                'message' => "Connection failed: {$errstr} ({$errno})",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get encryption info for stream
     */
    public function getEncryptionInfo($stream)
    {
        $encryptionSettings = json_decode($stream->encryption_settings, true) ?? [];
        $srtConfig = $encryptionSettings['srt'] ?? [];

        return [
            'enabled' => $stream->srt_enabled,
            'algorithm' => 'AES-' . ($this->keyLength * 8),
            'key_length' => $this->keyLength,
            'passphrase_hint' => $srtConfig['passphrase'] ? substr($srtConfig['passphrase'], 0, 8) . '...' : null,
            'latency' => $srtConfig['latency'] ?? $this->defaultLatency,
        ];
    }

    /**
     * Enable SRT for stream
     */
    public function enableForStream($stream, $options = [])
    {
        $config = $this->configureStream($stream, $options);

        // Generate SRT URLs for all active subscribers
        $subscribers = $stream->subscribers()->whereHas('activeSubscription')->get();

        $urls = [];
        foreach ($subscribers as $subscriber) {
            $urls[$subscriber->id] = $this->createSRTUrl($stream, $subscriber);
        }

        return [
            'success' => true,
            'config' => $config,
            'port' => $this->getSRTPort($stream),
            'subscriber_urls' => $urls,
        ];
    }

    /**
     * Disable SRT for stream
     */
    public function disableForStream($stream)
    {
        $stream->update([
            'srt_enabled' => false,
            'protocol' => 'rtmp', // Fallback to RTMP
        ]);

        return [
            'success' => true,
            'message' => 'SRT disabled for stream',
        ];
    }
}
