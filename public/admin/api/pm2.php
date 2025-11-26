<?php
/**
 * PM2 Process Manager API
 *
 * Manages PM2 workers and system services
 *
 * Actions:
 * - status: Get PM2 and service status
 * - start: Start PM2 workers
 * - stop: Stop PM2 workers
 * - restart: Restart PM2 workers
 * - logs: Get recent logs
 * - queue_stats: Get job queue statistics
 * - sync: Regenerate ecosystem.config.cjs from database and reload PM2
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../functions.php';

use Illuminate\Support\Facades\Crypt;
use App\Services\PM2WorkerService;

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'status';

// Define project root path (3 levels up from /public/admin/api/)
$projectRoot = realpath(__DIR__ . '/../../..');

// Parse JSON input for POST requests
$jsonInput = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true) ?? [];
    }
}

try {
    switch ($action) {
        case 'install':
            // Install PM2 globally
            // Strategy: Try user's npm first (with or without NVM), fall back to sudo if needed

            $output = [];
            $exitCode = 1;

            // Get current user info
            $currentUser = exec('whoami');
            $userHome = exec("getent passwd {$currentUser} | cut -d: -f6") ?: "/home/{$currentUser}";

            // Helper function to find npm
            $findNpm = function($home) {
                // Check standard PATH first
                exec('which npm 2>/dev/null', $whichOutput, $whichExit);
                if ($whichExit === 0 && !empty($whichOutput[0])) {
                    return ['path' => trim($whichOutput[0]), 'nvm' => false];
                }

                // Check NVM paths
                $nvmBase = "{$home}/.nvm/versions/node";
                if (is_dir($nvmBase)) {
                    $versions = glob("{$nvmBase}/v*", GLOB_ONLYDIR);
                    if (!empty($versions)) {
                        rsort($versions);
                        foreach ($versions as $ver) {
                            $npm = "{$ver}/bin/npm";
                            if (file_exists($npm) && is_executable($npm)) {
                                return ['path' => $npm, 'nvm' => true, 'nvm_dir' => "{$home}/.nvm"];
                            }
                        }
                    }
                }

                // Check system paths
                foreach (['/usr/local/bin/npm', '/usr/bin/npm'] as $path) {
                    if (file_exists($path) && is_executable($path)) {
                        return ['path' => $path, 'nvm' => false];
                    }
                }

                return null;
            };

            // Method 1: Try with current user's npm
            $userNpm = $findNpm($userHome);
            if ($userNpm) {
                if ($userNpm['nvm']) {
                    // NVM install: Set PATH to include node binary directory
                    $nodeBinDir = dirname($userNpm['path']);
                    $command = "export PATH=\"{$nodeBinDir}:\$PATH\" && export HOME='{$userHome}' && npm install -g pm2 2>&1";
                    exec("bash -c '{$command}'", $output, $exitCode);
                } else {
                    // Direct npm path - also need to ensure node is in PATH
                    $nodeBinDir = dirname($userNpm['path']);
                    $command = "export PATH=\"{$nodeBinDir}:\$PATH\" && npm install -g pm2 2>&1";
                    exec("bash -c '{$command}'", $output, $exitCode);
                }
            }

            // Method 2: Fall back to sudo with root's npm if user install failed
            if ($exitCode !== 0) {
                $output = []; // Reset output

                // Get sudo password from settings
                $setting = Setting::first();
                if (!$setting || empty($setting->sudo_password)) {
                    throw new Exception('User npm install failed and sudo password not configured. Please set sudo password in Settings → System Commands Configuration.');
                }

                // Decrypt sudo password
                $sudoPassword = Crypt::decryptString($setting->sudo_password);

                // Try to find npm as root - check root's NVM first, then system paths
                $rootNvmScript = 'export HOME=/root && export NVM_DIR="/root/.nvm" && [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"';

                // Try root's NVM installation
                $command = "echo '{$sudoPassword}' | sudo -S bash -c '{$rootNvmScript} && npm install -g pm2' 2>&1";
                exec($command, $output, $exitCode);

                // If still failed, try system npm with sudo
                if ($exitCode !== 0) {
                    foreach (['/usr/local/bin/npm', '/usr/bin/npm'] as $sysNpm) {
                        if (file_exists($sysNpm)) {
                            $output = [];
                            $command = "echo '{$sudoPassword}' | sudo -S {$sysNpm} install -g pm2 2>&1";
                            exec($command, $output, $exitCode);
                            if ($exitCode === 0) break;
                        }
                    }
                }
            }

            if ($exitCode !== 0 && empty($userNpm)) {
                throw new Exception('npm not found. Please ensure Node.js and npm are installed for either the web server user or root.');
            }

            if ($exitCode === 0) {
                // Start workers after installation - need PATH for pm2 command
                $nodeBinDir = $userNpm ? dirname($userNpm['path']) : '';
                $startCommand = $nodeBinDir
                    ? "export PATH=\"{$nodeBinDir}:\$PATH\" && cd {$projectRoot} && pm2 start ecosystem.config.cjs 2>&1"
                    : "cd {$projectRoot} && pm2 start ecosystem.config.cjs 2>&1";
                exec("bash -c '{$startCommand}'", $startOutput, $startExitCode);

                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 installed successfully and workers started'
                ]);
            } else {
                throw new Exception('Failed to install PM2: ' . implode("\n", $output));
            }
            break;

        case 'get_config':
            // Get worker configuration from database (source of truth)
            $workerName = $_GET['worker'] ?? null;

            if (!$workerName) {
                throw new Exception('Worker name is required');
            }

            // Get worker from database
            $worker = PM2Worker::where('name', $workerName)->first();

            if (!$worker) {
                throw new Exception("Worker '{$workerName}' not found in database");
            }

            // Return complete configuration
            $config = [
                'name' => $worker->name,
                'script' => $worker->script,
                'instances' => $worker->instances,
                'exec_mode' => $worker->exec_mode,
                'max_memory_restart' => $worker->max_memory_restart,
                'max_restarts' => $worker->max_restarts,
                'min_uptime' => $worker->min_uptime,
                'cron_restart' => $worker->cron_restart ?? '',
                'log_level' => $worker->env_vars['LOG_LEVEL'] ?? 'warn',
                'env_vars' => $worker->env_vars ?? [],
                'description' => $worker->description,
                'category' => $worker->category,
                'enabled' => (bool) $worker->enabled
            ];

            echo json_encode([
                'success' => true,
                'data' => $config
            ]);
            break;

        case 'update_config':
            // Update worker configuration in database and regenerate ecosystem.config.cjs
            $workerName = $jsonInput['worker'] ?? $_POST['worker'] ?? null;
            $configData = $jsonInput['config'] ?? $_POST['config'] ?? null;

            if (!$workerName || !$configData) {
                throw new Exception('Worker name and configuration are required');
            }

            // Decode config if it's JSON string
            if (is_string($configData)) {
                $configData = json_decode($configData, true);
            }

            // Get worker from database
            $worker = PM2Worker::where('name', $workerName)->first();

            if (!$worker) {
                throw new Exception("Worker '{$workerName}' not found in database");
            }

            // Update worker configuration in database
            if (isset($configData['script'])) {
                $worker->script = $configData['script'];
            }
            if (isset($configData['instances'])) {
                $worker->instances = (int) $configData['instances'];
            }
            if (isset($configData['exec_mode'])) {
                $worker->exec_mode = $configData['exec_mode'];
            }
            if (isset($configData['max_memory_restart'])) {
                $worker->max_memory_restart = $configData['max_memory_restart'];
            }
            if (isset($configData['max_restarts'])) {
                $worker->max_restarts = (int) $configData['max_restarts'];
            }
            if (isset($configData['min_uptime'])) {
                $worker->min_uptime = $configData['min_uptime'];
            }
            if (isset($configData['cron_restart'])) {
                $worker->cron_restart = $configData['cron_restart'] ?: null;
            }

            // Handle env_vars - merge with existing
            $envVars = $worker->env_vars ?? [];
            if (isset($configData['env_vars']) && is_array($configData['env_vars'])) {
                $envVars = array_merge($envVars, $configData['env_vars']);
            }
            // Also handle log_level as an env var
            if (isset($configData['log_level'])) {
                $envVars['LOG_LEVEL'] = $configData['log_level'];
            }
            $worker->env_vars = $envVars;

            // Save to database
            $worker->save();

            // Regenerate ecosystem.config.cjs from database
            $workerService = new PM2WorkerService();
            $isDev = env('APP_ENV') === 'local' || env('APP_ENV') === 'development';
            $result = $workerService->generateEcosystemConfig(null, $isDev);

            if (!$result['success']) {
                throw new Exception('Failed to regenerate ecosystem config: ' . $result['message']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Configuration updated successfully. Ecosystem config regenerated.'
            ]);
            break;

        case 'status':
            // Get PM2 worker status
            $pm2Status = [];

            // Load expected workers from database (auto-loader!)
            $dbWorkers = PM2Worker::enabled()->byPriority()->get();
            $expectedWorkers = [];

            foreach ($dbWorkers as $worker) {
                $expectedWorkers[$worker->name] = [
                    'name' => $worker->name,
                    'display_name' => $worker->display_name,
                    'script' => $worker->script,
                    'instances' => $worker->instances,
                    'exec_mode' => $worker->exec_mode,
                    'description' => $worker->description,
                    'category' => $worker->category,
                    'priority' => $worker->priority
                ];
            }

            execPM2('pm2 jlist --no-color', $output, $exitCode);

            $runningProcesses = [];
            if ($exitCode === 0 && !empty($output)) {
                // PM2 may output warning messages before the JSON - find the JSON array
                $jsonOutput = implode('', $output);
                // Strip ANSI escape codes
                $jsonOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $jsonOutput);
                // Extract JSON array from output (handles PM2 "out-of-date" warnings)
                if (preg_match('/\[.*\]/s', $jsonOutput, $matches)) {
                    $jsonOutput = $matches[0];
                }
                $processes = json_decode($jsonOutput, true);

                if ($processes) {
                    foreach ($processes as $process) {
                        $runningProcesses[$process['name']] = [
                            'name' => $process['name'],
                            'pm_id' => $process['pm_id'],
                            'status' => $process['pm2_env']['status'] ?? 'unknown',
                            'cpu' => $process['monit']['cpu'] ?? 0,
                            'memory' => $process['monit']['memory'] ?? 0,
                            'uptime' => $process['pm2_env']['pm_uptime'] ?? 0,
                            'restarts' => $process['pm2_env']['restart_time'] ?? 0,
                            'pid' => $process['pid'] ?? null,
                            'exec_mode' => $process['pm2_env']['exec_mode'] ?? 'fork',
                            'instances' => $process['pm2_env']['instances'] ?? 1,
                            'pm_cwd' => $process['pm2_env']['pm_cwd'] ?? null,
                            'pm_exec_path' => $process['pm2_env']['pm_exec_path'] ?? null
                        ];
                    }
                }
            }

            // Merge expected workers with running processes
            foreach ($expectedWorkers as $workerName => $workerConfig) {
                if (isset($runningProcesses[$workerName])) {
                    // Worker is running - use actual data
                    $pm2Status[] = array_merge($workerConfig, $runningProcesses[$workerName]);
                } else {
                    // Worker is not running - show as stopped
                    $pm2Status[] = [
                        'name' => $workerConfig['name'],
                        'display_name' => $workerConfig['display_name'] ?? $workerConfig['name'],
                        'pm_id' => null,
                        'status' => 'stopped',
                        'cpu' => 0,
                        'memory' => 0,
                        'uptime' => 0,
                        'restarts' => 0,
                        'pid' => null,
                        'exec_mode' => $workerConfig['exec_mode'],
                        'instances' => $workerConfig['instances'],
                        'script' => $workerConfig['script'],
                        'description' => $workerConfig['description'],
                        'category' => $workerConfig['category'] ?? null,
                        'priority' => $workerConfig['priority'] ?? 100,
                        'pm_cwd' => null,
                        'pm_exec_path' => null
                    ];
                }
            }

            // Get system services status (core platform services only)
            // Note: Streaming services (fos-nginx-streaming, fos-php-fpm-streaming) are managed
            // separately in Settings.vue under "Streaming Protocol (MPEG-DASH / HLS)" section
            $services = [
                [
                    'name' => 'fos-nginx',
                    'display_name' => 'Nginx Admin Panel',
                    'type' => 'system',
                    'status' => getServiceStatus('fos-nginx'),
                    'description' => 'Admin panel web server'
                ],
                [
                    'name' => 'php-fpm',
                    'display_name' => 'PHP-FPM Admin',
                    'type' => 'system',
                    'status' => getServiceStatus('php8.4-fpm'),
                    'description' => 'PHP FastCGI for admin panel'
                ],
                [
                    'name' => 'mariadb',
                    'display_name' => 'MariaDB Database',
                    'type' => 'system',
                    'status' => getServiceStatus('mariadb'),
                    'description' => 'Database server'
                ]
            ];

            // Get queue statistics
            require_once __DIR__ . '/../../../app/Services/JobQueueService.php';
            $queueService = new \App\Services\JobQueueService();

            $queueStats = [
                'stream_import' => $queueService->getQueueStats('stream-import'),
                'ffprobe_analysis' => $queueService->getQueueStats('ffprobe-analysis')
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'pm2_workers' => $pm2Status,
                    'system_services' => $services,
                    'queue_stats' => $queueStats,
                    'pm2_installed' => checkPM2Installed()
                ]
            ]);
            break;

        case 'start':
            // Start PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                execPM2("cd {$projectRoot} && pm2 start ecosystem.config.cjs", $output, $exitCode);
            } else {
                execPM2("cd {$projectRoot} && pm2 start {$workerId}", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers started successfully'
                ]);
            } else {
                throw new Exception('Failed to start PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'stop':
            // Stop PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                execPM2("cd {$projectRoot} && pm2 stop all", $output, $exitCode);
            } else {
                execPM2("cd {$projectRoot} && pm2 stop {$workerId}", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers stopped successfully'
                ]);
            } else {
                throw new Exception('Failed to stop PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'restart':
            // Restart PM2 workers
            $workerId = $_GET['worker_id'] ?? 'all';

            if ($workerId === 'all') {
                execPM2("cd {$projectRoot} && pm2 restart all", $output, $exitCode);
            } else {
                execPM2("cd {$projectRoot} && pm2 restart {$workerId}", $output, $exitCode);
            }

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'PM2 workers restarted successfully'
                ]);
            } else {
                throw new Exception('Failed to restart PM2 workers: ' . implode("\n", $output));
            }
            break;

        case 'logs':
            // Get recent PM2 logs
            $worker = $_GET['worker'] ?? 'all';
            $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;

            if ($worker === 'all') {
                execPM2("cd {$projectRoot} && pm2 logs --nostream --lines {$lines}", $output, $exitCode);
            } else {
                execPM2("cd {$projectRoot} && pm2 logs {$worker} --nostream --lines {$lines}", $output, $exitCode);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'logs' => implode("\n", $output)
                ]
            ]);
            break;

        case 'service_action':
            // Control system services
            $service = $jsonInput['service'] ?? $_POST['service'] ?? null;
            $serviceAction = $jsonInput['action'] ?? $_POST['action'] ?? null;

            if (!$service || !$serviceAction) {
                throw new Exception('Service and action are required');
            }

            $allowedServices = ['nginx', 'mariadb', 'php8.4-fpm'];
            $allowedActions = ['start', 'stop', 'restart', 'reload'];

            if (!in_array($service, $allowedServices)) {
                throw new Exception('Invalid service');
            }

            if (!in_array($serviceAction, $allowedActions)) {
                throw new Exception('Invalid action');
            }

            // Get sudo password from settings
            $setting = Setting::first();
            if (!$setting || empty($setting->sudo_password)) {
                throw new Exception('Sudo password not configured. Please set it in Settings → System Commands Configuration.');
            }

            // Decrypt sudo password
            $sudoPassword = Crypt::decryptString($setting->sudo_password);

            $command = "echo '{$sudoPassword}' | sudo -S systemctl {$serviceAction} {$service} 2>&1";
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($serviceAction) . " {$service} successfully"
                ]);
            } else {
                throw new Exception("Failed to {$serviceAction} {$service}: " . implode("\n", $output));
            }
            break;

        case 'queue_stats':
            // Get detailed queue statistics
            require_once __DIR__ . '/../../../app/Services/JobQueueService.php';
            $queueService = new \App\Services\JobQueueService();

            $stats = [
                'stream_import' => $queueService->getQueueStats('stream-import'),
                'ffprobe_analysis' => $queueService->getQueueStats('ffprobe-analysis')
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'sync':
            // Regenerate ecosystem.config.cjs from database and reload PM2
            $workerService = new PM2WorkerService();
            $isDev = env('APP_ENV') === 'local' || env('APP_ENV') === 'development';

            // Generate ecosystem.config.cjs from database
            $result = $workerService->syncWithPM2($isDev);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'workers_count' => $result['workers_count'],
                    'output' => $result['output']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'],
                    'output' => $result['output'] ?? []
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Helper function to get service status
 */
