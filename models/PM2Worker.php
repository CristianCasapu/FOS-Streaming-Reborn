<?php

/**
 * PM2Worker Model
 *
 * Represents a PM2 worker process configuration stored in the database.
 * Enables dynamic worker management without code changes.
 *
 * @property int $id
 * @property string $name Worker process name
 * @property string $display_name Human-readable name
 * @property string|null $description Worker purpose
 * @property string $script Path to worker script
 * @property string|null $cwd Working directory
 * @property array|null $args Command-line arguments
 * @property string $exec_mode fork|cluster
 * @property int $instances Number of instances
 * @property string $max_memory_restart Memory limit
 * @property int $max_restarts Max restart attempts
 * @property string $min_uptime Minimum uptime
 * @property int $restart_delay Restart delay (ms)
 * @property bool $autorestart Auto-restart enabled
 * @property string|null $cron_restart Cron schedule
 * @property string $log_level Log level
 * @property string|null $error_file Error log path
 * @property string|null $out_file Output log path
 * @property string|null $log_file Combined log path
 * @property string $log_date_format Log date format
 * @property bool $merge_logs Merge logs
 * @property array|null $env_vars Environment variables
 * @property bool $watch Watch mode
 * @property array|null $ignore_watch Ignore watch paths
 * @property int $kill_timeout Kill timeout (ms)
 * @property int $listen_timeout Listen timeout (ms)
 * @property bool $shutdown_with_message Shutdown with message
 * @property bool $enabled Worker enabled
 * @property bool $auto_start Auto-start on boot
 * @property int $priority Start priority
 * @property string|null $category Worker category
 * @property array|null $tags Worker tags
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class PM2Worker extends FosStreaming
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pm2_workers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'script',
        'cwd',
        'args',
        'exec_mode',
        'instances',
        'max_memory_restart',
        'max_restarts',
        'min_uptime',
        'restart_delay',
        'autorestart',
        'cron_restart',
        'log_level',
        'error_file',
        'out_file',
        'log_file',
        'log_date_format',
        'merge_logs',
        'env_vars',
        'watch',
        'ignore_watch',
        'kill_timeout',
        'listen_timeout',
        'shutdown_with_message',
        'enabled',
        'auto_start',
        'priority',
        'category',
        'tags',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'auto_start' => 'boolean',
        'autorestart' => 'boolean',
        'watch' => 'boolean',
        'merge_logs' => 'boolean',
        'shutdown_with_message' => 'boolean',
        'instances' => 'integer',
        'max_restarts' => 'integer',
        'restart_delay' => 'integer',
        'kill_timeout' => 'integer',
        'listen_timeout' => 'integer',
        'priority' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'args' => 'array',
        'env_vars' => 'array',
        'ignore_watch' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Scope a query to only include enabled workers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to only include disabled workers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope a query to only include auto-start workers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAutoStart($query)
    {
        return $query->where('auto_start', true);
    }

    /**
     * Scope a query to filter by category.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to order by priority.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPriority($query, $direction = 'asc')
    {
        return $query->orderBy('priority', $direction);
    }

    /**
     * Get the PM2 ecosystem configuration for this worker.
     *
     * @return array
     */
    public function toEcosystemConfig()
    {
        $config = [
            'name' => $this->name,
            'script' => $this->script,
            'instances' => $this->instances,
            'exec_mode' => $this->exec_mode,
            'autorestart' => $this->autorestart,
            'max_restarts' => $this->max_restarts,
            'min_uptime' => $this->min_uptime,
            'restart_delay' => $this->restart_delay,
            'max_memory_restart' => $this->max_memory_restart,
        ];

        // Add optional fields
        if ($this->cwd) {
            $config['cwd'] = $this->cwd;
        }

        if ($this->args && is_array($this->args)) {
            $config['args'] = $this->args;
        }

        if ($this->cron_restart) {
            $config['cron_restart'] = $this->cron_restart;
        }

        // Logging configuration
        if ($this->error_file) {
            $config['error_file'] = $this->error_file;
        }

        if ($this->out_file) {
            $config['out_file'] = $this->out_file;
        }

        if ($this->log_file) {
            $config['log_file'] = $this->log_file;
        }

        $config['log_date_format'] = $this->log_date_format;
        $config['merge_logs'] = $this->merge_logs;

        // Environment variables
        if ($this->env_vars && is_array($this->env_vars)) {
            $config['env'] = $this->env_vars;
        }

        // Watch mode
        $config['watch'] = $this->watch;
        if ($this->ignore_watch && is_array($this->ignore_watch)) {
            $config['ignore_watch'] = $this->ignore_watch;
        }

        // Graceful shutdown
        $config['kill_timeout'] = $this->kill_timeout;
        $config['listen_timeout'] = $this->listen_timeout;
        $config['shutdown_with_message'] = $this->shutdown_with_message;

        return $config;
    }

    /**
     * Get all enabled workers ordered by priority.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEnabledWorkers()
    {
        return self::enabled()
            ->byPriority('asc')
            ->get();
    }

    /**
     * Get all auto-start workers ordered by priority.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAutoStartWorkers()
    {
        return self::enabled()
            ->autoStart()
            ->byPriority('asc')
            ->get();
    }

    /**
     * Validate worker configuration.
     *
     * @param array $data
     * @return array Validation errors (empty if valid)
     */
    public static function validateConfig(array $data)
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors['name'] = 'Worker name is required';
        } elseif (!preg_match('/^[a-z0-9-_]+$/i', $data['name'])) {
            $errors['name'] = 'Worker name can only contain letters, numbers, hyphens, and underscores';
        }

        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Display name is required';
        }

        if (empty($data['script'])) {
            $errors['script'] = 'Script path is required';
        }

        // Validate exec_mode
        if (isset($data['exec_mode']) && !in_array($data['exec_mode'], ['fork', 'cluster'])) {
            $errors['exec_mode'] = 'Execution mode must be "fork" or "cluster"';
        }

        // Validate instances
        if (isset($data['instances'])) {
            $instances = (int)$data['instances'];
            if ($instances < 1 || $instances > 16) {
                $errors['instances'] = 'Instances must be between 1 and 16';
            }
        }

        // Validate priority
        if (isset($data['priority'])) {
            $priority = (int)$data['priority'];
            if ($priority < 0 || $priority > 1000) {
                $errors['priority'] = 'Priority must be between 0 and 1000';
            }
        }

        // Validate JSON fields
        if (isset($data['args']) && is_string($data['args'])) {
            json_decode($data['args']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['args'] = 'Args must be valid JSON array';
            }
        }

        if (isset($data['env_vars']) && is_string($data['env_vars'])) {
            json_decode($data['env_vars']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['env_vars'] = 'Environment variables must be valid JSON object';
            }
        }

        if (isset($data['ignore_watch']) && is_string($data['ignore_watch'])) {
            json_decode($data['ignore_watch']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['ignore_watch'] = 'Ignore watch must be valid JSON array';
            }
        }

        if (isset($data['tags']) && is_string($data['tags'])) {
            json_decode($data['tags']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['tags'] = 'Tags must be valid JSON array';
            }
        }

        return $errors;
    }
}
