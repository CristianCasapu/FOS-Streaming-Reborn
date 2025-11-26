<?php
/**
 * Settings API Endpoint
 * Manages system configuration and streaming server settings
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/SystemCommands.php';
require_once __DIR__ . '/../../../app/Services/PathDetectionService.php';

use Illuminate\Support\Facades\Crypt;
use App\SystemCommands;
use App\Services\PathDetectionService;

/**
 * Generate PHP-FPM streaming configuration file
 *
 * @param array $phpFpmInfo Configuration from PathDetectionService::detectPhpFpm()
 * @return bool True if config was created successfully
 */
function generatePhpFpmStreamingConfig(array $phpFpmInfo): bool
{
    $configPath = $phpFpmInfo['config_path'];
    $poolPath = $phpFpmInfo['pool_path'];
    $user = $phpFpmInfo['user'];
    $group = $phpFpmInfo['group'];
    $socket = $phpFpmInfo['socket'];
    $pidFile = $phpFpmInfo['pid_file'];
    $errorLog = $phpFpmInfo['error_log'];
    $slowLog = $phpFpmInfo['slow_log'];

    // Create directories if needed
    $configDir = dirname($configPath);
    $poolDir = dirname($poolPath);
    $logDir = dirname($errorLog);

    @mkdir($configDir, 0755, true);
    @mkdir($poolDir, 0755, true);
    @mkdir($logDir, 0755, true);

    // Generate main php-fpm.conf
    $mainConfig = <<<CONF
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; FOS-Streaming PHP-FPM Streaming Service Configuration
; Auto-generated - DO NOT EDIT DIRECTLY
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

[global]
pid = {$pidFile}
error_log = {$errorLog}
log_level = warning
daemonize = yes

include = {$poolDir}/*.conf
CONF;

    // Generate pool configuration
    $poolConfig = <<<CONF
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; FOS-Streaming PHP-FPM Pool Configuration
; Pool for handling streaming authentication requests
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

[streaming]
user = {$user}
group = {$group}
listen = {$socket}
listen.owner = {$user}
listen.group = {$group}
listen.mode = 0660

; Process Manager Settings
pm = {$phpFpmInfo['pm']}
pm.max_children = {$phpFpmInfo['pm_max_children']}
pm.start_servers = {$phpFpmInfo['pm_start_servers']}
pm.min_spare_servers = {$phpFpmInfo['pm_min_spare_servers']}
pm.max_spare_servers = {$phpFpmInfo['pm_max_spare_servers']}
pm.max_requests = {$phpFpmInfo['pm_max_requests']}

; Timeouts
request_terminate_timeout = 30s
request_slowlog_timeout = 10s

; Logging
slowlog = {$slowLog}
catch_workers_output = yes
decorate_workers_output = no

; Security
security.limit_extensions = .php
CONF;

    // Write configs
    $mainWritten = file_put_contents($configPath, $mainConfig) !== false;
    $poolWritten = file_put_contents($poolPath, $poolConfig) !== false;

    return $mainWritten && $poolWritten;
}

logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // Get system settings (only one settings record exists)
            $setting = Setting::first();

            if (!$setting) {
                throw new Exception('Settings not found. Please initialize the database.');
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $setting->id,
                    'ffmpeg_path' => $setting->ffmpeg_path,
                    'ffprobe_path' => $setting->ffprobe_path,
                    'webip' => $setting->webip,
                    'webport' => $setting->webport,
                    'hlsfolder' => $setting->hlsfolder,
                    'logourl' => $setting->logourl,
                    'faviconurl' => $setting->faviconurl ?? null,
                    'user_agent' => $setting->user_agent,
                    'sudo_user' => $setting->sudo_user ?? '',
                    'sudo_password_set' => !empty($setting->sudo_password),
                    'system_commands_enabled' => (bool)($setting->system_commands_enabled ?? false),
                    'last_command_at' => $setting->last_command_at ?? null,
                    // Streaming Protocol Settings
                    'streaming_protocol' => $setting->streaming_protocol ?? 'both',
                    'streams_path' => $setting->streams_path ?? '/home/casapu/projects/FOS-Streaming-v69/fospackv69/fos/streams',
                    'rtmp_port' => (int)($setting->rtmp_port ?? 1935),
                    'streaming_port' => (int)($setting->streaming_port ?? 8000),
                    'nginx_user' => $setting->nginx_user ?? null,
                    'nginx_worker_processes' => (int)($setting->nginx_worker_processes ?? 0),
                    // MPEG-DASH Settings
                    'dash_fragment' => (int)($setting->dash_fragment ?? 4),
                    'dash_playlist_length' => (int)($setting->dash_playlist_length ?? 30),
                    'dash_nested' => (bool)($setting->dash_nested ?? true),
                    'dash_cleanup' => (bool)($setting->dash_cleanup ?? true),
                    // HLS Settings
                    'hls_fragment' => (int)($setting->hls_fragment ?? 3),
                    'hls_playlist_length' => (int)($setting->hls_playlist_length ?? 60),
                    'hls_nested' => (bool)($setting->hls_nested ?? true),
                    'hls_cleanup' => (bool)($setting->hls_cleanup ?? true),
                    // Nginx Paths
                    'nginx_config_path' => $setting->nginx_config_path ?? null,
                    'nginx_binary_path' => $setting->nginx_binary_path ?? null,
                    // Trial Settings
                    'trial_enabled' => (bool)($setting->trial_enabled ?? true),
                    'trial_duration_hours' => (int)($setting->trial_duration_hours ?? 24),
                    'trial_requires_approval' => (bool)($setting->trial_requires_approval ?? false),
                    'max_trials_per_user' => (int)($setting->max_trials_per_user ?? 1),
                    // Device Security Settings
                    'device_concurrent_stream_grace_seconds' => (int)($setting->device_concurrent_stream_grace_seconds ?? 30),
                    'device_session_timeout_minutes' => (int)($setting->device_session_timeout_minutes ?? 60),
                    'device_max_registration_per_day' => (int)($setting->device_max_registration_per_day ?? 5),
                    'device_fingerprint_ttl_days' => (int)($setting->device_fingerprint_ttl_days ?? 365),
                ]
            ]);
            break;

        case 'update':
            $input = json_decode(file_get_contents('php://input'), true);
            $setting = Setting::first();

            if (!$setting) {
                throw new Exception('Settings not found');
            }

            $portChanged = false;
            $needsNginxRestart = false;

            // Update FFmpeg paths
            if (isset($input['ffmpeg_path'])) {
                $setting->ffmpeg_path = $input['ffmpeg_path'];
            }

            if (isset($input['ffprobe_path'])) {
                $setting->ffprobe_path = $input['ffprobe_path'];
            }

            // Update web IP
            if (isset($input['webip'])) {
                $setting->webip = $input['webip'];
            }

            // Update web port (requires nginx restart)
            if (isset($input['webport']) && $setting->webport != $input['webport']) {
                $newPort = $input['webport'] ?: 8000;
                $setting->webport = $newPort;
                $portChanged = true;

                // Generate new nginx configuration
                if (function_exists('generatEginxConfPort')) {
                    generatEginxConfPort($newPort);
                }
                $needsNginxRestart = true;
            }

            // Update HLS folder
            if (isset($input['hlsfolder'])) {
                $setting->hlsfolder = $input['hlsfolder'];
                // Create directory if it doesn't exist
                if (!file_exists($input['hlsfolder'])) {
                    @mkdir($input['hlsfolder'], 0777, true);
                }
            }

            // Update logo URL
            if (isset($input['logourl'])) {
                $setting->logourl = $input['logourl'];
            }

            // Update favicon URL
            if (isset($input['faviconurl'])) {
                $setting->faviconurl = $input['faviconurl'];
            }

            // Update user agent
            if (isset($input['user_agent'])) {
                $setting->user_agent = $input['user_agent'];
            }

            // Update sudo user
            if (isset($input['sudo_user'])) {
                $setting->sudo_user = $input['sudo_user'];
            }

            // Update sudo password (encrypt it)
            if (isset($input['sudo_password']) && !empty($input['sudo_password'])) {
                try {
                    $setting->sudo_password = Crypt::encryptString($input['sudo_password']);
                } catch (\Exception $e) {
                    throw new Exception('Failed to encrypt sudo password: ' . $e->getMessage());
                }
            }

            // Update system commands enabled flag
            if (isset($input['system_commands_enabled'])) {
                $setting->system_commands_enabled = (bool)$input['system_commands_enabled'];
            }

            // ============================================================
            // Streaming Protocol Settings
            // ============================================================
            if (isset($input['streaming_protocol'])) {
                $validProtocols = ['dash', 'hls', 'both'];
                if (in_array($input['streaming_protocol'], $validProtocols)) {
                    $setting->streaming_protocol = $input['streaming_protocol'];
                }
            }

            if (isset($input['streams_path'])) {
                $setting->streams_path = $input['streams_path'];
                // Create directories if they don't exist
                $streamsPath = $input['streams_path'];
                @mkdir($streamsPath . '/dash', 0755, true);
                @mkdir($streamsPath . '/hls', 0755, true);
            }

            if (isset($input['rtmp_port'])) {
                $setting->rtmp_port = (int)$input['rtmp_port'];
                $needsNginxRestart = true;
            }

            if (isset($input['streaming_port'])) {
                $setting->streaming_port = (int)$input['streaming_port'];
                $needsNginxRestart = true;
            }

            if (isset($input['nginx_user'])) {
                $setting->nginx_user = $input['nginx_user'];
            }

            if (isset($input['nginx_worker_processes'])) {
                $setting->nginx_worker_processes = (int)$input['nginx_worker_processes'];
            }

            // MPEG-DASH Settings
            if (isset($input['dash_fragment'])) {
                $setting->dash_fragment = (int)$input['dash_fragment'];
            }

            if (isset($input['dash_playlist_length'])) {
                $setting->dash_playlist_length = (int)$input['dash_playlist_length'];
            }

            if (isset($input['dash_nested'])) {
                $setting->dash_nested = (bool)$input['dash_nested'];
            }

            if (isset($input['dash_cleanup'])) {
                $setting->dash_cleanup = (bool)$input['dash_cleanup'];
            }

            // HLS Settings
            if (isset($input['hls_fragment'])) {
                $setting->hls_fragment = (int)$input['hls_fragment'];
            }

            if (isset($input['hls_playlist_length'])) {
                $setting->hls_playlist_length = (int)$input['hls_playlist_length'];
            }

            if (isset($input['hls_nested'])) {
                $setting->hls_nested = (bool)$input['hls_nested'];
            }

            if (isset($input['hls_cleanup'])) {
                $setting->hls_cleanup = (bool)$input['hls_cleanup'];
            }

            // Nginx Paths
            if (isset($input['nginx_config_path'])) {
                $setting->nginx_config_path = $input['nginx_config_path'];
            }

            if (isset($input['nginx_binary_path'])) {
                $setting->nginx_binary_path = $input['nginx_binary_path'];
            }

            // ============================================================
            // Trial Settings
            // ============================================================
            if (isset($input['trial_enabled'])) {
                $setting->trial_enabled = (bool)$input['trial_enabled'];
            }

            if (isset($input['trial_duration_hours'])) {
                $setting->trial_duration_hours = (int)$input['trial_duration_hours'];
            }

            if (isset($input['trial_requires_approval'])) {
                $setting->trial_requires_approval = (bool)$input['trial_requires_approval'];
            }

            if (isset($input['max_trials_per_user'])) {
                $setting->max_trials_per_user = (int)$input['max_trials_per_user'];
            }

            // ============================================================
            // Device Security Settings
            // ============================================================
            if (isset($input['device_concurrent_stream_grace_seconds'])) {
                $setting->device_concurrent_stream_grace_seconds = (int)$input['device_concurrent_stream_grace_seconds'];
            }

            if (isset($input['device_session_timeout_minutes'])) {
                $setting->device_session_timeout_minutes = (int)$input['device_session_timeout_minutes'];
            }

            if (isset($input['device_max_registration_per_day'])) {
                $setting->device_max_registration_per_day = (int)$input['device_max_registration_per_day'];
            }

            if (isset($input['device_fingerprint_ttl_days'])) {
                $setting->device_fingerprint_ttl_days = (int)$input['device_fingerprint_ttl_days'];
            }

            $setting->save();

            $message = 'Settings updated successfully';
            if ($needsNginxRestart) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $newUrl = $protocol . "://" . $_SERVER['SERVER_ADDR'] . ":" . $setting->webport . "/admin";
                $message .= '. IMPORTANT: Restart Nginx manually (killall -9 nginx && /usr/local/nginx/sbin/nginx) and navigate to: ' . $newUrl;
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'data' => [
                    'needs_nginx_restart' => $needsNginxRestart,
                    'new_url' => $needsNginxRestart ? ($protocol . "://" . $_SERVER['SERVER_ADDR'] . ":" . $setting->webport . "/admin") : null,
                    'sudo_password_set' => !empty($setting->sudo_password),
                    'system_commands_enabled' => (bool)$setting->system_commands_enabled,
                ]
            ]);
            break;

        case 'test_ffmpeg':
            // Test FFmpeg installation
            $setting = Setting::first();
            $ffmpegPath = $setting->ffmpeg_path ?? 'ffmpeg';

            $output = [];
            $returnCode = 0;
            exec($ffmpegPath . ' -version 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'FFmpeg is working correctly',
                    'data' => [
                        'version' => $output[0] ?? 'Unknown',
                        'path' => $ffmpegPath
                    ]
                ]);
            } else {
                throw new Exception('FFmpeg not found or not working at: ' . $ffmpegPath);
            }
            break;

        case 'test_ffprobe':
            // Test FFprobe installation
            $setting = Setting::first();
            $ffprobePath = $setting->ffprobe_path ?? 'ffprobe';

            $output = [];
            $returnCode = 0;
            exec($ffprobePath . ' -version 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'FFprobe is working correctly',
                    'data' => [
                        'version' => $output[0] ?? 'Unknown',
                        'path' => $ffprobePath
                    ]
                ]);
            } else {
                throw new Exception('FFprobe not found or not working at: ' . $ffprobePath);
            }
            break;

        case 'system_info':
            // Get system information
            $setting = Setting::first();

            echo json_encode([
                'success' => true,
                'data' => [
                    'php_version' => phpversion(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
                    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                    'current_port' => $setting->webport ?? 8000,
                    'hls_folder_exists' => file_exists($setting->hlsfolder ?? ''),
                    'hls_folder_writable' => is_writable($setting->hlsfolder ?? ''),
                ]
            ]);
            break;

        case 'test_sudo':
            // Test sudo password
            $result = SystemCommands::testSudoPassword();

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Sudo password is working correctly. Current user: ' . trim($result['output']) : 'Sudo password test failed',
                'data' => $result
            ]);
            break;

        case 'clear_sudo':
            // Clear sudo password
            $setting = Setting::first();

            if (!$setting) {
                throw new Exception('Settings not found');
            }

            $setting->sudo_password = null;
            $setting->sudo_user = null;
            $setting->system_commands_enabled = false;
            $setting->save();

            echo json_encode([
                'success' => true,
                'message' => 'Sudo credentials cleared successfully'
            ]);
            break;

        // ============================================================
        // Nginx Streaming Server Management
        // ============================================================

        case 'test_nginx_config':
            // Test nginx streaming configuration
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $nginxBinary = $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'];
            $nginxConfig = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];

            $output = [];
            $returnCode = 0;
            exec("{$nginxBinary} -t -c {$nginxConfig} 2>&1", $output, $returnCode);

            echo json_encode([
                'success' => $returnCode === 0,
                'message' => $returnCode === 0 ? 'Nginx configuration is valid' : 'Nginx configuration has errors',
                'data' => [
                    'output' => implode("\n", $output),
                    'binary' => $nginxBinary,
                    'config' => $nginxConfig
                ]
            ]);
            break;

        case 'nginx_status':
            // Get nginx streaming server status
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $pidFile = '/tmp/fos-streaming-nginx.pid';
            $isRunning = false;
            $pid = null;

            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && is_numeric($pid)) {
                    // Check if process is running
                    exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                    $isRunning = ($returnCode === 0);
                }
            }

            // Check streams directory
            $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
            $dashExists = file_exists($streamsPath . '/dash');
            $hlsExists = file_exists($streamsPath . '/hls');

            echo json_encode([
                'success' => true,
                'data' => [
                    'running' => $isRunning,
                    'pid' => $isRunning ? (int)$pid : null,
                    'pid_file' => $pidFile,
                    'streams_path' => $streamsPath,
                    'dash_dir_exists' => $dashExists,
                    'hls_dir_exists' => $hlsExists,
                    'streaming_protocol' => $setting->streaming_protocol ?? 'both',
                    'rtmp_port' => (int)($setting->rtmp_port ?? 1935),
                    'streaming_port' => (int)($setting->streaming_port ?? 8000),
                    'nginx_binary_path' => $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'],
                    'nginx_config_path' => $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'],
                    'detected_user' => $detected['user'],
                    'detected_group' => $detected['group'],
                ]
            ]);
            break;

        case 'start_nginx_streaming':
            // Start nginx streaming server
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $nginxBinary = $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'];
            $nginxConfig = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];

            // Check if already running
            $pidFile = '/tmp/fos-streaming-nginx.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    throw new Exception('Nginx streaming server is already running (PID: ' . $pid . ')');
                }
            }

            // Create streams directories
            $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
            @mkdir($streamsPath . '/dash', 0755, true);
            @mkdir($streamsPath . '/hls', 0755, true);

            // Start nginx
            $output = [];
            $returnCode = 0;
            exec("{$nginxBinary} -c {$nginxConfig} 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Failed to start nginx: ' . implode("\n", $output));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Nginx streaming server started successfully',
                'data' => [
                    'binary' => $nginxBinary,
                    'config' => $nginxConfig
                ]
            ]);
            break;

        case 'stop_nginx_streaming':
            // Stop nginx streaming server
            $pidFile = '/tmp/fos-streaming-nginx.pid';

            if (!file_exists($pidFile)) {
                throw new Exception('Nginx streaming server is not running (no PID file)');
            }

            $pid = trim(file_get_contents($pidFile));
            if (!$pid || !is_numeric($pid)) {
                throw new Exception('Invalid PID in file');
            }

            // Send quit signal
            exec("kill -QUIT {$pid} 2>&1", $output, $returnCode);

            // Wait a moment and verify it stopped
            sleep(1);
            exec("ps -p {$pid} > /dev/null 2>&1", $checkOutput, $checkCode);

            if ($checkCode === 0) {
                // Force kill if still running
                exec("kill -9 {$pid} 2>&1");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Nginx streaming server stopped',
                'data' => [
                    'pid' => (int)$pid
                ]
            ]);
            break;

        case 'restart_nginx_streaming':
            // Restart nginx streaming server (graceful reload)
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $nginxBinary = $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'];
            $nginxConfig = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];
            $pidFile = '/tmp/fos-streaming-nginx.pid';

            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    // Graceful reload
                    exec("{$nginxBinary} -c {$nginxConfig} -s reload 2>&1", $output, $returnCode);
                    if ($returnCode === 0) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Nginx streaming server reloaded gracefully'
                        ]);
                        break;
                    }
                }
            }

            // If not running or reload failed, start fresh
            $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
            @mkdir($streamsPath . '/dash', 0755, true);
            @mkdir($streamsPath . '/hls', 0755, true);

            exec("{$nginxBinary} -c {$nginxConfig} 2>&1", $output, $returnCode);

            echo json_encode([
                'success' => $returnCode === 0,
                'message' => $returnCode === 0 ? 'Nginx streaming server started' : 'Failed to start nginx',
                'data' => ['output' => implode("\n", $output)]
            ]);
            break;

        // ============================================================
        // Auto-Detection & Configuration Generation
        // ============================================================

        case 'auto_detect_paths':
            // Auto-detect system paths, user/group, and binaries
            $detected = PathDetectionService::detectAll();
            $setting = Setting::first();

            // Add current settings for comparison
            $current = [
                'nginx_user' => $setting->nginx_user,
                'streams_path' => $setting->streams_path,
                'nginx_binary_path' => $setting->nginx_binary_path,
                'nginx_config_path' => $setting->nginx_config_path,
                'ffmpeg_path' => $setting->ffmpeg_path,
                'ffprobe_path' => $setting->ffprobe_path,
            ];

            echo json_encode([
                'success' => true,
                'message' => 'Paths auto-detected successfully',
                'data' => [
                    'detected' => $detected,
                    'current' => $current
                ]
            ]);
            break;

        case 'apply_detected_paths':
            // Apply auto-detected paths to settings
            $input = json_decode(file_get_contents('php://input'), true);
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();

            // Apply selected detected values
            $applied = [];

            if ($input['apply_user'] ?? false) {
                $setting->nginx_user = $detected['user'];
                $applied[] = 'nginx_user';
            }

            if ($input['apply_streams_path'] ?? false) {
                $setting->streams_path = $detected['paths']['streams_path'];
                @mkdir($detected['paths']['streams_path'] . '/dash', 0755, true);
                @mkdir($detected['paths']['streams_path'] . '/hls', 0755, true);
                $applied[] = 'streams_path';
            }

            if ($input['apply_nginx_binary'] ?? false) {
                $setting->nginx_binary_path = $detected['paths']['nginx_binary_path'];
                $applied[] = 'nginx_binary_path';
            }

            if ($input['apply_nginx_config'] ?? false) {
                $setting->nginx_config_path = $detected['paths']['nginx_config_path'];
                $applied[] = 'nginx_config_path';
            }

            if ($input['apply_ffmpeg'] ?? false) {
                $setting->ffmpeg_path = $detected['binaries']['ffmpeg'];
                $applied[] = 'ffmpeg_path';
            }

            if ($input['apply_ffprobe'] ?? false) {
                $setting->ffprobe_path = $detected['binaries']['ffprobe'];
                $applied[] = 'ffprobe_path';
            }

            $setting->save();

            echo json_encode([
                'success' => true,
                'message' => 'Applied ' . count($applied) . ' detected path(s)',
                'data' => [
                    'applied' => $applied
                ]
            ]);
            break;

        case 'generate_nginx_config':
            // Generate nginx streaming configuration dynamically
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $phpFpmInfo = PathDetectionService::detectPhpFpm();

            $user = $setting->nginx_user ?: $detected['user'];
            $group = $detected['group'];
            $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
            $logsPath = $detected['paths']['logs_path'];
            $publicPath = $detected['paths']['public_path'];
            $phpFpmSocket = PathDetectionService::getPhpFpmStreamingSocket();
            $rtmpPort = $setting->rtmp_port ?? 1935;
            $streamingPort = $setting->streaming_port ?? 8001;
            $dashFragment = $setting->dash_fragment ?? 4;
            $dashPlaylistLength = $setting->dash_playlist_length ?? 30;
            $dashNested = $setting->dash_nested ? 'on' : 'off';
            $dashCleanup = $setting->dash_cleanup ? 'on' : 'off';
            $hlsFragment = $setting->hls_fragment ?? 3;
            $hlsPlaylistLength = $setting->hls_playlist_length ?? 60;
            $hlsNested = $setting->hls_nested ? 'on' : 'off';
            $hlsCleanup = $setting->hls_cleanup ? 'on' : 'off';
            $protocol = $setting->streaming_protocol ?? 'both';

            // Generate config
            $config = <<<NGINX
#############################################################################
# FOS-Streaming Nginx Streaming Service Configuration
# Auto-generated on {$_SERVER['REQUEST_TIME']}
#
# User: {$user}:{$group}
# Ports: {$streamingPort} (HTTP streaming), {$rtmpPort} (RTMP ingest)
# Protocol: {$protocol}
#############################################################################

user {$user} {$group};
worker_processes auto;
worker_cpu_affinity auto;
worker_rlimit_nofile 1000000;
error_log {$logsPath}/nginx-streaming-error.log warn;
pid /tmp/fos-streaming-nginx.pid;

events {
    worker_connections 100000;
    use epoll;
    multi_accept on;
    accept_mutex off;
}

http {
    include mime.types;
    default_type application/octet-stream;

    types {
        application/dash+xml mpd;
        video/mp4 m4s;
    }

    log_format streaming '\$remote_addr - \$remote_user [\$time_local] '
                         '"\$request" \$status \$body_bytes_sent '
                         '"\$http_referer" "\$http_user_agent" '
                         'rt=\$request_time';

    access_log {$logsPath}/nginx-streaming-access.log streaming buffer=256k flush=5m;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 10000;
    reset_timedout_connection on;
    client_body_timeout 60;
    client_header_timeout 60;
    send_timeout 60;
    client_body_buffer_size 128k;
    client_max_body_size 10m;
    output_buffers 2 512k;
    chunked_transfer_encoding off;
    gzip off;
    server_tokens off;

    limit_conn_zone \$binary_remote_addr zone=stream_conn:50m;
    limit_req_zone \$binary_remote_addr zone=stream_req:50m rate=100r/s;

    upstream php_fpm_streaming {
        server unix:{$phpFpmSocket};
        keepalive 128;
    }

    server {
        listen {$streamingPort} reuseport;
        listen [::]:{$streamingPort} reuseport;
        server_name _;
        root {$publicPath};
        index index.php;

        limit_conn stream_conn 50;
        limit_req zone=stream_req burst=200 nodelay;

        rewrite ^/live/(.*)/(.*)/(.*)$ /stream.php?username=\$1&password=\$2&stream=\$3 break;
        rewrite ^/live/(.*)/(.*)/(.*)\.(m3u8|mpd|ts|m4s)$ /stream.php?username=\$1&password=\$2&stream=\$3&format=\$4 break;

        location /dash {
            alias {$streamsPath}/dash;
            types {
                application/dash+xml mpd;
                video/mp4 m4s mp4;
                audio/mp4 m4a;
            }
            add_header Cache-Control "no-cache, no-store, must-revalidate";
            add_header Access-Control-Allow-Origin * always;
            aio on;
            directio 512;
            sendfile on;
            access_log off;
        }

        location /hls {
            alias {$streamsPath}/hls;
            default_type application/vnd.apple.mpegurl;
            add_header Cache-Control "no-cache, no-store, must-revalidate";
            add_header Access-Control-Allow-Origin * always;
            aio on;
            directio 512;
            sendfile on;
            access_log off;
        }

        location ~ \\.php$ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\\.php)(/.+)$;
            fastcgi_pass php_fpm_streaming;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_keep_conn on;
            fastcgi_buffers 256 16k;
            fastcgi_hide_header X-Powered-By;
        }

        location /health {
            access_log off;
            return 200 'OK';
            add_header Content-Type text/plain;
        }

        location /nginx_status {
            stub_status on;
            access_log off;
            allow 127.0.0.1;
            deny all;
        }
    }
}

rtmp {
    server {
        listen {$rtmpPort};
        chunk_size 4096;
        max_streams 128;
        ping 30s;
        ping_timeout 15s;
        drop_idle_publisher 30s;

        application live {
            live on;
            record off;
            gop_cache on;
            allow publish 127.0.0.1;
            allow publish ::1;
            deny publish all;
            allow play all;

NGINX;

            // Add DASH config if enabled
            if ($protocol === 'dash' || $protocol === 'both') {
                $config .= <<<NGINX

            dash on;
            dash_path {$streamsPath}/dash;
            dash_fragment {$dashFragment}s;
            dash_playlist_length {$dashPlaylistLength}s;
            dash_nested {$dashNested};
            dash_cleanup {$dashCleanup};
NGINX;
            }

            // Add HLS config if enabled
            if ($protocol === 'hls' || $protocol === 'both') {
                $config .= <<<NGINX

            hls on;
            hls_path {$streamsPath}/hls;
            hls_fragment {$hlsFragment}s;
            hls_playlist_length {$hlsPlaylistLength}s;
            hls_nested {$hlsNested};
            hls_cleanup {$hlsCleanup};
            hls_continuous on;
NGINX;
            }

            $config .= <<<NGINX

        }
    }
}
NGINX;

            // Write config if requested
            $writeConfig = $input['write'] ?? false;
            $configPath = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $postData = json_decode(file_get_contents('php://input'), true);
                if ($postData['write'] ?? false) {
                    // Backup existing config
                    if (file_exists($configPath)) {
                        @copy($configPath, $configPath . '.bak.' . time());
                    }
                    // Write new config
                    if (file_put_contents($configPath, $config) !== false) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Nginx configuration generated and saved',
                            'data' => [
                                'config_path' => $configPath,
                                'config' => $config
                            ]
                        ]);
                    } else {
                        throw new Exception('Failed to write config file: ' . $configPath);
                    }
                    break;
                }
            }

            // Just return the generated config
            echo json_encode([
                'success' => true,
                'message' => 'Nginx configuration generated (not saved)',
                'data' => [
                    'config_path' => $configPath,
                    'config' => $config
                ]
            ]);
            break;

        // ============================================================
        // Unified Service Management
        // ============================================================

        case 'all_services_status':
            // Get status of all managed services
            // Organized into: platform services (streaming) and system services
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();
            $phpFpmInfo = PathDetectionService::detectPhpFpm();

            // ========================================
            // PLATFORM SERVICES (Streaming Protocol)
            // ========================================

            // Nginx Streaming Status
            $nginxStreamingPid = null;
            $nginxStreamingRunning = false;
            $pidFile = '/tmp/fos-streaming-nginx.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && is_numeric($pid)) {
                    exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                    $nginxStreamingRunning = ($returnCode === 0);
                    $nginxStreamingPid = $nginxStreamingRunning ? (int)$pid : null;
                }
            }

            // PHP-FPM Streaming Status (project-local socket)
            $phpFpmStreamingPid = null;
            $phpFpmStreamingRunning = false;
            $phpFpmStreamingPidFile = PathDetectionService::getPhpFpmStreamingPidFile();
            $phpFpmStreamingSocket = PathDetectionService::getPhpFpmStreamingSocket();
            if (file_exists($phpFpmStreamingPidFile)) {
                $pid = trim(file_get_contents($phpFpmStreamingPidFile));
                if ($pid && is_numeric($pid)) {
                    exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                    $phpFpmStreamingRunning = ($returnCode === 0);
                    $phpFpmStreamingPid = $phpFpmStreamingRunning ? (int)$pid : null;
                }
            }

            // ========================================
            // SYSTEM SERVICES
            // ========================================

            // Nginx Admin Panel Status (system nginx - serves admin UI)
            $nginxAdminPid = null;
            $nginxAdminRunning = false;
            $nginxPidFiles = ['/var/run/nginx.pid', '/run/nginx.pid', '/usr/local/nginx/logs/nginx.pid'];
            foreach ($nginxPidFiles as $npf) {
                if (file_exists($npf)) {
                    $pid = trim(file_get_contents($npf));
                    if ($pid && is_numeric($pid)) {
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            $nginxAdminRunning = true;
                            $nginxAdminPid = (int)$pid;
                            break;
                        }
                    }
                }
            }

            // PHP-FPM Admin Status (system PHP-FPM - serves admin UI)
            $phpFpmAdminPid = null;
            $phpFpmAdminRunning = false;
            $phpFpmAdminPidFiles = ['/run/php-fpm/php-fpm.pid', '/var/run/php-fpm.pid', '/run/php/php-fpm.pid', '/var/run/php/php-fpm.pid'];
            // Also check versioned paths
            foreach (['8.4', '8.3', '8.2', '8.1'] as $phpVersion) {
                $phpFpmAdminPidFiles[] = "/run/php/php{$phpVersion}-fpm.pid";
                $phpFpmAdminPidFiles[] = "/var/run/php{$phpVersion}-fpm.pid";
            }
            foreach ($phpFpmAdminPidFiles as $pf) {
                if (file_exists($pf)) {
                    $pid = trim(file_get_contents($pf));
                    if ($pid && is_numeric($pid)) {
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            $phpFpmAdminRunning = true;
                            $phpFpmAdminPid = (int)$pid;
                            break;
                        }
                    }
                }
            }

            // MariaDB/MySQL Status
            $mariadbPid = null;
            $mariadbRunning = false;
            $mariadbPidFiles = ['/var/run/mysqld/mysqld.pid', '/run/mysqld/mysqld.pid', '/var/run/mariadb/mariadb.pid', '/run/mariadb/mariadb.pid'];
            foreach ($mariadbPidFiles as $mf) {
                if (file_exists($mf)) {
                    $pid = trim(file_get_contents($mf));
                    if ($pid && is_numeric($pid)) {
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            $mariadbRunning = true;
                            $mariadbPid = (int)$pid;
                            break;
                        }
                    }
                }
            }
            // Also try pgrep as fallback
            if (!$mariadbRunning) {
                exec("pgrep -x mysqld 2>/dev/null || pgrep -x mariadbd 2>/dev/null", $pgrepOutput, $pgrepCode);
                if ($pgrepCode === 0 && !empty($pgrepOutput)) {
                    $mariadbRunning = true;
                    $mariadbPid = (int)trim($pgrepOutput[0]);
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    // Platform Services (Streaming Protocol)
                    'platform' => [
                        'nginxStreaming' => [
                            'name' => 'Nginx Streaming Service',
                            'running' => $nginxStreamingRunning,
                            'pid' => $nginxStreamingPid,
                            'binary' => $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'],
                            'config' => $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'],
                            'port' => (int)($setting->streaming_port ?? 8001),
                        ],
                        'phpFpmStreaming' => [
                            'name' => 'PHP-FPM Streaming Service',
                            'running' => $phpFpmStreamingRunning,
                            'pid' => $phpFpmStreamingPid,
                            'socket' => $phpFpmStreamingSocket,
                            'socket_exists' => file_exists($phpFpmStreamingSocket),
                            'config' => $phpFpmInfo['config_path'],
                        ],
                    ],
                    // System Services
                    'system' => [
                        'nginxAdmin' => [
                            'name' => 'Nginx Admin Panel',
                            'running' => $nginxAdminRunning,
                            'pid' => $nginxAdminPid,
                            'port' => (int)($setting->webport ?? 7777),
                            'manageable' => false, // Usually managed by systemd
                        ],
                        'phpFpmAdmin' => [
                            'name' => 'PHP-FPM Admin',
                            'running' => $phpFpmAdminRunning,
                            'pid' => $phpFpmAdminPid,
                            'manageable' => false, // Usually managed by systemd
                        ],
                        'mariadb' => [
                            'name' => 'MariaDB Database',
                            'running' => $mariadbRunning,
                            'pid' => $mariadbPid,
                            'manageable' => false, // Usually managed by systemd
                        ],
                    ],
                    // Legacy format for backwards compatibility
                    'nginxStreaming' => [
                        'running' => $nginxStreamingRunning,
                        'pid' => $nginxStreamingPid,
                        'binary' => $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'],
                        'config' => $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'],
                        'port' => (int)($setting->streaming_port ?? 8001),
                    ],
                    'phpFpmStreaming' => [
                        'running' => $phpFpmStreamingRunning,
                        'pid' => $phpFpmStreamingPid,
                        'socket' => $phpFpmStreamingSocket,
                        'socket_exists' => file_exists($phpFpmStreamingSocket),
                    ],
                    'nginxAdmin' => [
                        'running' => $nginxAdminRunning,
                        'pid' => $nginxAdminPid,
                        'port' => (int)($setting->webport ?? 7777),
                    ]
                ]
            ]);
            break;

        case 'service_status':
            // Get status of a specific service
            $serviceName = $_GET['service'] ?? '';
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();

            switch ($serviceName) {
                case 'nginx_streaming':
                    $pidFile = '/tmp/fos-streaming-nginx.pid';
                    $isRunning = false;
                    $pid = null;
                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        if ($pid && is_numeric($pid)) {
                            exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                            $isRunning = ($returnCode === 0);
                        }
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'service' => 'nginx_streaming',
                            'running' => $isRunning,
                            'pid' => $isRunning ? (int)$pid : null,
                            'binary' => $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'],
                        ]
                    ]);
                    break;

                case 'php_fpm_streaming':
                    $pidFile = PathDetectionService::getPhpFpmStreamingPidFile();
                    $socket = PathDetectionService::getPhpFpmStreamingSocket();
                    $isRunning = false;
                    $pid = null;
                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        if ($pid && is_numeric($pid)) {
                            exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                            $isRunning = ($returnCode === 0);
                        }
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'service' => 'php_fpm_streaming',
                            'running' => $isRunning,
                            'pid' => $isRunning ? (int)$pid : null,
                            'socket' => $socket,
                            'socket_exists' => file_exists($socket),
                        ]
                    ]);
                    break;

                case 'nginx_admin':
                    $isRunning = false;
                    $pid = null;
                    $nginxPidFiles = ['/var/run/nginx.pid', '/run/nginx.pid', '/usr/local/nginx/logs/nginx.pid'];
                    foreach ($nginxPidFiles as $npf) {
                        if (file_exists($npf)) {
                            $pid = trim(file_get_contents($npf));
                            if ($pid && is_numeric($pid)) {
                                exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                                if ($returnCode === 0) {
                                    $isRunning = true;
                                    break;
                                }
                            }
                        }
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'service' => 'nginx_admin',
                            'running' => $isRunning,
                            'pid' => $isRunning ? (int)$pid : null,
                        ]
                    ]);
                    break;

                default:
                    throw new Exception('Unknown service: ' . $serviceName);
            }
            break;

        case 'start_service':
            $serviceName = $_GET['service'] ?? '';
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();

            switch ($serviceName) {
                case 'nginx_streaming':
                    // Reuse existing start_nginx_streaming logic
                    $nginxBinary = $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'];
                    $nginxConfig = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];

                    $pidFile = '/tmp/fos-streaming-nginx.pid';
                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            throw new Exception('Nginx streaming server is already running (PID: ' . $pid . ')');
                        }
                    }

                    $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
                    @mkdir($streamsPath . '/dash', 0755, true);
                    @mkdir($streamsPath . '/hls', 0755, true);

                    $output = [];
                    exec("{$nginxBinary} -c {$nginxConfig} 2>&1", $output, $returnCode);

                    if ($returnCode !== 0) {
                        throw new Exception('Failed to start nginx: ' . implode("\n", $output));
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Nginx streaming server started successfully'
                    ]);
                    break;

                case 'php_fpm_streaming':
                    $phpFpmInfo = PathDetectionService::detectPhpFpm();
                    $pidFile = PathDetectionService::getPhpFpmStreamingPidFile();

                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            throw new Exception('PHP-FPM streaming is already running (PID: ' . $pid . ')');
                        }
                    }

                    // Find PHP-FPM binary
                    $phpFpmBinary = $phpFpmInfo['binary'];
                    if (!$phpFpmBinary || !file_exists($phpFpmBinary)) {
                        // Try to find it
                        $possiblePaths = [
                            '/usr/sbin/php-fpm',
                            '/usr/sbin/php-fpm8.4',
                            '/usr/sbin/php-fpm8.3',
                            '/usr/sbin/php-fpm8.2',
                            PathDetectionService::detectProjectRoot() . '/fospackv69/fos/php/sbin/php-fpm',
                        ];
                        foreach ($possiblePaths as $path) {
                            if (file_exists($path) && is_executable($path)) {
                                $phpFpmBinary = $path;
                                break;
                            }
                        }
                    }

                    if (!$phpFpmBinary || !file_exists($phpFpmBinary)) {
                        throw new Exception('PHP-FPM binary not found. Please install php-fpm.');
                    }

                    // Use the streaming-specific config
                    $phpFpmConfig = $phpFpmInfo['config_path'];

                    // Generate PHP-FPM streaming config if it doesn't exist
                    if (!file_exists($phpFpmConfig)) {
                        $configGenerated = generatePhpFpmStreamingConfig($phpFpmInfo);
                        if (!$configGenerated) {
                            throw new Exception('Failed to generate PHP-FPM streaming configuration at: ' . $phpFpmConfig);
                        }
                    }

                    // Ensure log directory exists
                    $logsPath = PathDetectionService::detectProjectRoot() . '/fospackv69/fos/logs';
                    if (!file_exists($logsPath)) {
                        @mkdir($logsPath, 0755, true);
                    }

                    $output = [];
                    exec("{$phpFpmBinary} -y {$phpFpmConfig} 2>&1", $output, $returnCode);

                    if ($returnCode !== 0) {
                        throw new Exception('Failed to start PHP-FPM: ' . implode("\n", $output));
                    }

                    // Wait a moment for socket to be created
                    sleep(1);
                    $socket = PathDetectionService::getPhpFpmStreamingSocket();

                    echo json_encode([
                        'success' => true,
                        'message' => 'PHP-FPM streaming service started successfully',
                        'data' => [
                            'socket' => $socket,
                            'socket_exists' => file_exists($socket),
                            'config' => $phpFpmConfig,
                        ]
                    ]);
                    break;

                case 'nginx_admin':
                    // Start system nginx (usually requires sudo)
                    $output = [];
                    exec("sudo systemctl start nginx 2>&1 || sudo service nginx start 2>&1 || nginx 2>&1", $output, $returnCode);

                    if ($returnCode !== 0) {
                        throw new Exception('Failed to start Nginx admin panel. May require sudo privileges. Output: ' . implode("\n", $output));
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Nginx admin panel started successfully'
                    ]);
                    break;

                default:
                    throw new Exception('Unknown service: ' . $serviceName);
            }
            break;

        case 'stop_service':
            $serviceName = $_GET['service'] ?? '';

            switch ($serviceName) {
                case 'nginx_streaming':
                    $pidFile = '/tmp/fos-streaming-nginx.pid';
                    if (!file_exists($pidFile)) {
                        throw new Exception('Nginx streaming server is not running (no PID file)');
                    }

                    $pid = trim(file_get_contents($pidFile));
                    if (!$pid || !is_numeric($pid)) {
                        throw new Exception('Invalid PID in file');
                    }

                    exec("kill -QUIT {$pid} 2>&1", $output, $returnCode);
                    sleep(1);
                    exec("ps -p {$pid} > /dev/null 2>&1", $checkOutput, $checkCode);

                    if ($checkCode === 0) {
                        exec("kill -9 {$pid} 2>&1");
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Nginx streaming server stopped'
                    ]);
                    break;

                case 'php_fpm_streaming':
                    $pidFile = PathDetectionService::getPhpFpmStreamingPidFile();
                    $socket = PathDetectionService::getPhpFpmStreamingSocket();

                    if (!file_exists($pidFile)) {
                        throw new Exception('PHP-FPM streaming is not running (no PID file)');
                    }

                    $pid = trim(file_get_contents($pidFile));
                    if (!$pid || !is_numeric($pid)) {
                        throw new Exception('Invalid PID in file');
                    }

                    exec("kill -QUIT {$pid} 2>&1", $output, $returnCode);
                    sleep(1);
                    exec("ps -p {$pid} > /dev/null 2>&1", $checkOutput, $checkCode);

                    if ($checkCode === 0) {
                        exec("kill -9 {$pid} 2>&1");
                    }

                    // Clean up socket and PID file
                    @unlink($socket);
                    @unlink($pidFile);

                    echo json_encode([
                        'success' => true,
                        'message' => 'PHP-FPM streaming service stopped'
                    ]);
                    break;

                case 'nginx_admin':
                    $output = [];
                    exec("sudo systemctl stop nginx 2>&1 || sudo service nginx stop 2>&1 || nginx -s quit 2>&1", $output, $returnCode);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Nginx admin panel stopped'
                    ]);
                    break;

                default:
                    throw new Exception('Unknown service: ' . $serviceName);
            }
            break;

        case 'restart_service':
            $serviceName = $_GET['service'] ?? '';
            $setting = Setting::first();
            $detected = PathDetectionService::detectAll();

            switch ($serviceName) {
                case 'nginx_streaming':
                    $nginxBinary = $setting->nginx_binary_path ?: $detected['paths']['nginx_binary_path'];
                    $nginxConfig = $setting->nginx_config_path ?: $detected['paths']['nginx_config_path'];
                    $pidFile = '/tmp/fos-streaming-nginx.pid';

                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                        if ($returnCode === 0) {
                            exec("{$nginxBinary} -c {$nginxConfig} -s reload 2>&1", $output, $returnCode);
                            if ($returnCode === 0) {
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Nginx streaming server reloaded gracefully'
                                ]);
                                break;
                            }
                        }
                    }

                    // Start fresh if not running or reload failed
                    $streamsPath = $setting->streams_path ?: $detected['paths']['streams_path'];
                    @mkdir($streamsPath . '/dash', 0755, true);
                    @mkdir($streamsPath . '/hls', 0755, true);

                    exec("{$nginxBinary} -c {$nginxConfig} 2>&1", $output, $returnCode);

                    echo json_encode([
                        'success' => $returnCode === 0,
                        'message' => $returnCode === 0 ? 'Nginx streaming server started' : 'Failed to start nginx'
                    ]);
                    break;

                case 'php_fpm_streaming':
                    $phpFpmInfo = PathDetectionService::detectPhpFpm();
                    $pidFile = PathDetectionService::getPhpFpmStreamingPidFile();

                    // Try graceful reload first if running
                    if (file_exists($pidFile)) {
                        $pid = trim(file_get_contents($pidFile));
                        if ($pid && is_numeric($pid)) {
                            exec("ps -p {$pid} > /dev/null 2>&1", $checkOutput, $checkCode);
                            if ($checkCode === 0) {
                                exec("kill -USR2 {$pid} 2>&1"); // Graceful reload for PHP-FPM
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'PHP-FPM streaming service reloaded gracefully'
                                ]);
                                break;
                            }
                        }
                    }

                    // Not running, start fresh
                    $phpFpmBinary = $phpFpmInfo['binary'];
                    if (!$phpFpmBinary || !file_exists($phpFpmBinary)) {
                        $possiblePaths = [
                            '/usr/sbin/php-fpm',
                            '/usr/sbin/php-fpm8.4',
                            '/usr/sbin/php-fpm8.3',
                            '/usr/sbin/php-fpm8.2',
                        ];
                        foreach ($possiblePaths as $path) {
                            if (file_exists($path) && is_executable($path)) {
                                $phpFpmBinary = $path;
                                break;
                            }
                        }
                    }

                    $phpFpmConfig = $phpFpmInfo['config_path'];

                    // Generate config if needed
                    if (!file_exists($phpFpmConfig)) {
                        generatePhpFpmStreamingConfig($phpFpmInfo);
                    }

                    $output = [];
                    exec("{$phpFpmBinary} -y {$phpFpmConfig} 2>&1", $output, $returnCode);

                    echo json_encode([
                        'success' => $returnCode === 0,
                        'message' => $returnCode === 0 ? 'PHP-FPM streaming service started' : 'Failed to start PHP-FPM'
                    ]);
                    break;

                case 'nginx_admin':
                    $output = [];
                    exec("sudo systemctl reload nginx 2>&1 || sudo service nginx reload 2>&1 || nginx -s reload 2>&1", $output, $returnCode);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Nginx admin panel reloaded'
                    ]);
                    break;

                default:
                    throw new Exception('Unknown service: ' . $serviceName);
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