function getServiceStatus($serviceName) {
    exec("systemctl is-active {$serviceName} 2>&1", $output, $exitCode);
    $status = trim(implode('', $output));

    return [
        'active' => $exitCode === 0 && $status === 'active',
        'status' => $status,
        'enabled' => isServiceEnabled($serviceName)
    ];
}

/**
 * Helper function to check if service is enabled
 */
function isServiceEnabled($serviceName) {
    exec("systemctl is-enabled {$serviceName} 2>&1", $output, $exitCode);
    return $exitCode === 0;
}

/**
 * Get the node binary directory for PATH
 * Returns the directory containing node/npm/pm2 binaries
 */
function getNodeBinDir() {
    // Check NVM installation first
    $currentUser = exec('whoami');
    $userHome = exec("getent passwd {$currentUser} | cut -d: -f6") ?: "/home/{$currentUser}";
    $nvmBase = "{$userHome}/.nvm/versions/node";

    if (is_dir($nvmBase)) {
        $versions = glob("{$nvmBase}/v*", GLOB_ONLYDIR);
        if (!empty($versions)) {
            rsort($versions);
            foreach ($versions as $ver) {
                $bin = "{$ver}/bin";
                if (file_exists("{$bin}/node") && file_exists("{$bin}/pm2")) {
                    return $bin;
                }
            }
        }
    }

    // Check system paths
    if (file_exists('/usr/local/bin/pm2')) {
        return '/usr/local/bin';
    }
    if (file_exists('/usr/bin/pm2')) {
        return '/usr/bin';
    }

    return null;
}

