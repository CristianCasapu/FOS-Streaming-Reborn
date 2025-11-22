<?php
/**
 * SystemCommandLog Model
 * Tracks all system command executions for audit trail
 */

use Illuminate\Database\Eloquent\Model;

class SystemCommandLog extends Model
{
    protected $table = 'system_command_logs';

    protected $fillable = [
        'admin_id',
        'command',
        'description',
        'output',
        'exit_code',
        'execution_time',
        'success',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'success' => 'boolean',
        'exit_code' => 'integer',
        'execution_time' => 'float',
        'created_at' => 'datetime',
    ];

    // Only created_at, no updated_at
    const UPDATED_AT = null;
    public $timestamps = true;

    /**
     * Relationship to Admin
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Log a successful command execution
     */
    public static function logSuccess($adminId, $command, $description, $output, $exitCode, $executionTime)
    {
        return self::create([
            'admin_id' => $adminId,
            'command' => $command,
            'description' => $description,
            'output' => $output,
            'exit_code' => $exitCode,
            'execution_time' => $executionTime,
            'success' => true,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Log a failed command execution
     */
    public static function logFailure($adminId, $command, $description, $error, $exitCode, $executionTime)
    {
        return self::create([
            'admin_id' => $adminId,
            'command' => $command,
            'description' => $description,
            'output' => $error,
            'exit_code' => $exitCode,
            'execution_time' => $executionTime,
            'success' => false,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Get recent command logs
     */
    public static function getRecent($limit = 50)
    {
        return self::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by admin
     */
    public static function getByAdmin($adminId, $limit = 50)
    {
        return self::where('admin_id', $adminId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed commands
     */
    public static function getFailedCommands($limit = 50)
    {
        return self::where('success', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
