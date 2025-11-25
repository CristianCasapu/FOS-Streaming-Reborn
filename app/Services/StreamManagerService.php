<?php

namespace App\Services;

use App\Services\PathDetectionService;

/**
 * StreamManagerService
 *
 * Handles execution of queued stream commands (start, stop, restart)
 * Used by the stream-manager PM2 worker
 *
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */
class StreamManagerService
{
    private $logger;

    public function __construct()
    {
        $this->logger = new \App\Services\LoggerService('stream-manager');

        // Ensure HLS folder exists on startup
        $this->ensureHlsFolderExists();
    }

    /**
     * Ensure the streaming folders exist
     * Creates DASH and HLS directories if they don't exist with proper permissions
     *
     * @return bool True if folders exist or were created successfully
     */
    private function ensureHlsFolderExists(): bool
    {
        $setting = \Setting::first();
        $streamsPath = $setting->streams_path ?: PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';

        // Create both DASH and HLS folders
        $folders = [
            $streamsPath . '/dash',
            $streamsPath . '/hls'
        ];

        $created = false;
        foreach ($folders as $path) {
            if (!file_exists($path)) {
                $this->logger->info("Creating streaming folder: {$path}");
                if (@mkdir($path, 0755, true)) {
                    @chmod($path, 0755);
                    $this->logger->info("Streaming folder created successfully: {$path}");
                    $created = true;
                } else {
                    $this->logger->warning("Failed to create streaming folder: {$path}");
                }
            } else {
                // Folder exists, ensure permissions
                if (!is_writable($path)) {
                    @chmod($path, 0755);
                    $this->logger->info("Fixed permissions for streaming folder: {$path}");
                }
                $created = true;
            }
        }

        return $created;
    }

    /**
     * Process all queued stream commands
     *
     * @return array Statistics
     */
    public function processQueuedCommands(): array
    {
        // Re-check HLS folder on each run
        $this->ensureHlsFolderExists();

        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'commands' => []
        ];

        // Get all streams with scheduled commands
        $streams = \Stream::whereIn('scheduled_command', ['start', 'stop', 'restart'])
            ->orderBy('command_queued_at')
            ->get();

        foreach ($streams as $stream) {
            $command = $stream->scheduled_command;
            $this->logger->info("Processing command '{$command}' for stream ID {$stream->id}");

            // Skip start/restart commands for disabled streams
            if (in_array($command, ['start', 'restart']) && $stream->isDisabled()) {
                $this->logger->warning("Skipping {$command} command for disabled stream {$stream->id}");
                $stream->clearScheduledCommand('skipped: stream is disabled');
                $stats['processed']++;
                $stats['failed']++;
                $stats['commands'][] = [
                    'stream_id' => $stream->id,
                    'command' => $command,
                    'success' => false,
                    'message' => 'Stream is disabled'
                ];
                continue;
            }

            $result = $this->executeCommand($stream, $command);

            $stats['processed']++;
            if ($result['success']) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }

            $stats['commands'][] = [
                'stream_id' => $stream->id,
                'command' => $command,
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }

