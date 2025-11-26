<?php

use Illuminate\Database\Eloquent\Model;

class UserAgent extends Model
{
    protected $table = 'user_agents';

    protected $fillable = [
        'user_agent',
        'name',
        'version',
        'os',
        'hardware_type',
        'is_default',
        'enabled',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get only enabled user agents
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', 1);
    }

    /**
     * Scope to get only default user agent
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }

    /**
     * Get the default user agent
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', 1)->first();
    }

    /**
     * Set this user agent as default (and unset others)
     */
    public function setAsDefault(): bool
    {
        // Unset all other defaults
        static::where('is_default', 1)->update(['is_default' => 0]);

        // Set this one as default
        $this->is_default = true;
        return $this->save();
    }

    /**
     * Get a random enabled user agent
     */
    public static function getRandom(): ?self
    {
        return static::enabled()->inRandomOrder()->first();
    }

    /**
     * Get user agent string for use in FFmpeg/FFprobe
     * Returns default if set, otherwise random enabled, or fallback
     */
    public static function getForStreaming(): string
    {
        // First try to get default
        $userAgent = static::getDefault();

        if (!$userAgent) {
            // Fall back to random enabled
            $userAgent = static::getRandom();
        }

        if ($userAgent) {
            return $userAgent->user_agent;
        }

        // Ultimate fallback
        return 'FOS-Streaming/1.0';
    }

    /**
     * Streams using this user agent
     */
    public function streams()
    {
        return $this->hasMany(Stream::class, 'user_agent_id');
    }

    /**
     * Get display name (friendly name or truncated user agent)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        // Generate a name from the user agent
        if (str_contains($this->user_agent, 'VLC')) {
            return 'VLC ' . ($this->version ?? 'Player');
        }
        if (str_contains($this->user_agent, 'iPhone')) {
            return 'iPhone Safari ' . ($this->os ?? '');
        }
        if (str_contains($this->user_agent, 'Android')) {
            return 'Android ' . ($this->hardware_type ?? 'Device');
        }
        if (str_contains($this->user_agent, 'OTT Navigator')) {
            return 'OTT Navigator';
        }
        if (str_contains($this->user_agent, 'Smarters')) {
            return 'IPTV Smarters';
        }

        // Truncate long user agents
        return strlen($this->user_agent) > 50
            ? substr($this->user_agent, 0, 47) . '...'
            : $this->user_agent;
    }
}
