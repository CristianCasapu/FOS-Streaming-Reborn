<?php
/**
 * System Commands Helper Class
 * Securely executes system commands with sudo using stored credentials
 */

namespace App;

use Illuminate\Support\Facades\Crypt;

class SystemCommands
{
    /**
     * Allowed commands that can be executed
     * Whitelist approach for security
     */
    private static $allowedCommands = [
        // Package Management (apt-get)
        'apt-get update',
        'DEBIAN_FRONTEND=noninteractive apt-get install -y ufw',
        'DEBIAN_FRONTEND=noninteractive apt-get install -y fail2ban',
        'DEBIAN_FRONTEND=noninteractive apt-get install -y ffmpeg',
        'DEBIAN_FRONTEND=noninteractive apt-get remove -y',
        'DEBIAN_FRONTEND=noninteractive apt-get upgrade -y',

        // Package Management (apt - modern Debian/Ubuntu)
        'apt update',
        'DEBIAN_FRONTEND=noninteractive apt install -y ufw',
        'DEBIAN_FRONTEND=noninteractive apt install -y fail2ban',
        'DEBIAN_FRONTEND=noninteractive apt install -y ffmpeg',
        'DEBIAN_FRONTEND=noninteractive apt remove -y',
        'DEBIAN_FRONTEND=noninteractive apt upgrade -y',

        // UFW Firewall
        'ufw status',
        'ufw enable',
        'ufw disable',
        'ufw allow',
        'ufw deny',
        'ufw delete',
        'ufw reload',
        'ufw reset',

        // fail2ban
        'fail2ban-client status',
        'fail2ban-client start',
        'fail2ban-client stop',
        'fail2ban-client reload',
        'fail2ban-client set',
        'fail2ban-client get',
        'fail2ban-client ban',
        'fail2ban-client unban',

        // Service Management
        'systemctl start',
        'systemctl stop',
        'systemctl restart',
        'systemctl reload',
        'systemctl status',
        'systemctl enable',
        'systemctl disable',

        // Nginx
        'nginx -t',
        'nginx -s reload',
        'killall -9 nginx',

        // PHP-FPM
        'php-fpm8.4 -t',

        // System Info & Detection
        'df -h',
        'free -h',
        'uptime',
        'who',
        'whoami',
        'ps aux',
        'netstat -tlnp',
        'ss -tlnp',
        'which ffmpeg',
        'which ffprobe',
        'which nginx',
    ];

    /**
     * Execute a command with sudo
     *
     * @param string $command The command to execute
     * @param int $adminId Admin ID for logging
     * @param string $description Human-readable description
     * @return array Result with success, output, exit_code
     */
    public static function execute($command, $adminId, $description = null)
    {
        $startTime = microtime(true);

        try {
            // Get settings
            $setting = \Setting::first();

            if (!$setting || !$setting->system_commands_enabled) {
                throw new \Exception('System commands are disabled');
            }

            // Validate command against whitelist
            if (!self::isCommandAllowed($command)) {
                throw new \Exception('Command not allowed: ' . $command);
            }

            // Commands that don't need sudo
            $noSudoCommands = ['which', 'whoami', 'uptime', 'df -h', 'free -h', 'who', 'ps aux'];
            $needsSudo = true;

            foreach ($noSudoCommands as $noSudoCmd) {
                if (strpos($command, $noSudoCmd) === 0) {
                    $needsSudo = false;
                    break;
                }
            }

            // Build command (with or without sudo)
            if ($needsSudo) {
                if (empty($setting->sudo_password)) {
                    throw new \Exception('Sudo password not configured. Go to Settings to configure sudo credentials.');
                }

                if (empty($setting->sudo_user)) {
                    throw new \Exception('Sudo user not configured. Go to Settings to configure sudo credentials.');
                }

                // Decrypt sudo password
                $sudoPassword = Crypt::decryptString($setting->sudo_password);
                // sudo_user is the user account that has sudo privileges (must match who PHP runs as)
                $sudoUser = $setting->sudo_user;

                // Get the current user running PHP
                $currentUser = get_current_user();

                // Build the sudo command
                // Using sudo -S to read password from stdin, -p '' suppresses prompt
                // IMPORTANT: The current user running PHP must match sudo_user or have sudo privileges
                // The password provided must be for the user running PHP, not sudo_user
                $fullCommand = sprintf(
                    'echo %s | sudo -S -p "" %s 2>&1',
                    escapeshellarg($sudoPassword),
                    $command
                );
            } else {
                // Execute without sudo
                $fullCommand = $command . ' 2>&1';
            }

            // Execute command
            $output = [];
            $exitCode = 0;
            exec($fullCommand, $output, $exitCode);

            // Clean password from output (in case of errors) - only if sudo was used
            if ($needsSudo && isset($sudoPassword)) {
                $output = array_map(function($line) use ($sudoPassword) {
                    return str_replace($sudoPassword, '[REDACTED]', $line);
                }, $output);
            }

            $executionTime = microtime(true) - $startTime;
            $outputString = implode("\n", $output);

            // Log the execution
            if ($exitCode === 0) {
                \SystemCommandLog::logSuccess(
                    $adminId,
                    $command,
                    $description,
                    $outputString,
                    $exitCode,
                    $executionTime
                );
            } else {
                \SystemCommandLog::logFailure(
                    $adminId,
                    $command,
                    $description,
                    $outputString,
                    $exitCode,
                    $executionTime
                );
            }

            // Update last command timestamp
            $setting->last_command_at = date('Y-m-d H:i:s');
            $setting->save();

            return [
                'success' => $exitCode === 0,
                'output' => $outputString,
                'exit_code' => $exitCode,
                'execution_time' => round($executionTime, 3),
                'command' => $command,
            ];

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            // Log the failure
            \SystemCommandLog::logFailure(
                $adminId,
                $command,
                $description,
                $e->getMessage(),
                -1,
                $executionTime
            );

            return [
                'success' => false,
                'output' => $e->getMessage(),
                'exit_code' => -1,
                'execution_time' => round($executionTime, 3),
                'command' => $command,
            ];
        }
    }

