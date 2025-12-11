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

        case 'get_security_logs':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            // Get recent auth failures from system logs
            exec("sudo tail -n {$limit} /var/log/auth.log 2>/dev/null | grep -i 'failed\|banned' | tail -20", $output);

            echo json_encode(['success' => true, 'data' => $output]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
