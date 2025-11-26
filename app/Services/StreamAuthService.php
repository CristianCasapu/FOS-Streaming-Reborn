<?php

namespace App\Services;

use Stream;
use Setting;

/**
 * StreamAuthService - Enterprise-Grade Streaming Authentication
 *
 * Provides secure, time-limited token-based authentication for streaming access.
 * Supports both staff (admin) and subscriber access with different security levels.
 *
 * Token Types:
 * - staff: For admin panel stream preview (short-lived, IP-bound)
 * - subscriber: For subscriber streaming (session-based, device-bound)
 * - api: For API integrations (longer-lived, rate-limited)
 */
class StreamAuthService
{
    /**
     * Token expiration times in seconds
     */
    private const TOKEN_EXPIRY = [
        'staff' => 3600,        // 1 hour for staff preview
        'subscriber' => 86400,   // 24 hours for subscribers
        'api' => 604800,        // 7 days for API integrations
    ];

    /**
     * Generate a secure streaming token
     *
     * @param int $streamId Stream ID
     * @param string $type Token type (staff, subscriber, api)
     * @param int|null $userId User/Subscriber ID
     * @param string|null $ipAddress Client IP address (for binding)
     * @param array $metadata Additional token metadata
     * @return array Token data including token string and expiry
     */
    public function generateToken(
        int $streamId,
        string $type = 'staff',
        ?int $userId = null,
        ?string $ipAddress = null,
        array $metadata = []
    ): array {
        // Validate stream exists
        $stream = Stream::find($streamId);
        if (!$stream) {
            throw new \Exception('Stream not found');
        }

        // Generate cryptographically secure token
        $token = bin2hex(random_bytes(32)); // 64-character hex token
        $expiresAt = time() + (self::TOKEN_EXPIRY[$type] ?? 3600);

        // Create token record
        $tokenData = [
            'token' => $token,
            'stream_id' => $streamId,
            'type' => $type,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'metadata' => json_encode($metadata),
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'last_used_at' => null,
            'use_count' => 0,
            'is_revoked' => false,
        ];

        // Store token in database
        \StreamToken::create($tokenData);

        return [
            'token' => $token,
            'stream_id' => $streamId,
            'type' => $type,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'expires_in' => self::TOKEN_EXPIRY[$type] ?? 3600,
        ];
    }

    /**
     * Validate a streaming token
     *
     * @param string $token Token string
     * @param int $streamId Expected stream ID
     * @param string|null $ipAddress Client IP address for validation
     * @return array Validation result with token data
     */
    public function validateToken(string $token, int $streamId, ?string $ipAddress = null): array
    {
        $streamToken = \StreamToken::where('token', $token)
            ->where('stream_id', $streamId)
            ->where('is_revoked', false)
            ->first();

        if (!$streamToken) {
            return [
                'valid' => false,
                'error' => 'Token not found or revoked',
                'code' => 'TOKEN_INVALID'
            ];
        }

        // Check expiration
        if (strtotime($streamToken->expires_at) < time()) {
            return [
                'valid' => false,
                'error' => 'Token expired',
                'code' => 'TOKEN_EXPIRED'
            ];
        }

        // For staff tokens, validate IP if bound
        if ($streamToken->type === 'staff' && $streamToken->ip_address) {
            if ($ipAddress && $streamToken->ip_address !== $ipAddress) {
                // Log potential token theft
                $this->logSecurityEvent('ip_mismatch', $streamToken, $ipAddress);
                return [
                    'valid' => false,
                    'error' => 'Token IP mismatch',
                    'code' => 'IP_MISMATCH'
                ];
            }
        }

        // Update usage stats
        $streamToken->last_used_at = date('Y-m-d H:i:s');
        $streamToken->use_count += 1;
        $streamToken->save();

        return [
            'valid' => true,
            'token_data' => [
                'type' => $streamToken->type,
                'user_id' => $streamToken->user_id,
                'stream_id' => $streamToken->stream_id,
                'metadata' => json_decode($streamToken->metadata, true),
            ]
        ];
    }

