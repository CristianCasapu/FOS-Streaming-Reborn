<?php

use Illuminate\Database\Eloquent\Model;

/**
 * StreamToken Model
 *
 * Secure, time-limited tokens for streaming authentication.
 * Supports staff, subscriber, and API token types.
 *
 * @property int $id
 * @property string $token
 * @property int $stream_id
 * @property string $type (staff, subscriber, api)
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $metadata (JSON)
 * @property string $created_at
 * @property string $expires_at
 * @property string|null $last_used_at
 * @property int $use_count
 * @property bool $is_revoked
 */
class StreamToken extends Model
{
    protected $table = 'stream_tokens';

    public $timestamps = false;

    protected $fillable = [
        'token',
        'stream_id',
        'type',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
        'expires_at',
        'last_used_at',
        'use_count',
        'is_revoked',
    ];

    protected $casts = [
        'stream_id' => 'integer',
        'user_id' => 'integer',
        'use_count' => 'integer',
        'is_revoked' => 'boolean',
    ];

    protected $hidden = [
        'token', // Hide token in JSON responses for security
    ];

    /**
     * Relationship to Stream
     */
    public function stream()
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }

    /**
     * Relationship to Subscriber (when type is 'subscriber')
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'user_id');
    }

    /**
     * Check if token is valid (not expired and not revoked)
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if (strtotime($this->expires_at) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Check if token is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Get remaining time in seconds
     *
     * @return int
     */
    public function getRemainingTime(): int
    {
        $remaining = strtotime($this->expires_at) - time();
        return max(0, $remaining);
    }

    /**
     * Revoke this token
     *
     * @return bool
     */
    public function revoke(): bool
    {
        $this->is_revoked = true;
        return $this->save();
    }

    /**
     * Update last used timestamp
     *
     * @return void
     */
    public function touch(): void
    {
        $this->last_used_at = date('Y-m-d H:i:s');
        $this->use_count += 1;
        $this->save();
    }

    /**
     * Get decoded metadata
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return json_decode($this->metadata, true) ?? [];
    }

    /**
     * Scope for valid (non-expired, non-revoked) tokens
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'));
    }

    /**
     * Scope for staff tokens
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStaff($query)
    {
        return $query->where('type', 'staff');
    }

    /**
     * Scope for subscriber tokens
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubscriber($query)
    {
        return $query->where('type', 'subscriber');
    }

    /**
     * Scope for API tokens
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApi($query)
    {
        return $query->where('type', 'api');
    }

    /**
     * Scope for expired tokens
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', date('Y-m-d H:i:s'));
    }

    /**
     * Generate a new token string
     *
     * @return string
     */
    public static function generateTokenString(): string
    {
        return bin2hex(random_bytes(32));
    }
}