        return $stats;
    }

    /**
     * Execute a command for a stream
     *
     * @param \Stream $stream Stream object
     * @param string $command Command to execute
     * @return array Result
     */
    private function executeCommand(\Stream $stream, string $command): array
    {
        try {
            switch ($command) {
                case 'start':
                    return $this->startStream($stream);

                case 'stop':
                    return $this->stopStream($stream);

                case 'restart':
                    // Stop then start
                    $stopResult = $this->stopStream($stream);
                    if (!$stopResult['success']) {
                        return $stopResult;
                    }
                    sleep(2); // Wait for cleanup
                    return $this->startStream($stream);

                default:
                    return [
                        'success' => false,
                        'message' => 'Invalid command'
                    ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Command execution failed for stream {$stream->id}: " . $e->getMessage());

            $stream->clearScheduledCommand('error: ' . $e->getMessage());
            $stream->updateState('error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Start a stream
     *
     * @param \Stream $stream Stream object
     * @return array Result
     */
    private function startStream(\Stream $stream): array
    {
        // Double-check: disabled streams cannot be started
        if ($stream->isDisabled()) {
            $stream->clearScheduledCommand('error: stream is disabled');
            return [
                'success' => false,
                'message' => 'Cannot start disabled stream'
            ];
        }

        // Update state to starting
        $stream->updateState('starting');

        // Get FFmpeg command from transcode settings
        $ffmpegCommand = getTranscode($stream->id);

        if (empty($ffmpegCommand)) {
            $stream->clearScheduledCommand('error: Failed to build FFmpeg command');
            $stream->updateState('error');

            return [
                'success' => false,
                'message' => 'Failed to build FFmpeg command'
            ];
        }

        // Execute FFmpeg and capture PID
        $this->logger->info("Executing FFmpeg for stream {$stream->id}");
        $this->logger->debug("FFmpeg command: {$ffmpegCommand}");

        $output = [];
        exec($ffmpegCommand, $output, $exitCode);

        // PID should be in the output (from "& echo $!")
        $pid = !empty($output) ? trim(end($output)) : null;

        if (!$pid || !is_numeric($pid)) {
            $stream->clearScheduledCommand('error: Failed to start FFmpeg');
            $stream->updateState('error');

            return [
                'success' => false,
                'message' => 'Failed to capture FFmpeg PID'
            ];
        }

        // Verify PID is running
        exec("ps -p {$pid} > /dev/null 2>&1", $checkOutput, $checkExitCode);

        if ($checkExitCode !== 0) {
            $stream->clearScheduledCommand('error: FFmpeg process died immediately');
            $stream->updateState('error');
            $stream->ffmpeg_exit_code = $checkExitCode;

            return [
                'success' => false,
                'message' => 'FFmpeg process died immediately after start'
            ];
        }

        // Update stream with PID and state
        $stream->pid = (int)$pid;
        $stream->last_ffmpeg_command = $ffmpegCommand;
        $stream->stream_started_at = date('Y-m-d H:i:s');
        $stream->updateState('running');
        $stream->clearScheduledCommand('success');

        // Reset restart attempts on successful start
        $stream->resetRestartAttempts();

        $this->logger->info("Stream {$stream->id} started successfully with PID {$pid}");

        return [
            'success' => true,
            'message' => "Stream started with PID {$pid}"
        ];
    }

    /**
     * Stop a stream
     *
     * @param \Stream $stream Stream object
     * @return array Result
     */
    private function stopStream(\Stream $stream): array
    {
        // Update state to stopping
        $stream->updateState('stopping');

        $pid = $stream->pid;

        if (!$pid) {
            $this->logger->warning("Stream {$stream->id} has no PID to stop");
            $stream->updateState('stopped');
            $stream->clearScheduledCommand('success');

            return [
                'success' => true,
                'message' => 'Stream had no PID (already stopped)'
            ];
        }

        // Check if PID exists
        exec("ps -p {$pid} > /dev/null 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            // PID doesn't exist, stream already stopped
            $this->logger->warning("Stream {$stream->id} PID {$pid} not found");
            $stream->pid = null;
            $stream->updateState('stopped');
            $stream->clearScheduledCommand('success');

            return [
                'success' => true,
                'message' => 'PID not found (already stopped)'
            ];
        }

        // Kill the process
        $this->logger->info("Killing stream {$stream->id} PID {$pid}");
        exec("kill -9 {$pid}");

        // Wait for process to die
        sleep(1);

        // Clean up streaming segment files (DASH and HLS)
        $setting = \Setting::first();
        $streamsPath = $setting->streams_path ?: PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';

        // Clean DASH segments (nginx-rtmp creates nested directories)
        $dashPath = "{$streamsPath}/dash/{$stream->id}";
        if (file_exists($dashPath)) {
            $this->logger->info("Cleaning up DASH files: {$dashPath}");
            exec("/bin/rm -rf {$dashPath}");
        }

        // Clean HLS segments (nginx-rtmp creates nested directories)
        $hlsPath = "{$streamsPath}/hls/{$stream->id}";
        if (file_exists($hlsPath)) {
            $this->logger->info("Cleaning up HLS files: {$hlsPath}");
            exec("/bin/rm -rf {$hlsPath}");
        }

        // Clean legacy flat HLS files (for backwards compatibility)
        exec("/bin/rm -f {$streamsPath}/hls/{$stream->id}_*.m3u8 2>/dev/null");
        exec("/bin/rm -f {$streamsPath}/hls/{$stream->id}_*.ts 2>/dev/null");

        // Update stream
        $stream->pid = null;
        $stream->updateState('stopped');
        $stream->clearScheduledCommand('success');

        $this->logger->info("Stream {$stream->id} stopped successfully");

        return [
            'success' => true,
            'message' => 'Stream stopped successfully'
        ];
    }

    /**
     * Get pending commands count
     *
     * @return int
     */
    public function getPendingCommandsCount(): int
    {
        return \Stream::whereIn('scheduled_command', ['start', 'stop', 'restart'])->count();
    }
}
