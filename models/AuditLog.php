<?php

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false; // Only created_at

    protected $fillable = [
        'user_id',
        'user_type',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'method',
        'url',
        'status',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'user_id');
    }

    /**
     * Get the entity that was affected
     */
    public function entity()
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /**
     * Scope to filter by user type
     */
    public function scopeByUserType($query, $type)
    {
        return $query->where('user_type', $type);
    }

    /**
     * Scope to filter by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by entity type
     */
    public function scopeByEntityType($query, $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope to get recent logs
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get changes summary
     */
    public function getChangesSummaryAttribute()
    {
        if (!$this->old_values || !$this->new_values) {
            return null;
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Log an audit event
     */
    public static function log($action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null, $description = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'user_type' => auth()->guard()->name ?? 'admin',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'status' => 'success',
            'description' => $description,
        ]);
    }

    /**
     * Log a failed action
     */
    public static function logFailed($action, $description, $entityType = null, $entityId = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'user_type' => auth()->guard()->name ?? 'admin',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'status' => 'failed',
            'description' => $description,
        ]);
    }

    /**
     * Get formatted action name
     */
    public function getFormattedActionAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->action));
    }

    /**
     * Get formatted entity name
     */
    public function getFormattedEntityAttribute()
    {
        if ($this->entity_type && $this->entity_id) {
            return "{$this->entity_type} #{$this->entity_id}";
        }
        return $this->entity_type;
    }
}
