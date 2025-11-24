<?php

namespace App\Services;

use DeviceFingerprint;
use DeviceBinding;
use DeviceViolation;
use DeviceSession;
use Setting;

class DeviceFingerprintService
{
    /**
     * Generate fingerprint hash from request data
     */
    public function generateFingerprint($request)
    {
        $components = $this->extractComponents($request);
        return DeviceFingerprint::generateDeviceId($components);
    }

    /**
     * Extract fingerprint components from request
     */
    private function extractComponents($request)
    {
        // Get from headers and request data
        return [
            'user_agent' => $request->userAgent(),
            'accept_headers' => $request->header('Accept'),
            'accept_language' => $request->header('Accept-Language'),
            'accept_encoding' => $request->header('Accept-Encoding'),
            'ip_address' => $request->ip(),
            'timezone' => $request->input('device.timezone'),
            'screen_resolution' => $request->input('device.screen'),
            'color_depth' => $request->input('device.colorDepth'),
            'platform' => $request->input('device.platform'),
            'canvas_fingerprint' => $request->input('device.canvasFingerprint'),
            'webgl_fingerprint' => $request->input('device.webglFingerprint'),
            'audio_fingerprint' => $request->input('device.audioFingerprint'),
            'fonts' => $request->input('device.fonts', []),
            'plugins' => $request->input('device.plugins', []),
            'languages' => $request->input('device.languages', []),
        ];
    }

    /**
     * Find or create device fingerprint
     */
    public function findOrCreate($request, $subscriber)
    {
        $components = $this->extractComponents($request);
        $deviceId = DeviceFingerprint::generateDeviceId($components);

        $fingerprint = DeviceFingerprint::where('device_id', $deviceId)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if (!$fingerprint) {
            // Check for similar devices (possible fingerprint evasion)
            $similar = $this->findSimilarDevice($components, $subscriber);

            if ($similar && $similar->is_blocked) {
                // Blocked device trying to evade detection
                $this->logViolation($subscriber->id, null, 'suspicious_pattern', [
                    'reason' => 'Similar to blocked device',
                    'blocked_device_id' => $similar->id
                ]);

                throw new \Exception('Device blocked due to suspicious activity');
            }

            // Create new fingerprint
            $fingerprint = DeviceFingerprint::create([
                'subscriber_id' => $subscriber->id,
                'device_id' => $deviceId,
                'hardware_id' => $components['hardware_id'] ?? null,
                'browser_fingerprint' => json_encode($components),
                'canvas_fingerprint' => $components['canvas_fingerprint'],
                'webgl_fingerprint' => $components['webgl_fingerprint'],
                'audio_fingerprint' => $components['audio_fingerprint'],
                'timezone' => $components['timezone'],
                'screen_resolution' => $components['screen_resolution'],
                'platform' => $components['platform'],
                'user_agent' => $components['user_agent']
            ]);

            // Check daily registration limit
            $this->checkDailyRegistrationLimit($subscriber);
        }

        // Update last seen
        $fingerprint->updateLastSeen();

        return $fingerprint;
    }

    /**
     * Find similar device (for evasion detection)
     */
    private function findSimilarDevice($components, $subscriber)
    {
        $devices = DeviceFingerprint::where('subscriber_id', $subscriber->id)->get();

        foreach ($devices as $device) {
            $similarity = $this->calculateSimilarity($device, $components);
            if ($similarity > 0.8) { // 80% similarity threshold
                return $device;
            }
        }

        return null;
    }

    /**
     * Calculate similarity between device and components
     */
    public function calculateSimilarity($device, $components)
    {
        $score = 0;
        $weights = [
            'canvas_fingerprint' => 0.3,
            'webgl_fingerprint' => 0.3,
            'audio_fingerprint' => 0.2,
            'timezone' => 0.05,
            'screen_resolution' => 0.05,
            'platform' => 0.05,
            'user_agent' => 0.05
        ];

        foreach ($weights as $field => $weight) {
            if (isset($components[$field]) && $device->$field === $components[$field]) {
                $score += $weight;
            }
        }

        return $score;
    }

