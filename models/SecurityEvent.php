<?php
/**
 * SecurityEvent Model
 * Handles security events tracking
 */
class SecurityEvent extends FosStreaming {

    protected $table = 'security_events';

    /**
     * Get severity badge color
     */
    public function getSeverityBadgeAttribute()
    {
        $badges = [
            'low' => 'success',
            'medium' => 'info',
            'high' => 'warning',
            'critical' => 'danger'
        ];

        return $badges[$this->severity] ?? 'default';
    }

    /**
     * Get severity icon
     */
    public function getSeverityIconAttribute()
    {
        $icons = [
            'low' => 'fa-info-circle',
            'medium' => 'fa-exclamation-circle',
            'high' => 'fa-exclamation-triangle',
            'critical' => 'fa-times-circle'
        ];

        return $icons[$this->severity] ?? 'fa-question-circle';
    }

    /**
     * Scope: Recent events (last 24 hours)
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>', date('Y-m-d H:i:s', strtotime("-{$hours} hours")));
    }

    /**
     * Scope: By severity
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: Critical events
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
}
