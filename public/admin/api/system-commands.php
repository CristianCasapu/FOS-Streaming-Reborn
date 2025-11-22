<?php
/**
 * System Commands API Endpoint
 * Execute system commands with sudo using stored credentials
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/SystemCommands.php';

use App\SystemCommands;

logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'execute';
$adminId = session('admin_id');

if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    switch ($action) {
        case 'execute':
            // Execute a system command
            $input = json_decode(file_get_contents('php://input'), true);
            $command = $input['command'] ?? '';
            $description = $input['description'] ?? 'Manual command execution';

            if (empty($command)) {
                throw new Exception('Command is required');
            }

            $result = SystemCommands::execute($command, $adminId, $description);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Command executed successfully' : 'Command execution failed',
                'data' => $result
            ]);
            break;

        case 'install_package':
            // Install a package using apt-get
            $input = json_decode(file_get_contents('php://input'), true);
            $package = $input['package'] ?? '';

            if (empty($package)) {
                throw new Exception('Package name is required');
            }

            $result = SystemCommands::installPackage($package, $adminId);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? "Package '{$package}' installed successfully" : "Failed to install package '{$package}'",
                'data' => $result
            ]);
            break;

        case 'restart_service':
            // Restart a service
            $input = json_decode(file_get_contents('php://input'), true);
            $service = $input['service'] ?? '';

            if (empty($service)) {
                throw new Exception('Service name is required');
            }

            $result = SystemCommands::restartService($service, $adminId);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? "Service '{$service}' restarted successfully" : "Failed to restart service '{$service}'",
                'data' => $result
            ]);
            break;

        case 'service_status':
            // Get service status
            $input = json_decode(file_get_contents('php://input'), true);
            $service = $input['service'] ?? '';

            if (empty($service)) {
                throw new Exception('Service name is required');
            }

            $result = SystemCommands::getServiceStatus($service, $adminId);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? "Service status retrieved" : "Failed to get service status",
                'data' => $result
            ]);
            break;

        case 'get_logs':
            // Get command execution logs
            $limit = $_GET['limit'] ?? 50;
            $logs = SystemCommandLog::getRecent((int)$limit);

            echo json_encode([
                'success' => true,
                'data' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'admin_id' => $log->admin_id,
                        'command' => $log->command,
                        'description' => $log->description,
                        'output' => $log->output,
                        'exit_code' => $log->exit_code,
                        'execution_time' => $log->execution_time,
                        'success' => $log->success,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            ]);
            break;

        case 'get_failed_logs':
            // Get failed command logs
            $limit = $_GET['limit'] ?? 50;
            $logs = SystemCommandLog::getFailedCommands((int)$limit);

            echo json_encode([
                'success' => true,
                'data' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'admin_id' => $log->admin_id,
                        'command' => $log->command,
                        'description' => $log->description,
                        'output' => $log->output,
                        'exit_code' => $log->exit_code,
                        'execution_time' => $log->execution_time,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            ]);
            break;

        case 'allowed_commands':
            // Get list of allowed commands
            echo json_encode([
                'success' => true,
                'data' => SystemCommands::getAllowedCommands()
            ]);
            break;

        case 'quick_actions':
            // Get predefined quick actions
            $quickActions = [
                [
                    'id' => 'install_ufw',
                    'name' => 'Install UFW Firewall',
                    'description' => 'Install UFW (Uncomplicated Firewall)',
                    'command' => 'apt-get install -y ufw',
                    'category' => 'packages'
                ],
                [
                    'id' => 'install_fail2ban',
                    'name' => 'Install fail2ban',
                    'description' => 'Install fail2ban intrusion prevention system',
                    'command' => 'apt-get install -y fail2ban',
                    'category' => 'packages'
                ],
                [
                    'id' => 'restart_nginx',
                    'name' => 'Restart Nginx',
                    'description' => 'Restart Nginx web server',
                    'command' => 'systemctl restart nginx',
                    'category' => 'services'
                ],
                [
                    'id' => 'restart_php_fpm',
                    'name' => 'Restart PHP-FPM',
                    'description' => 'Restart PHP-FPM service',
                    'command' => 'systemctl restart php8.4-fpm',
                    'category' => 'services'
                ],
                [
                    'id' => 'restart_mariadb',
                    'name' => 'Restart MariaDB',
                    'description' => 'Restart MariaDB database server',
                    'command' => 'systemctl restart mariadb',
                    'category' => 'services'
                ],
                [
                    'id' => 'enable_ufw',
                    'name' => 'Enable UFW',
                    'description' => 'Enable UFW firewall',
                    'command' => 'ufw --force enable',
                    'category' => 'firewall'
                ],
                [
                    'id' => 'start_fail2ban',
                    'name' => 'Start fail2ban',
                    'description' => 'Start fail2ban service',
                    'command' => 'systemctl start fail2ban',
                    'category' => 'services'
                ],
                [
                    'id' => 'system_update',
                    'name' => 'Update Package List',
                    'description' => 'Update APT package list',
                    'command' => 'apt-get update',
                    'category' => 'system'
                ],
            ];

            echo json_encode([
                'success' => true,
                'data' => $quickActions
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
