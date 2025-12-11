<?php
/**
 * Advanced Security API Endpoint
 * Manages UFW firewall, fail2ban, and system-level security
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_status';

try {
    switch ($action) {
        case 'get_status':
            // Get overall security status
            $status = [
                'ufw' => [
                    'installed' => false,
                    'active' => false,
                    'rules_count' => 0
                ],
                'fail2ban' => [
                    'installed' => false,
                    'running' => false,
                    'jails_count' => 0,
                    'banned_count' => 0
                ]
            ];

            // Check UFW status - use separate output variable
            $ufwWhichOutput = [];
            exec('which ufw 2>/dev/null', $ufwWhichOutput, $ufwWhichCode);
            if ($ufwWhichCode === 0 && !empty($ufwWhichOutput)) {
                $status['ufw']['installed'] = true;

                $ufwStatusOutput = [];
                exec('sudo ufw status 2>/dev/null', $ufwStatusOutput, $ufwStatusCode);
                if ($ufwStatusCode === 0 && !empty($ufwStatusOutput)) {
                    $status['ufw']['active'] = strpos(implode('', $ufwStatusOutput), 'Status: active') !== false;
                    $status['ufw']['rules_count'] = max(0, count($ufwStatusOutput) - 3);
                }
            }

            // Check fail2ban status - use separate output variable
            $f2bWhichOutput = [];
            exec('which fail2ban-client 2>/dev/null', $f2bWhichOutput, $f2bWhichCode);
            if ($f2bWhichCode === 0 && !empty($f2bWhichOutput)) {
                $status['fail2ban']['installed'] = true;

                $f2bStatusOutput = [];
                exec('sudo fail2ban-client status 2>/dev/null', $f2bStatusOutput, $f2bStatusCode);
                if ($f2bStatusCode === 0) {
                    $status['fail2ban']['running'] = true;

                    // Count jails
                    foreach ($f2bStatusOutput as $line) {
                        if (strpos($line, 'Jail list:') !== false) {
                            preg_match_all('/\w+/', $line, $matches);
                            $status['fail2ban']['jails_count'] = max(0, count($matches[0]) - 2);
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $status]);
            break;

        case 'get_ufw_rules':
            exec('sudo ufw status numbered 2>/dev/null', $output, $returnCode);

            $rules = [];
            if ($returnCode === 0) {
                foreach ($output as $line) {
                    if (preg_match('/\[\s*(\d+)\]\s+(.+)/', $line, $matches)) {
                        $rules[] = [
                            'number' => $matches[1],
                            'rule' => trim($matches[2])
                        ];
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $rules]);
            break;

        case 'toggle_ufw':
            $input = json_decode(file_get_contents('php://input'), true);
            $enable = $input['enable'] ?? false;

            $command = $enable ? 'sudo ufw --force enable 2>&1' : 'sudo ufw --force disable 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => $enable ? 'Firewall enabled successfully' : 'Firewall disabled successfully'
                ]);
            } else {
                throw new Exception('Failed to ' . ($enable ? 'enable' : 'disable') . ' firewall: ' . implode(' ', $output));
            }
            break;

        case 'add_ufw_rule':
            $input = json_decode(file_get_contents('php://input'), true);
            $port = $input['port'] ?? '';
            $protocol = $input['protocol'] ?? 'tcp';
            $action = $input['action'] ?? 'allow';

            if (empty($port)) {
                throw new Exception('Port is required');
            }

            $command = "sudo ufw {$action} {$port}/{$protocol} 2>&1";
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                exec('sudo ufw reload 2>&1');
                echo json_encode(['success' => true, 'message' => 'Firewall rule added successfully']);
            } else {
                throw new Exception('Failed to add firewall rule: ' . implode(' ', $output));
            }
            break;

        case 'delete_ufw_rule':
            $input = json_decode(file_get_contents('php://input'), true);
            $ruleNumber = $input['rule_number'] ?? '';

            if (empty($ruleNumber)) {
                throw new Exception('Rule number is required');
            }

            $command = "sudo ufw --force delete {$ruleNumber} 2>&1";
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => 'Firewall rule deleted successfully']);
            } else {
                throw new Exception('Failed to delete firewall rule: ' . implode(' ', $output));
            }
            break;

        case 'get_fail2ban_jails':
            exec('sudo fail2ban-client status 2>/dev/null', $output, $returnCode);

            $jails = [];
            if ($returnCode === 0) {
                foreach ($output as $line) {
                    if (strpos($line, 'Jail list:') !== false) {
                        $jailLine = substr($line, strpos($line, ':') + 1);
                        $jails = array_filter(array_map('trim', explode(',', $jailLine)));
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => array_values($jails)]);
            break;

        case 'get_jail_status':
            $jail = $_GET['jail'] ?? '';
            if (empty($jail)) {
                throw new Exception('Jail name is required');
            }

            exec("sudo fail2ban-client status {$jail} 2>/dev/null", $output, $returnCode);

            $status = [
                'jail' => $jail,
                'currently_banned' => 0,
                'total_banned' => 0,
                'total_failed' => 0,
                'banned_ips' => []
            ];

            if ($returnCode === 0) {
                foreach ($output as $line) {
                    if (preg_match('/Currently banned:\s*(\d+)/', $line, $matches)) {
                        $status['currently_banned'] = (int)$matches[1];
                    } elseif (preg_match('/Total banned:\s*(\d+)/', $line, $matches)) {
                        $status['total_banned'] = (int)$matches[1];
                    } elseif (preg_match('/Total failed:\s*(\d+)/', $line, $matches)) {
                        $status['total_failed'] = (int)$matches[1];
                    } elseif (strpos($line, 'Banned IP list:') !== false) {
                        $ipLine = substr($line, strpos($line, ':') + 1);
                        $status['banned_ips'] = array_filter(array_map('trim', explode(' ', $ipLine)));
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $status]);
            break;

        case 'ban_ip':
            $input = json_decode(file_get_contents('php://input'), true);
            $ip = $input['ip'] ?? '';
            $jail = $input['jail'] ?? 'sshd';

            if (empty($ip)) {
                throw new Exception('IP address is required');
            }

            // Validate IP format
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new Exception('Invalid IP address format');
            }

            exec("sudo fail2ban-client set {$jail} banip {$ip} 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => "IP {$ip} banned successfully in jail {$jail}"]);
            } else {
                throw new Exception('Failed to ban IP: ' . implode(' ', $output));
            }
            break;

        case 'unban_ip':
            $input = json_decode(file_get_contents('php://input'), true);
            $ip = $input['ip'] ?? '';
            $jail = $input['jail'] ?? 'sshd';

            if (empty($ip)) {
                throw new Exception('IP address is required');
            }

            exec("sudo fail2ban-client set {$jail} unbanip {$ip} 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => "IP {$ip} unbanned successfully from jail {$jail}"]);
            } else {
                throw new Exception('Failed to unban IP: ' . implode(' ', $output));
            }
            break;

        case 'reload_fail2ban':
            exec('sudo fail2ban-client reload 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => 'fail2ban reloaded successfully']);
            } else {
                throw new Exception('Failed to reload fail2ban: ' . implode(' ', $output));
            }
            break;

        case 'enable_fail2ban':
            // Enable and start fail2ban service
            // Service to jail mapping with log file paths
            $jailConfigs = [
                'sshd' => [
                    'service_checks' => ['sshd', 'ssh'],
                    'log_path' => '/var/log/auth.log',
                ],
                'nginx' => [
                    'service_checks' => ['nginx'],
                    'log_path' => '/var/log/nginx/error.log',
                ],
                'apache' => [
                    'service_checks' => ['apache2', 'httpd'],
                    'log_path' => '/var/log/apache2/error.log',
                ],
            ];

            $runningServices = [];
            $stoppedServices = [];
            $createdLogFiles = [];

            foreach ($jailConfigs as $service => $config) {
                $isRunning = false;
                foreach ($config['service_checks'] as $cmd) {
                    exec("systemctl is-active {$cmd} 2>/dev/null", $checkOutput, $checkCode);
                    if ($checkCode === 0) {
                        $isRunning = true;
                        break;
                    }
                    $checkOutput = [];
                }

                if ($isRunning) {
                    $runningServices[] = $service;

                    // Check if log file exists, create it if not
                    $logPath = $config['log_path'];
                    exec("sudo test -f " . escapeshellarg($logPath), $testOutput, $testCode);
                    if ($testCode !== 0) {
                        // Create the log file and its parent directory
                        $logDir = dirname($logPath);
                        exec("sudo mkdir -p " . escapeshellarg($logDir) . " 2>&1");
                        exec("sudo touch " . escapeshellarg($logPath) . " 2>&1", $touchOutput, $touchCode);
                        if ($touchCode === 0) {
                            exec("sudo chmod 640 " . escapeshellarg($logPath) . " 2>&1");
                            $createdLogFiles[] = $logPath;
                        }
                    }
                } else {
                    $stoppedServices[] = $service;
                }
            }

            // Create/update jail.local to disable jails for non-running services
            $jailLocalPath = '/etc/fail2ban/jail.local';
            $jailConfig = "[DEFAULT]\nbantime = 3600\nfindtime = 600\nmaxretry = 5\n\n";

            // Disable jails for services that aren't running
            foreach ($stoppedServices as $service) {
                $jailConfig .= "[{$service}]\nenabled = false\n\n";
            }

            // Write jail.local configuration
            $tempFile = tempnam(sys_get_temp_dir(), 'jail_');
            file_put_contents($tempFile, $jailConfig);
            exec("sudo cp {$tempFile} {$jailLocalPath} 2>&1", $cpOutput, $cpCode);
            unlink($tempFile);

            if ($cpCode !== 0) {
                throw new Exception('Failed to configure fail2ban jails');
            }

            // Also create jail.d/fos-overrides.local to override defaults-debian.conf
            // Files in jail.d/ with .local extension have highest priority
            $jailDLocalPath = '/etc/fail2ban/jail.d/fos-overrides.local';
            $jailDConfig = "# FOS Streaming jail overrides\n# This file overrides defaults-debian.conf\n\n";
            foreach ($stoppedServices as $service) {
                $jailDConfig .= "[{$service}]\nenabled = false\n\n";
            }

            $tempFile2 = tempnam(sys_get_temp_dir(), 'jaild_');
            file_put_contents($tempFile2, $jailDConfig);
            exec("sudo cp {$tempFile2} {$jailDLocalPath} 2>&1", $cpOutput2, $cpCode2);
            unlink($tempFile2);

            // Enable the service to start on boot
            exec('sudo systemctl enable fail2ban 2>&1', $enableOutput, $enableCode);

            // Start the service
            exec('sudo systemctl start fail2ban 2>&1', $startOutput, $startCode);

            if ($startCode === 0) {
                $message = 'fail2ban service enabled and started successfully.';
                if (!empty($stoppedServices)) {
                    $message .= ' Disabled jails for non-running services: ' . implode(', ', $stoppedServices) . '.';
                }
                if (!empty($createdLogFiles)) {
                    $message .= ' Created missing log files: ' . implode(', ', $createdLogFiles) . '.';
                }
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'running_services' => $runningServices,
                    'disabled_jails' => $stoppedServices,
                    'created_log_files' => $createdLogFiles
                ]);
            } else {
                throw new Exception('Failed to start fail2ban: ' . implode(' ', array_merge($enableOutput, $startOutput)));
            }
            break;

        case 'stop_fail2ban':
            exec('sudo systemctl stop fail2ban 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => 'fail2ban stopped successfully']);
            } else {
                throw new Exception('Failed to stop fail2ban: ' . implode(' ', $output));
            }
            break;

        case 'get_fail2ban_config':
            // Get fail2ban jail configuration
            $jailConfigs = [];

            // Read local FOS jail configuration if it exists
            $fosJailPath = __DIR__ . '/../../../security/fail2ban/jail.local';
            $systemJailPath = '/etc/fail2ban/jail.d/fos-streaming.local';

            $configPath = file_exists($systemJailPath) ? $systemJailPath : $fosJailPath;

            if (file_exists($configPath)) {
                $config = file_get_contents($configPath);
                $jailConfigs['config_file'] = $configPath;
                $jailConfigs['config_content'] = $config;

                // Parse jails from config
                preg_match_all('/\[([^\]]+)\]\s*\n((?:(?!\[)[^\n]*\n)*)/s', $config, $matches, PREG_SET_ORDER);

                $jailConfigs['jails'] = [];
                foreach ($matches as $match) {
                    $jailName = $match[1];
                    if ($jailName === 'DEFAULT') continue;

                    $jailConfig = [];
                    $lines = explode("\n", $match[2]);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || $line[0] === '#') continue;
                        if (strpos($line, '=') !== false) {
                            list($key, $value) = array_map('trim', explode('=', $line, 2));
                            $jailConfig[$key] = $value;
                        }
                    }
                    if (!empty($jailConfig)) {
                        $jailConfigs['jails'][$jailName] = $jailConfig;
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $jailConfigs]);
            break;

        case 'get_fail2ban_logs':
            $jail = $_GET['jail'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

            $logs = [];

            // Get fail2ban log entries for specific jail or all
            if (!empty($jail)) {
                // Get logs for specific jail
                exec("sudo grep -i '{$jail}' /var/log/fail2ban.log 2>/dev/null | tail -n {$limit}", $logs);
            } else {
                // Get all fail2ban logs
                exec("sudo tail -n {$limit} /var/log/fail2ban.log 2>/dev/null", $logs);
            }

            // Parse log entries
            $parsedLogs = [];
            foreach ($logs as $log) {
                // Parse fail2ban log format: 2025-11-21 14:23:45,123 fail2ban.actions [PID]: LEVEL Jail [jailname] Action IP
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+\s+fail2ban\.(\w+)\s+\[\d+\]:\s+(\w+)\s+\[?(\w+)?\]?\s*(.*)$/i', $log, $match)) {
                    $parsedLogs[] = [
                        'timestamp' => $match[1],
                        'component' => $match[2],
                        'level' => $match[3],
                        'jail' => $match[4] ?? '',
                        'message' => $match[5],
                        'raw' => $log
                    ];
                } else {
                    $parsedLogs[] = [
                        'timestamp' => '',
                        'component' => '',
                        'level' => '',
                        'jail' => '',
                        'message' => $log,
                        'raw' => $log
                    ];
                }
            }

            echo json_encode(['success' => true, 'data' => $parsedLogs]);
            break;

        case 'get_jail_details':
            $jail = $_GET['jail'] ?? '';
            if (empty($jail)) {
                throw new Exception('Jail name is required');
            }

            $details = [
                'jail' => $jail,
                'status' => [],
                'config' => [],
                'recent_bans' => [],
                'recent_logs' => []
            ];

            // Get jail status
            exec("sudo fail2ban-client status {$jail} 2>/dev/null", $statusOutput, $statusCode);
            if ($statusCode === 0) {
                foreach ($statusOutput as $line) {
                    if (preg_match('/Currently failed:\s*(\d+)/', $line, $m)) {
                        $details['status']['currently_failed'] = (int)$m[1];
                    } elseif (preg_match('/Total failed:\s*(\d+)/', $line, $m)) {
                        $details['status']['total_failed'] = (int)$m[1];
                    } elseif (preg_match('/Currently banned:\s*(\d+)/', $line, $m)) {
                        $details['status']['currently_banned'] = (int)$m[1];
                    } elseif (preg_match('/Total banned:\s*(\d+)/', $line, $m)) {
                        $details['status']['total_banned'] = (int)$m[1];
                    } elseif (strpos($line, 'Banned IP list:') !== false) {
                        $ipLine = substr($line, strpos($line, ':') + 1);
                        $details['status']['banned_ips'] = array_values(array_filter(array_map('trim', explode(' ', $ipLine))));
                    }
                }
            }

            // Get jail configuration
            exec("sudo fail2ban-client get {$jail} bantime 2>/dev/null", $bantimeOutput);
            exec("sudo fail2ban-client get {$jail} findtime 2>/dev/null", $findtimeOutput);
            exec("sudo fail2ban-client get {$jail} maxretry 2>/dev/null", $maxretryOutput);

            $details['config'] = [
                'bantime' => !empty($bantimeOutput) ? trim(end($bantimeOutput)) : 'N/A',
                'findtime' => !empty($findtimeOutput) ? trim(end($findtimeOutput)) : 'N/A',
                'maxretry' => !empty($maxretryOutput) ? trim(end($maxretryOutput)) : 'N/A',
            ];

            // Get recent logs for this jail
            exec("sudo grep -i '{$jail}' /var/log/fail2ban.log 2>/dev/null | tail -20", $logOutput);
            $details['recent_logs'] = $logOutput;

            echo json_encode(['success' => true, 'data' => $details]);
            break;

        case 'get_security_logs':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            // Get recent auth failures from system logs
            exec("sudo tail -n {$limit} /var/log/auth.log 2>/dev/null | grep -i 'failed\|banned' | tail -20", $output);

            echo json_encode(['success' => true, 'data' => $output]);
            break;

        case 'get_available_jails':
            // Get all available jails and their service status
            $availableJails = [
                'sshd' => [
                    'name' => 'sshd',
                    'description' => 'SSH authentication protection',
                    'service_checks' => ['sshd', 'ssh'],
                    'log_path' => '/var/log/auth.log',
                ],
                'nginx-http-auth' => [
                    'name' => 'nginx-http-auth',
                    'description' => 'Nginx HTTP basic auth protection',
                    'service_checks' => ['nginx'],
                    'log_path' => '/var/log/nginx/error.log',
                ],
                'nginx-botsearch' => [
                    'name' => 'nginx-botsearch',
                    'description' => 'Nginx bot/scanner protection',
                    'service_checks' => ['nginx'],
                    'log_path' => '/var/log/nginx/access.log',
                ],
                'nginx-limit-req' => [
                    'name' => 'nginx-limit-req',
                    'description' => 'Nginx rate limiting protection',
                    'service_checks' => ['nginx'],
                    'log_path' => '/var/log/nginx/error.log',
                ],
                'apache-auth' => [
                    'name' => 'apache-auth',
                    'description' => 'Apache authentication protection',
                    'service_checks' => ['apache2', 'httpd'],
                    'log_path' => '/var/log/apache2/error.log',
                ],
                'php-url-fopen' => [
                    'name' => 'php-url-fopen',
                    'description' => 'PHP URL injection protection',
                    'service_checks' => ['nginx', 'apache2', 'httpd'],
                    'log_path' => '/var/log/nginx/access.log',
                ],
            ];

            $jailsData = [];

            // Get currently active jails from fail2ban
            $activeJails = [];
            exec('sudo fail2ban-client status 2>/dev/null', $f2bOutput, $f2bCode);
            if ($f2bCode === 0) {
                foreach ($f2bOutput as $line) {
                    if (strpos($line, 'Jail list:') !== false) {
                        $jailLine = substr($line, strpos($line, ':') + 1);
                        $activeJails = array_map('trim', explode(',', $jailLine));
                    }
                }
            }

            // Read jail configurations to check enabled/disabled status
            // Priority: jail.d/fos-overrides.local > jail.local > defaults
            $jailLocalConfig = [];

            // Helper function to parse jail config
            $parseJailConfig = function($content) use (&$jailLocalConfig) {
                if (!$content) return;
                preg_match_all('/\[([^\]]+)\]\s*\n((?:(?!\[)[^\n]*\n)*)/s', $content, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $jailName = $match[1];
                    $lines = explode("\n", $match[2]);
                    foreach ($lines as $line) {
                        if (preg_match('/^\s*(enabled)\s*=\s*(.+)$/i', trim($line), $m)) {
                            $jailLocalConfig[$jailName]['enabled'] = strtolower(trim($m[2])) === 'true';
                        }
                    }
                }
            };

            // Read jail.local first (lower priority)
            $jailLocalPath = '/etc/fail2ban/jail.local';
            if (file_exists($jailLocalPath)) {
                $parseJailConfig(@file_get_contents($jailLocalPath));
            }

            // Read jail.d/fos-overrides.local (highest priority, overrides jail.local)
            $jailDLocalPath = '/etc/fail2ban/jail.d/fos-overrides.local';
            exec("sudo cat {$jailDLocalPath} 2>/dev/null", $jailDContent, $jailDCode);
            if ($jailDCode === 0) {
                $parseJailConfig(implode("\n", $jailDContent));
            }

            foreach ($availableJails as $jailId => $jail) {
                // Check if the required service is running
                $serviceRunning = false;
                foreach ($jail['service_checks'] as $service) {
                    exec("systemctl is-active {$service} 2>/dev/null", $serviceOutput, $serviceCode);
                    if ($serviceCode === 0) {
                        $serviceRunning = true;
                        break;
                    }
                    $serviceOutput = [];
                }

                // Check if log file exists (use sudo test since www-data may not have read permission)
                $logCheckOutput = [];
                exec("sudo test -f " . escapeshellarg($jail['log_path']) . " && echo 'exists'", $logCheckOutput, $logCheckCode);
                $logExists = ($logCheckCode === 0 && !empty($logCheckOutput));

                // Determine if jail is enabled
                $isActive = in_array($jailId, $activeJails);
                $isEnabled = isset($jailLocalConfig[$jailId]['enabled'])
                    ? $jailLocalConfig[$jailId]['enabled']
                    : $isActive;

                $jailsData[] = [
                    'id' => $jailId,
                    'name' => $jail['name'],
                    'description' => $jail['description'],
                    'service_running' => $serviceRunning,
                    'log_exists' => $logExists,
                    // Can enable if service is running (log file will be created automatically if missing)
                    'can_enable' => $serviceRunning,
                    'is_active' => $isActive,
                    'is_enabled' => $isEnabled,
                    'log_path' => $jail['log_path'],
                ];
            }

            echo json_encode(['success' => true, 'data' => $jailsData]);
            break;

        case 'toggle_jail':
            $input = json_decode(file_get_contents('php://input'), true);
            $jailName = $input['jail'] ?? '';
            $enable = $input['enable'] ?? false;

            if (empty($jailName)) {
                throw new Exception('Jail name is required');
            }

            // Sanitize jail name (alphanumeric, dash, underscore only)
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jailName)) {
                throw new Exception('Invalid jail name');
            }

            // Jail to log file mapping
            $jailLogPaths = [
                'sshd' => '/var/log/auth.log',
                'nginx-http-auth' => '/var/log/nginx/error.log',
                'nginx-botsearch' => '/var/log/nginx/access.log',
                'nginx-limit-req' => '/var/log/nginx/error.log',
                'apache-auth' => '/var/log/apache2/error.log',
                'php-url-fopen' => '/var/log/nginx/access.log',
            ];

            $createdLogFile = null;

            // If enabling, ensure log file exists
            if ($enable && isset($jailLogPaths[$jailName])) {
                $logPath = $jailLogPaths[$jailName];
                exec("sudo test -f " . escapeshellarg($logPath), $testOutput, $testCode);
                if ($testCode !== 0) {
                    // Create the log file and its parent directory
                    $logDir = dirname($logPath);
                    exec("sudo mkdir -p " . escapeshellarg($logDir) . " 2>&1");
                    exec("sudo touch " . escapeshellarg($logPath) . " 2>&1", $touchOutput, $touchCode);
                    if ($touchCode === 0) {
                        exec("sudo chmod 640 " . escapeshellarg($logPath) . " 2>&1");
                        $createdLogFile = $logPath;
                    } else {
                        throw new Exception("Failed to create log file: {$logPath}");
                    }
                }
            }

            // Use jail.d/fos-overrides.local which has highest priority
            // This overrides both jail.local and defaults-debian.conf
            $jailDLocalPath = '/etc/fail2ban/jail.d/fos-overrides.local';
            $config = '';

            // Read existing config if it exists
            exec("sudo cat {$jailDLocalPath} 2>/dev/null", $configLines, $readCode);
            if ($readCode === 0) {
                $config = implode("\n", $configLines);
            } else {
                $config = "# FOS Streaming jail overrides\n# This file overrides defaults-debian.conf\n\n";
            }

            // Check if jail section exists
            $pattern = '/\[' . preg_quote($jailName, '/') . '\][^\[]*/s';
            $newSection = "[{$jailName}]\nenabled = " . ($enable ? 'true' : 'false') . "\n\n";

            if (preg_match($pattern, $config)) {
                // Update existing section
                $config = preg_replace($pattern, $newSection, $config);
            } else {
                // Add new section
                $config .= "\n" . $newSection;
            }

            // Write updated config
            $tempFile = tempnam(sys_get_temp_dir(), 'jail_');
            file_put_contents($tempFile, $config);
            exec("sudo cp {$tempFile} {$jailDLocalPath} 2>&1", $cpOutput, $cpCode);
            unlink($tempFile);

            if ($cpCode !== 0) {
                throw new Exception('Failed to update jail configuration');
            }

            // Reload fail2ban to apply changes
            exec('sudo fail2ban-client reload 2>&1', $reloadOutput, $reloadCode);

            $message = "Jail '{$jailName}' " . ($enable ? 'enabled' : 'disabled') . ' successfully';
            if ($createdLogFile) {
                $message .= ". Created missing log file: {$createdLogFile}";
            }

            if ($reloadCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'created_log_file' => $createdLogFile
                ]);
            } else {
                // Still return success if config was written but reload failed
                echo json_encode([
                    'success' => true,
                    'message' => $message . '. Note: fail2ban reload required.',
                    'reload_failed' => true,
                    'created_log_file' => $createdLogFile
                ]);
            }
            break;

        case 'update_jail_config':
            $input = json_decode(file_get_contents('php://input'), true);
            $jailName = $input['jail'] ?? '';
            $bantime = isset($input['bantime']) ? (int)$input['bantime'] : null;
            $findtime = isset($input['findtime']) ? (int)$input['findtime'] : null;
            $maxretry = isset($input['maxretry']) ? (int)$input['maxretry'] : null;

            if (empty($jailName)) {
                throw new Exception('Jail name is required');
            }

            // Sanitize jail name
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jailName)) {
                throw new Exception('Invalid jail name');
            }

            // Read current jail.local
            $jailLocalPath = '/etc/fail2ban/jail.local';
            $config = file_exists($jailLocalPath) ? file_get_contents($jailLocalPath) : '';

            // Build new section content
            $sectionLines = ["[{$jailName}]", "enabled = true"];
            if ($bantime !== null) $sectionLines[] = "bantime = {$bantime}";
            if ($findtime !== null) $sectionLines[] = "findtime = {$findtime}";
            if ($maxretry !== null) $sectionLines[] = "maxretry = {$maxretry}";
            $newSection = implode("\n", $sectionLines) . "\n\n";

            // Check if jail section exists
            $pattern = '/\[' . preg_quote($jailName, '/') . '\][^\[]*/s';

            if (preg_match($pattern, $config)) {
                $config = preg_replace($pattern, $newSection, $config);
            } else {
                $config .= "\n" . $newSection;
            }

            // Write updated config
            $tempFile = tempnam(sys_get_temp_dir(), 'jail_');
            file_put_contents($tempFile, $config);
            exec("sudo cp {$tempFile} {$jailLocalPath} 2>&1", $cpOutput, $cpCode);
            unlink($tempFile);

            if ($cpCode !== 0) {
                throw new Exception('Failed to update jail configuration');
            }

            // Reload fail2ban
            exec('sudo fail2ban-client reload 2>&1', $reloadOutput, $reloadCode);

            echo json_encode([
                'success' => true,
                'message' => "Jail '{$jailName}' configuration updated successfully"
            ]);
            break;

        case 'reload_ufw':
            exec('sudo ufw reload 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => 'UFW reloaded successfully']);
            } else {
                throw new Exception('Failed to reload UFW: ' . implode(' ', $output));
            }
            break;

        case 'reset_ufw':
            // Reset UFW to defaults - this removes all rules
            exec('sudo ufw --force reset 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                // Mark all rules in database as unapplied
                UfwRule::query()->update(['applied' => false]);

                echo json_encode([
                    'success' => true,
                    'message' => 'UFW reset to defaults. All rules have been removed and marked as unapplied.'
                ]);
            } else {
                throw new Exception('Failed to reset UFW: ' . implode(' ', $output));
            }
            break;

        case 'restart_fail2ban':
            exec('sudo systemctl restart fail2ban 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode(['success' => true, 'message' => 'fail2ban restarted successfully']);
            } else {
                throw new Exception('Failed to restart fail2ban: ' . implode(' ', $output));
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