    /**
     * Validate device for subscription
     */
    public function validateDevice($subscription, $fingerprint)
    {
        // Check if device is blocked
        if ($fingerprint->is_blocked) {
            throw new \Exception('Device is blocked');
        }

        // Find or create binding
        $binding = DeviceBinding::where('subscription_id', $subscription->id)
            ->where('device_fingerprint_id', $fingerprint->id)
            ->first();

        if (!$binding) {
            // Check device limit
            $deviceCount = DeviceBinding::where('subscription_id', $subscription->id)
                ->where('is_active', true)
                ->count();

            $maxDevices = $subscription->max_devices ?? 3;

            if ($deviceCount >= $maxDevices) {
                $this->logViolation(
                    $subscription->subscriber_id,
                    $subscription->id,
                    'concurrent_limit',
                    ['current' => $deviceCount, 'max' => $maxDevices]
                );

                throw new \Exception('Device limit exceeded');
            }

            // Auto-register device
            $binding = $this->registerDevice($subscription, $fingerprint);
        }

        // Validate binding
        if (!$binding->isValid()) {
            throw new \Exception('Device binding invalid or expired');
        }

        // Mark as validated
        $binding->markAsValidated();

        return $binding;
    }

    /**
     * Register new device
     */
    public function registerDevice($subscription, $fingerprint, $request = null)
    {
        $binding = DeviceBinding::create([
            'subscription_id' => $subscription->id,
            'device_fingerprint_id' => $fingerprint->id,
            'device_name' => $this->generateDeviceName($fingerprint),
            'ip_address' => $request ? $request->ip() : null,
            'is_primary' => DeviceBinding::where('subscription_id', $subscription->id)->count() === 0,
            'is_active' => true
        ]);

        // Set IP range if home network
        if ($request && $request->input('device.homeNetwork')) {
            $binding->setIpRange($request->ip() . '/24');
        }

        return $binding;
    }

