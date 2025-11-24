<?php

namespace App\Http\Middleware;

use App\Services\DeviceFingerprintService;
use Closure;

/**
 * Device Lock Middleware
 *
 * Validates device fingerprints and enforces device locking
 * Phase 1: Foundation - Security Hardening
 */
class DeviceLockMiddleware
{
    private $deviceService;

    public function __construct()
    {
        $this->deviceService = new DeviceFingerprintService();
    }

    /**
     * Handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Get subscriber from session or request
        $subscriberId = $request->session()->get('subscriber_id') ?? $request->input('subscriber_id');

        if (!$subscriberId) {
            return $this->unauthorizedResponse('Subscriber not authenticated');
        }

        // Get device fingerprint from request
        $fingerprint = $this->extractFingerprint($request);

        if (!$fingerprint) {
            return $this->unauthorizedResponse('Device fingerprint required');
        }

        try {
            // Validate device
            $validation = $this->deviceService->validateDevice($subscriberId, $fingerprint);

            if (!$validation['allowed']) {
                // Log violation
                \DeviceViolation::create([
                    'device_fingerprint_id' => $validation['device_id'] ?? null,
                    'subscriber_id' => $subscriberId,
                    'violation_type' => $validation['reason'] ?? 'unauthorized_device',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => json_encode([
                        'path' => $request->path(),
                        'method' => $request->method(),
                        'validation' => $validation,
                    ]),
                ]);

                return $this->unauthorizedResponse($validation['message'] ?? 'Device not authorized');
            }

            // Add device info to request for downstream use
            $request->attributes->set('device_id', $validation['device_id']);
            $request->attributes->set('device_fingerprint', $fingerprint);

        } catch (\Exception $e) {
            \Log::error('Device validation error: ' . $e->getMessage(), [
                'subscriber_id' => $subscriberId,
                'ip' => $request->ip(),
            ]);

            return $this->unauthorizedResponse('Device validation failed');
        }

        return $next($request);
    }

    /**
     * Extract device fingerprint from request
     */
    private function extractFingerprint($request)
    {
        // Check headers first
        $fingerprint = $request->header('X-Device-Fingerprint');

        // Fall back to request body
        if (!$fingerprint) {
            $fingerprint = $request->input('device_fingerprint');
        }

        // Fall back to session
        if (!$fingerprint) {
            $fingerprint = $request->session()->get('device_fingerprint');
        }

        return $fingerprint;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse($message)
    {
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'success' => false,
                'error' => $message,
                'code' => 'DEVICE_UNAUTHORIZED',
            ], 403);
        }

        return redirect()->route('subscriber.device-blocked')
            ->with('error', $message);
    }

    /**
     * Middleware for specific stream access
     * Validates device and checks concurrent streams
     */
    public function handleStreamAccess($request, Closure $next, $streamId = null)
    {
        $subscriberId = $request->session()->get('subscriber_id');
        $fingerprint = $this->extractFingerprint($request);
        $streamId = $streamId ?? $request->input('stream_id');

        if (!$subscriberId || !$fingerprint || !$streamId) {
            return $this->unauthorizedResponse('Missing required parameters');
        }

        try {
            // Get subscription
            $subscription = \Subscription::where('subscriber_id', $subscriberId)
                ->where('status', 'active')
                ->first();

            if (!$subscription) {
                return $this->unauthorizedResponse('No active subscription');
            }

            // Validate device
            $validation = $this->deviceService->validateDevice($subscriberId, $fingerprint);

            if (!$validation['allowed']) {
                return $this->unauthorizedResponse($validation['message']);
            }

            // Check concurrent stream limit
            $device = \DeviceFingerprint::find($validation['device_id']);
            $activeStreams = \DeviceSession::where('device_fingerprint_id', $device->id)
                ->where('is_active', true)
                ->count();

            $maxConcurrent = $subscription->package->max_connections ?? 1;

            if ($activeStreams >= $maxConcurrent) {
                // Log violation
                \DeviceViolation::create([
                    'device_fingerprint_id' => $device->id,
                    'subscriber_id' => $subscriberId,
                    'violation_type' => 'concurrent_stream_limit',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => json_encode([
                        'active_streams' => $activeStreams,
                        'max_allowed' => $maxConcurrent,
                        'stream_id' => $streamId,
                    ]),
                ]);

                return $this->unauthorizedResponse("Concurrent stream limit reached ({$maxConcurrent} streams)");
            }

            // Create session
            $session = \DeviceSession::create([
                'device_fingerprint_id' => $device->id,
                'subscriber_id' => $subscriberId,
                'subscription_id' => $subscription->id,
                'stream_id' => $streamId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_active' => true,
                'started_at' => now(),
            ]);

            // Add session info to request
            $request->attributes->set('device_session_id', $session->id);

        } catch (\Exception $e) {
            \Log::error('Stream access validation error: ' . $e->getMessage(), [
                'subscriber_id' => $subscriberId,
                'stream_id' => $streamId,
            ]);

            return $this->unauthorizedResponse('Stream access validation failed');
        }

        return $next($request);
    }
}