/**
 * Execute a PM2 command with proper PATH and HOME
 */
function execPM2($command, &$output = [], &$exitCode = 0) {
    $nodeBinDir = getNodeBinDir();
    $currentUser = exec('whoami');
    $userHome = exec("getent passwd {$currentUser} | cut -d: -f6") ?: "/home/{$currentUser}";

    // Set HOME and PATH so PM2 uses the correct daemon
    $envSetup = "export HOME=\"{$userHome}\" && export PM2_HOME=\"{$userHome}/.pm2\"";

    if ($nodeBinDir) {
        $fullCommand = "{$envSetup} && export PATH=\"{$nodeBinDir}:\$PATH\" && {$command}";
    } else {
        $fullCommand = "{$envSetup} && {$command}";
    }

    exec("bash -c '{$fullCommand}' 2>&1", $output, $exitCode);
    return $exitCode === 0;
}

/**
 * Check if PM2 is installed
 */
function checkPM2Installed() {
    // Check PATH first
    exec('which pm2 2>&1', $output, $exitCode);
    if ($exitCode === 0) {
        return true;
    }

    // Check NVM installation
    $currentUser = exec('whoami');
    $userHome = exec("getent passwd {$currentUser} | cut -d: -f6") ?: "/home/{$currentUser}";
    $nvmBase = "{$userHome}/.nvm/versions/node";

    if (is_dir($nvmBase)) {
        $versions = glob("{$nvmBase}/v*", GLOB_ONLYDIR);
        if (!empty($versions)) {
            rsort($versions);
            foreach ($versions as $ver) {
                $pm2 = "{$ver}/bin/pm2";
                if (file_exists($pm2) && is_executable($pm2)) {
                    return true;
                }
            }
        }
    }

    // Check global npm paths
    foreach (['/usr/local/bin/pm2', '/usr/bin/pm2'] as $path) {
        if (file_exists($path) && is_executable($path)) {
            return true;
        }
    }

    return false;
}

