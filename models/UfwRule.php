<?php
/**
 * UFW Rule Model
 * Manages UFW firewall rules with lockout prevention
 */
class UfwRule extends FosStreaming
{
    protected $table = 'ufw_rules';
    public $timestamps = true;

    protected $fillable = [
        'port',
        'protocol',
        'action',
        'direction',
        'from_ip',
        'to_ip',
        'interface',
        'description',
        'is_protected',
        'is_default',
        'enabled',
        'priority',
        'applied'
    ];

    protected $casts = [
        'port' => 'integer',
        'is_protected' => 'boolean',
        'is_default' => 'boolean',
        'enabled' => 'boolean',
        'applied' => 'boolean',
        'priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get all enabled rules ordered by priority
     */
    public static function getEnabledRules()
    {
        return self::where('enabled', true)
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get all protected rules (cannot be deleted)
     */
    public static function getProtectedRules()
    {
        return self::where('is_protected', true)->get();
    }

    /**
     * Get all default/seeded rules
     */
    public static function getDefaultRules()
    {
        return self::where('is_default', true)
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Get custom (non-default) rules
     */
    public static function getCustomRules()
    {
        return self::where('is_default', false)
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Get rules that should be applied but haven't been
     */
    public static function getPendingRules()
    {
        return self::where('enabled', true)
            ->where('applied', false)
            ->get();
    }

    /**
     * Build UFW command for this rule
     */
    public function toUfwCommand(): string
    {
        $parts = ['sudo', 'ufw'];

        // Add action
        $parts[] = $this->action;

        // Add direction if specified
        if ($this->direction !== 'both') {
            $parts[] = $this->direction;
        }

        // Add interface if specified
        if ($this->interface) {
            $parts[] = "on {$this->interface}";
        }

        // Add from IP if specified
        if ($this->from_ip) {
            $parts[] = "from {$this->from_ip}";
        }

        // Add to IP if specified
        if ($this->to_ip) {
            $parts[] = "to {$this->to_ip}";
        }

        // Add port
        $parts[] = "port {$this->port}";

        // Add protocol if not 'both'
        if ($this->protocol !== 'both') {
            $parts[] = "proto {$this->protocol}";
        }

        return implode(' ', $parts);
    }

    /**
     * Mark rule as applied
     */
    public function markApplied()
    {
        $this->applied = true;
        $this->save();
    }

    /**
     * Mark rule as not applied
     */
    public function markUnapplied()
    {
        $this->applied = false;
        $this->save();
    }

    /**
     * Check if rule can be deleted (not protected)
     */
    public function canDelete(): bool
    {
        return !$this->is_protected;
    }

    /**
     * Safe delete - only if not protected
     */
    public function safeDelete(): bool
    {
        if (!$this->canDelete()) {
            return false;
        }

        return $this->delete();
    }

    /**
     * Get human-readable rule description
     */
    public function getReadableRule(): string
    {
        $parts = [];

        // Action
        $parts[] = strtoupper($this->action);

        // Direction
        if ($this->direction !== 'both') {
            $parts[] = strtoupper($this->direction);
        }

        // Port/Protocol
        $parts[] = "{$this->port}/{$this->protocol}";

        // From IP
        if ($this->from_ip) {
            $parts[] = "from {$this->from_ip}";
        }

        // To IP
        if ($this->to_ip) {
            $parts[] = "to {$this->to_ip}";
        }

        // Interface
        if ($this->interface) {
            $parts[] = "on {$this->interface}";
        }

        return implode(' ', $parts);
    }
}