    /**
     * Generate friendly device name
     */
    private function generateDeviceName($fingerprint)
    {
        $platform = $fingerprint->platform ?? 'Unknown';
        $screen = $fingerprint->screen_resolution ?? '';

        // Parse user agent for browser
        $browser = 'Browser';
        if (stripos($fingerprint->user_agent, 'chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($fingerprint->user_agent, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($fingerprint->user_agent, 'safari') !== false) {
            $browser = 'Safari';
        } elseif (stripos($fingerprint->user_agent, 'edge') !== false) {
            $browser = 'Edge';
        }

        return trim("{$platform} {$browser} {$screen}");
    }

    /**
     * Create streaming session
     */
    public function createSession($binding, $streamId = null)
    {
        // Check concurrent streams
        $activeSessions = DeviceSession::where('device_binding_id', $binding->id)
            ->where('is_active', true)
            ->count();

        $maxConcurrent = $binding->subscription->max_concurrent_streams ?? 1;

        if ($activeSessions >= $maxConcurrent) {
            // Check grace period for stream switching
            $lastSession = DeviceSession::where('device_binding_id', $binding->id)
                ->where('is_active', true)
                ->orderBy('last_activity', 'desc')
                ->first();

            $gracePeriod = Setting::getValue('device_concurrent_stream_grace_seconds', 30);

            if ($lastSession && $lastSession->last_activity->diffInSeconds(now()) > $gracePeriod) {
                $this->logViolation(
                    $binding->subscription->subscriber_id,
                    $binding->subscription_id,
                    'concurrent_limit',
                    ['active' => $activeSessions, 'max' => $maxConcurrent]
                );

                throw new \Exception('Concurrent stream limit exceeded');
            }

            // End previous session (stream switching)
            $lastSession->update([
                'is_active' => false,
                'ended_at' => now()
            ]);
        }

        // Create new session
        $session = DeviceSession::create([
            'device_binding_id' => $binding->id,
            'stream_id' => $streamId,
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'started_at' => now(),
            'last_activity' => now(),
            'is_active' => true
        ]);

        return $session;
    }

    /**
     * Validate session
     */
    public function validateSession($sessionToken)
    {
        $session = DeviceSession::where('session_token', $sessionToken)
            ->where('is_active', true)
            ->first();

        if (!$session) {
            throw new \Exception('Invalid session');
        }

        // Check session timeout
        $timeout = Setting::getValue('device_session_timeout_minutes', 60);

        if ($session->last_activity->diffInMinutes(now()) > $timeout) {
            $session->update([
                'is_active' => false,
                'ended_at' => now()
            ]);

            throw new \Exception('Session expired');
        }

        // Update activity
        $session->update(['last_activity' => now()]);

        return $session;
    }

    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity($subscriber, $request)
    {
        // Check rapid device switching
        $recentDevices = DeviceFingerprint::where('subscriber_id', $subscriber->id)
            ->where('created_at', '>', now()->subHours(1))
            ->count();

        if ($recentDevices > 3) {
            $this->logViolation(
                $subscriber->id,
                null,
                'suspicious_pattern',
                ['reason' => 'Rapid device switching', 'count' => $recentDevices]
            );
        }

        // Check IP jumping
        $recentIps = Activity::where('user_id', $subscriber->id)
            ->where('date_start', '>', now()->subMinutes(30))
            ->distinct('user_ip')
            ->count('user_ip');

        if ($recentIps > 5) {
            $this->logViolation(
                $subscriber->id,
                null,
                'ip_jump',
                ['reason' => 'Multiple IPs in short time', 'count' => $recentIps]
            );
        }

        // Check geolocation jumps
        $this->checkGeolocationJumps($subscriber);
    }

    /**
     * Check for impossible geolocation jumps
     */
    private function checkGeolocationJumps($subscriber)
    {
        $sessions = DeviceSession::whereHas('deviceBinding', function($q) use ($subscriber) {
            $q->whereHas('subscription', function($sq) use ($subscriber) {
                $sq->where('subscriber_id', $subscriber->id);
            });
        })
        ->where('started_at', '>', now()->subHours(2))
        ->orderBy('started_at')
        ->get();

        $lastLocation = null;
        $lastTime = null;

        foreach ($sessions as $session) {
            if ($lastLocation && $session->geolocation) {
                $distance = $this->calculateDistance(
                    $lastLocation['lat'],
                    $lastLocation['lon'],
                    $session->geolocation['lat'],
                    $session->geolocation['lon']
                );

                $timeDiff = $lastTime->diffInHours($session->started_at);

                // Impossible travel speed (> 1000 km/h)
                if ($timeDiff > 0 && ($distance / $timeDiff) > 1000) {
                    $this->logViolation(
                        $subscriber->id,
                        null,
                        'location_mismatch',
                        [
                            'reason' => 'Impossible travel speed',
                            'distance' => $distance,
                            'time' => $timeDiff
                        ]
                    );
                }
            }

            $lastLocation = $session->geolocation;
            $lastTime = $session->started_at;
        }
    }

    /**
     * Check daily device registration limit
     */
    private function checkDailyRegistrationLimit($subscriber)
    {
        $maxPerDay = Setting::getValue('device_max_registration_per_day', 5);

        $todayCount = DeviceFingerprint::where('subscriber_id', $subscriber->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayCount > $maxPerDay) {
            $this->logViolation(
                $subscriber->id,
                null,
                'suspicious_pattern',
                ['reason' => 'Too many devices registered', 'count' => $todayCount]
            );

            throw new \Exception('Daily device registration limit exceeded');
        }
    }

    /**
     * Log violation
     */
    private function logViolation($subscriberId, $subscriptionId, $type, $details)
    {
        DeviceViolation::create([
            'subscriber_id' => $subscriberId,
            'subscription_id' => $subscriptionId,
            'violation_type' => $type,
            'severity' => $this->getViolationSeverity($type),
            'details' => json_encode($details),
            'ip_address' => request()->ip(),
            'device_info' => request()->userAgent()
        ]);
    }

    /**
     * Get violation severity
     */
    private function getViolationSeverity($type)
    {
        $severities = [
            'concurrent_limit' => 'medium',
            'location_mismatch' => 'high',
            'device_mismatch' => 'high',
            'sharing_detected' => 'critical',
            'suspicious_pattern' => 'high',
            'ip_jump' => 'medium',
            'rapid_switching' => 'medium'
        ];

        return $severities[$type] ?? 'medium';
    }

    /**
     * Calculate distance between coordinates
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Clean up old fingerprints
     */
    public function cleanup()
    {
        $ttlDays = Setting::getValue('device_fingerprint_ttl_days', 365);

        // Remove old, inactive fingerprints
        DeviceFingerprint::where('last_seen', '<', now()->subDays($ttlDays))
            ->whereDoesntHave('bindings', function($q) {
                $q->where('is_active', true);
            })
            ->delete();

        // Remove old violations
        DeviceViolation::where('created_at', '<', now()->subDays(90))
            ->delete();

        // Remove expired sessions
        DeviceSession::where('is_active', false)
            ->where('ended_at', '<', now()->subDays(7))
            ->delete();
    }
}