/**
 * Extract worker configuration from ecosystem.config.cjs
 */
function extractWorkerConfig($content, $workerName) {
    $config = [
        'name' => $workerName,
        'script' => '',
        'instances' => 1,
        'exec_mode' => 'fork',
        'max_memory_restart' => '500M',
        'max_restarts' => 10,
        'min_uptime' => '10s',
        'cron_restart' => '',
        'log_level' => 'warn'
    ];

    // Find the worker block in the apps array
    $pattern = '/\{\s*name:\s*[\'"]' . preg_quote($workerName, '/') . '[\'"].*?\}/s';
    if (preg_match($pattern, $content, $matches)) {
        $workerBlock = $matches[0];

        // Extract individual properties
        if (preg_match('/script:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['script'] = $m[1];
        }
        if (preg_match('/instances:\s*(\d+)/', $workerBlock, $m)) {
            $config['instances'] = (int)$m[1];
        }
        if (preg_match('/exec_mode:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['exec_mode'] = $m[1];
        }
        if (preg_match('/max_memory_restart:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['max_memory_restart'] = $m[1];
        }
        if (preg_match('/max_restarts:\s*(\d+)/', $workerBlock, $m)) {
            $config['max_restarts'] = (int)$m[1];
        }
        if (preg_match('/min_uptime:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['min_uptime'] = $m[1];
        }
        if (preg_match('/cron_restart:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['cron_restart'] = $m[1];
        }
        // Extract LOG_LEVEL from env section
        if (preg_match('/LOG_LEVEL:\s*[\'"]([^\'"]+)[\'"]/', $workerBlock, $m)) {
            $config['log_level'] = $m[1];
        }
    }

    return $config;
}

/**
 * Update worker configuration in ecosystem.config.cjs
 */
function updateWorkerConfig($content, $workerName, $configData) {
    // Find the worker block
    $pattern = '/(\{\s*name:\s*[\'"]' . preg_quote($workerName, '/') . '[\'"].*?\})/s';

    if (preg_match($pattern, $content, $matches)) {
        $workerBlock = $matches[1];
        $newBlock = $workerBlock;

        // Update each configuration value
        if (isset($configData['script'])) {
            $newBlock = preg_replace(
                '/script:\s*[\'"][^\'"]*[\'"]/',
                "script: '{$configData['script']}'",
                $newBlock
            );
        }

        if (isset($configData['instances'])) {
            $newBlock = preg_replace(
                '/instances:\s*\d+/',
                "instances: {$configData['instances']}",
                $newBlock
            );
        }

        if (isset($configData['exec_mode'])) {
            $newBlock = preg_replace(
                '/exec_mode:\s*[\'"][^\'"]*[\'"]/',
                "exec_mode: '{$configData['exec_mode']}'",
                $newBlock
            );
        }

        if (isset($configData['max_memory_restart'])) {
            $newBlock = preg_replace(
                '/max_memory_restart:\s*[\'"][^\'"]*[\'"]/',
                "max_memory_restart: '{$configData['max_memory_restart']}'",
                $newBlock
            );
        }

        if (isset($configData['max_restarts'])) {
            $newBlock = preg_replace(
                '/max_restarts:\s*\d+/',
                "max_restarts: {$configData['max_restarts']}",
                $newBlock
            );
        }

        if (isset($configData['min_uptime'])) {
            $newBlock = preg_replace(
                '/min_uptime:\s*[\'"][^\'"]*[\'"]/',
                "min_uptime: '{$configData['min_uptime']}'",
                $newBlock
            );
        }

        if (isset($configData['cron_restart'])) {
            if (!empty($configData['cron_restart'])) {
                $newBlock = preg_replace(
                    '/cron_restart:\s*[\'"][^\'"]*[\'"]/',
                    "cron_restart: '{$configData['cron_restart']}'",
                    $newBlock
                );
            }
        }

        if (isset($configData['log_level'])) {
            // Update LOG_LEVEL in env sections
            $newBlock = preg_replace(
                '/LOG_LEVEL:\s*[\'"][^\'"]*[\'"]/',
                "LOG_LEVEL: '{$configData['log_level']}'",
                $newBlock
            );
        }

        // Replace the old block with the new one
        $content = str_replace($workerBlock, $newBlock, $content);
    }

    return $content;
}
