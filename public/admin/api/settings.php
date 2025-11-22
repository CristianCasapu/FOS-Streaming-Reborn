<?php
/**
 * Settings API Endpoint
 * Manages system configuration and streaming server settings
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/SystemCommands.php';

use Illuminate\Support\Facades\Crypt;
use App\SystemCommands;

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

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