    /**
     * Generate secure streaming URLs with embedded token
     *
     * @param int $streamId Stream ID
     * @param string $type Token type
     * @param int|null $userId User ID
     * @return array URLs for different streaming protocols
     */
    public function generateSecureUrls(int $streamId, string $type = 'staff', ?int $userId = null): array
    {
        $settings = Setting::first();
        $streamingPort = $settings->streaming_port ?? 8000;
        $rtmpPort = $settings->rtmp_port ?? 1935;

        // Get the server hostname
        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Remove port if present
        $hostname = preg_replace('/:\d+$/', '', $hostname);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // Generate token
        $tokenData = $this->generateToken($streamId, $type, $userId, $ipAddress);
        $token = $tokenData['token'];

        // Build secure URLs using query parameters for secure-stream.php compatibility
        // These URLs will be proxied through nginx to secure-stream.php
        $baseUrl = "/secure-stream.php";

        return [
            'success' => true,
            'urls' => [
                'hls' => "{$baseUrl}?token={$token}&stream={$streamId}&format=hls",
                'dash' => "{$baseUrl}?token={$token}&stream={$streamId}&format=dash",
                'direct' => "{$baseUrl}?token={$token}&stream={$streamId}&format=direct",
            ],
            'token' => $token,
            'expires_at' => $tokenData['expires_at'],
            'expires_in' => $tokenData['expires_in'],
        ];
    }

    /**
     * Revoke a token
     *
     * @param string $token Token to revoke
     * @return bool Success status
     */
    public function revokeToken(string $token): bool
    {
        $streamToken = \StreamToken::where('token', $token)->first();
        if ($streamToken) {
            $streamToken->is_revoked = true;
            $streamToken->save();
            return true;
        }
        return false;
    }

    /**
     * Revoke all tokens for a user
     *
     * @param int $userId User ID
     * @param string|null $type Optional token type filter
     * @return int Number of tokens revoked
     */
    public function revokeUserTokens(int $userId, ?string $type = null): int
    {
        $query = \StreamToken::where('user_id', $userId)
            ->where('is_revoked', false);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->update(['is_revoked' => true]);
    }

    /**
     * Revoke all tokens for a specific stream
     *
     * @param int $streamId Stream ID
     * @param string|null $type Optional token type filter
     * @return int Number of tokens revoked
     */
    public function revokeAllStreamTokens(int $streamId, ?string $type = null): int
    {
        $query = \StreamToken::where('stream_id', $streamId)
            ->where('is_revoked', false);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->update(['is_revoked' => true]);
    }

    /**
     * Cleanup expired tokens
     *
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens(): int
    {
        return \StreamToken::where('expires_at', '<', date('Y-m-d H:i:s'))
            ->orWhere('is_revoked', true)
            ->delete();
    }

    /**
     * Get active tokens for a user
     *
     * @param int $userId User ID
     * @param string|null $type Optional token type filter
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTokens(int $userId, ?string $type = null)
    {
        $query = \StreamToken::where('user_id', $userId)
            ->where('is_revoked', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'));

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Log security events
     *
     * @param string $event Event type
     * @param \StreamToken $token Token involved
     * @param string|null $ipAddress IP address
     */
    private function logSecurityEvent(string $event, $token, ?string $ipAddress = null): void
    {
        // Log to security_events table if it exists
        try {
            \DB::table('security_events')->insert([
                'event_type' => 'stream_token_' . $event,
                'severity' => 'warning',
                'user_id' => $token->user_id,
                'ip_address' => $ipAddress,
                'details' => json_encode([
                    'token_type' => $token->type,
                    'stream_id' => $token->stream_id,
                    'expected_ip' => $token->ip_address,
                    'actual_ip' => $ipAddress,
                ]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silently fail if table doesn't exist
            error_log("Security event logging failed: " . $e->getMessage());
        }
    }

    /**
     * Validate subscriber access to a stream
     *
     * @param int $subscriberId Subscriber ID
     * @param int $streamId Stream ID
     * @return array Validation result
     */
    public function validateSubscriberStreamAccess(int $subscriberId, int $streamId): array
    {
        $subscriber = \Subscriber::find($subscriberId);
        if (!$subscriber) {
            return ['valid' => false, 'error' => 'Subscriber not found'];
        }

        if (!$subscriber->enabled) {
            return ['valid' => false, 'error' => 'Account disabled'];
        }

        if (!$subscriber->hasValidAccess()) {
            return ['valid' => false, 'error' => 'No active subscription or trial'];
        }

        // Check if subscriber has access to this stream via their bouquets
        $stream = Stream::find($streamId);
        if (!$stream) {
            return ['valid' => false, 'error' => 'Stream not found'];
        }

        // Get all accessible bouquets for subscriber
        $accessibleBouquets = $subscriber->getAccessibleBouquetsAttribute();

        // Check if any bouquet contains this stream
        foreach ($accessibleBouquets as $bouquet) {
            if ($bouquet->hasStream($streamId)) {
                return [
                    'valid' => true,
                    'subscriber' => $subscriber,
                    'bouquet' => $bouquet,
                ];
            }
        }

        return ['valid' => false, 'error' => 'No access to this stream'];
    }
}
