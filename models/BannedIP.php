<?php
/**
 * BannedIP Model
 * Manages permanently banned/whitelisted IPs
 */
class BannedIP extends FosStreaming
{
    protected $table = 'banned_ips';
    public $timestamps = true;

    protected $fillable = [
        'ip_address',
        'type',          // 'banned' or 'whitelisted'
        'reason',
        'banned_by',
        'expires_at',
        'permanent'
    ];

    protected $casts = [
        'permanent' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Check if IP is currently banned
     */
    public static function isBanned(string $ip): bool
    {
        $record = self::where('ip_address', $ip)
            ->where('type', 'banned')
            ->where(function($query) {
                $query->where('permanent', true)
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $record !== null;
    }

    /**
     * Check if IP is whitelisted
     */
    public static function isWhitelisted(string $ip): bool
    {
        return self::where('ip_address', $ip)
            ->where('type', 'whitelisted')
            ->exists();
    }

    /**
     * Add IP to ban list
     */
    public static function ban(string $ip, string $reason, ?int $duration = null, ?string $bannedBy = null): self
    {
        // Remove any existing records for this IP
        self::where('ip_address', $ip)->delete();

        return self::create([
            'ip_address' => $ip,
            'type' => 'banned',
            'reason' => $reason,
            'banned_by' => $bannedBy ?? 'system',
            'expires_at' => $duration ? now()->addSeconds($duration) : null,
            'permanent' => $duration === null
        ]);
    }

    /**
     * Add IP to whitelist
     */
    public static function whitelist(string $ip, string $reason, ?string $addedBy = null): self
    {
        // Remove any existing records for this IP
        self::where('ip_address', $ip)->delete();

        return self::create([
            'ip_address' => $ip,
            'type' => 'whitelisted',
            'reason' => $reason,
            'banned_by' => $addedBy ?? 'system',
            'permanent' => true
        ]);
    }

    /**
     * Remove IP from ban/whitelist
     */
    public static function unban(string $ip): bool
    {
        return self::where('ip_address', $ip)->delete() > 0;
    }

    /**
     * Clean up expired bans
     */
    public static function cleanExpired(): int
    {
        return self::where('type', 'banned')
            ->where('permanent', false)
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Get all currently banned IPs
     */
    public static function getActiveBans(): array
    {
        return self::where('type', 'banned')
            ->where(function($query) {
                $query->where('permanent', true)
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get all whitelisted IPs
     */
    public static function getWhitelisted(): array
    {
        return self::where('type', 'whitelisted')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
