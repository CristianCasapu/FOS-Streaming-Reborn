<?php

namespace App\Services;

use PM2Worker;

/**
 * PM2WorkerService
 *
 * Business logic for PM2 worker management and ecosystem.config.js generation
 */
class PM2WorkerService
{
    /**
     * Get all enabled workers ordered by priority
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllEnabledWorkers()
    {
        return PM2Worker::enabled()->byPriority()->get();
    }

    /**
     * Get worker by name
     *
     * @param string $name
     * @return PM2Worker|null
     */
    public function getWorkerByName($name)
    {
        return PM2Worker::where('name', $name)->first();
    }

    /**
     * Generate ecosystem.config.js from database
     *
     * @param string|null $outputPath Path to write the file (null = project root)
     * @param bool $isDev Whether to include development settings
     * @return array ['success' => bool, 'message' => string, 'path' => string]
     */
    public function generateEcosystemConfig($outputPath = null, $isDev = false)
    {
        try {
            // Get all enabled workers
            $workers = $this->getAllEnabledWorkers();

            if ($workers->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No enabled workers found in database',
                    'path' => null
                ];
            }

            // Determine output path
            // Use .cjs extension since package.json has "type": "module"
            if ($outputPath === null) {
                $outputPath = base_path('ecosystem.config.cjs');
            }

            // Generate the config content
            $content = $this->buildEcosystemConfigContent($workers, $isDev);

            // Write to file
            $result = file_put_contents($outputPath, $content);

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to write ecosystem.config.js file',
                    'path' => $outputPath
                ];
            }

            return [
                'success' => true,
                'message' => "Successfully generated ecosystem.config.js with {$workers->count()} workers",
                'path' => $outputPath,
                'workers_count' => $workers->count()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating ecosystem.config.js: ' . $e->getMessage(),
                'path' => $outputPath ?? null
            ];
        }
    }

    /**
     * Build the content of ecosystem.config.js file
     *
     * @param \Illuminate\Database\Eloquent\Collection $workers
     * @param bool $isDev
     * @return string
     */
    protected function buildEcosystemConfigContent($workers, $isDev = false)
    {
        $apps = [];

        foreach ($workers as $worker) {
            $apps[] = $this->workerToConfigArray($worker, $isDev);
        }

        // Generate JavaScript code
        $js = "/**\n";
        $js .= " * PM2 Ecosystem Configuration\n";
        $js .= " * \n";
        $js .= " * AUTO-GENERATED from database by PM2WorkerService\n";
        $js .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $js .= " * Workers: " . count($apps) . "\n";
        $js .= " * \n";
        $js .= " * DO NOT EDIT MANUALLY - Use Admin UI to manage workers\n";
        $js .= " */\n\n";

        if ($isDev) {
            $js .= "const isDev = process.env.NODE_ENV !== 'production';\n\n";
        }

        $js .= "module.exports = {\n";
        $js .= "  apps: [\n";

        foreach ($apps as $index => $app) {
            $js .= $this->formatAppConfig($app, $isDev, $index > 0);
        }

        $js .= "  ]\n";
        $js .= "};\n";

        return $js;
    }

    /**
     * Convert PM2Worker model to config array
     *
     * @param PM2Worker $worker
     * @param bool $isDev
     * @return array
     */
    protected function workerToConfigArray($worker, $isDev = false)
    {
        $config = [
            'name' => $worker->name,
            'script' => $worker->script,
            'instances' => $worker->instances,
            'exec_mode' => $worker->exec_mode,
            'autorestart' => $worker->autorestart,
            'max_restarts' => $worker->max_restarts,
            'min_uptime' => $worker->min_uptime,
            'restart_delay' => $worker->restart_delay,
            'max_memory_restart' => $worker->max_memory_restart,
        ];

        // Add optional fields
        if ($worker->cwd) {
            $config['cwd'] = $worker->cwd;
        }

        if ($worker->args && is_array($worker->args)) {
            $config['args'] = $worker->args;
        }

        if ($worker->cron_restart) {
            $config['cron_restart'] = $worker->cron_restart;
        }

        // Logging configuration
        if ($worker->error_file) {
            $config['error_file'] = $worker->error_file;
        }

        if ($worker->out_file) {
            $config['out_file'] = $worker->out_file;
        }

        if ($worker->log_file) {
            $config['log_file'] = $worker->log_file;
        }

        $config['log_date_format'] = $worker->log_date_format;
        $config['merge_logs'] = $worker->merge_logs;

        // Environment variables
        if ($worker->env_vars && is_array($worker->env_vars)) {
            $config['env'] = $worker->env_vars;
        }

        // Watch mode (development only)
        if ($isDev) {
            $config['watch'] = $worker->watch;
            if ($worker->ignore_watch && is_array($worker->ignore_watch)) {
                $config['ignore_watch'] = $worker->ignore_watch;
            }
        }

        // Graceful shutdown
        $config['kill_timeout'] = $worker->kill_timeout;
        $config['listen_timeout'] = $worker->listen_timeout;
        $config['shutdown_with_message'] = $worker->shutdown_with_message;

        return $config;
    }

    /**
     * Format a single app config for JavaScript output
     *
     * @param array $app
     * @param bool $isDev
     * @param bool $addComma
     * @return string
     */
    protected function formatAppConfig($app, $isDev, $addComma = false)
    {
        $js = ($addComma ? ",\n" : "") . "    {\n";

        foreach ($app as $key => $value) {
            $js .= "      {$key}: ";

            if (is_bool($value)) {
                $js .= $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $js .= $value;
            } elseif (is_array($value)) {
                $js .= $this->formatArrayValue($value);
            } elseif ($key === 'watch' && $isDev) {
                $js .= 'isDev';
            } else {
                $js .= "'" . addslashes($value) . "'";
            }

            $js .= ",\n";
        }

        $js .= "    }";

        return $js;
    }

    /**
     * Format array value for JavaScript
     *
     * @param array $value
     * @return string
     */
    protected function formatArrayValue($value)
    {
        // Check if it's an object (associative array) or simple array
        if ($this->isAssociativeArray($value)) {
            // Format as object
            $items = [];
            foreach ($value as $k => $v) {
                if (is_bool($v)) {
                    $items[] = "\n        {$k}: " . ($v ? 'true' : 'false');
                } elseif (is_int($v)) {
                    $items[] = "\n        {$k}: {$v}";
                } else {
                    $items[] = "\n        {$k}: '" . addslashes($v) . "'";
                }
            }
            return '{' . implode(',', $items) . "\n      }";
        } else {
            // Format as array
            $items = [];
            foreach ($value as $v) {
                if (is_bool($v)) {
                    $items[] = $v ? 'true' : 'false';
                } elseif (is_int($v)) {
                    $items[] = $v;
                } else {
                    $items[] = "'" . addslashes($v) . "'";
                }
            }
            return '[' . implode(', ', $items) . ']';
        }
    }

    /**
     * Check if array is associative
     *
     * @param array $arr
     * @return bool
     */
    protected function isAssociativeArray($arr)
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Sync with PM2 daemon - regenerate config and reload
     *
     * @param bool $isDev
     * @return array
     */
    public function syncWithPM2($isDev = false)
    {
        // Generate new ecosystem.config.cjs
        $result = $this->generateEcosystemConfig(null, $isDev);

        if (!$result['success']) {
            return $result;
        }

        $configFile = 'ecosystem.config.cjs';
        $projectRoot = base_path();

        // Reload PM2 configuration
        exec("cd {$projectRoot} && pm2 reload {$configFile} 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            // Try restart if reload failed
            exec("cd {$projectRoot} && pm2 restart {$configFile} 2>&1", $restartOutput, $restartExitCode);

            if ($restartExitCode !== 0) {
                return [
                    'success' => false,
                    'message' => 'Failed to reload/restart PM2 workers',
                    'output' => array_merge($output, $restartOutput)
                ];
            }

            $output = array_merge($output, $restartOutput);
        }

        return [
            'success' => true,
            'message' => 'Successfully synced PM2 workers from database',
            'workers_count' => $result['workers_count'],
            'output' => $output
        ];
    }

    /**
     * Validate worker configuration data
     *
     * @param array $data
     * @return array Validation errors (empty if valid)
     */
    public function validateWorkerConfig(array $data)
    {
        return PM2Worker::validateConfig($data);
    }
}