    /**
     * Check if command is allowed based on whitelist
     *
     * @param string $command
     * @return bool
     */
    private static function isCommandAllowed($command)
    {
        // Check if command starts with any allowed command
        foreach (self::$allowedCommands as $allowedCommand) {
            if (strpos($command, $allowedCommand) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of allowed commands
     *
     * @return array
     */
    public static function getAllowedCommands()
    {
        return self::$allowedCommands;
    }

    /**
     * Test sudo password
     *
     * @return array
     */
    public static function testSudoPassword()
    {
        return self::execute('whoami', session('admin_id'), 'Test sudo password');
    }

    /**
     * Install package
     *
     * @param string $package
     * @param int $adminId
     * @return array
     */
    public static function installPackage($package, $adminId)
    {
        // Sanitize package name (only allow alphanumeric, dash, underscore, dot)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $package)) {
            return [
                'success' => false,
                'output' => 'Invalid package name',
                'exit_code' => -1,
            ];
        }

        // Update package list first (use apt update for modern systems)
        $updateResult = self::execute('apt update', $adminId, 'Update package list');

        // Continue even if update fails (packages might still be installable)
        // Just log the issue
        if (!$updateResult['success']) {
            error_log('Package update warning: ' . $updateResult['output']);
        }

        // Install package (without escapeshellarg to match whitelist)
        // Use DEBIAN_FRONTEND=noninteractive to prevent interactive prompts
        return self::execute(
            'DEBIAN_FRONTEND=noninteractive apt install -y ' . $package,
            $adminId,
            'Install package: ' . $package
        );
    }

    /**
     * Restart service
     *
     * @param string $service
     * @param int $adminId
     * @return array
     */
    public static function restartService($service, $adminId)
    {
        $allowedServices = ['nginx', 'php8.4-fpm', 'php-fpm', 'fail2ban', 'ufw', 'mariadb', 'mysql'];

        if (!in_array($service, $allowedServices)) {
            return [
                'success' => false,
                'output' => 'Service not allowed: ' . $service,
                'exit_code' => -1,
            ];
        }

        // Don't use escapeshellarg to match whitelist
        return self::execute(
            'systemctl restart ' . $service,
            $adminId,
            'Restart service: ' . $service
        );
    }

    /**
     * Get service status
     *
     * @param string $service
     * @param int $adminId
     * @return array
     */
    public static function getServiceStatus($service, $adminId)
    {
        // Sanitize service name (only allow alphanumeric, dash, underscore, dot)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $service)) {
            return [
                'success' => false,
                'output' => 'Invalid service name',
                'exit_code' => -1,
            ];
        }

        // Don't use escapeshellarg to match whitelist
        return self::execute(
            'systemctl status ' . $service,
            $adminId,
            'Get service status: ' . $service
        );
    }
}
