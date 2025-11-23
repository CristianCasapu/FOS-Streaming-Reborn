<?php

namespace App\Services;

/**
 * StreamMonitorService
 *
 * Monitors running stream PIDs and handles crash detection/recovery
 * Used by the stream-monitor PM2 worker
 *
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */
class StreamMonitorService
{
    private $logger;

    public function __construct()
    {
        $this->logger = new \App\Services\LoggerService('stream-monitor');
    }

    /**
     * Monitor all running streams
     *
     * @return array Statistics
     */
    public function monitorStreams(): array
    {
        $stats = [
            'checked' => 0,
            'healthy' => 0,
            'crashed' => 0,
            'restarted' => 0
        ];

        // Get all streams that should be running
        $streams = \Stream::where('state', 'running')
            ->orWhere(function($query) {
                $query->whereNotNull('pid')
                      ->where('pid', '>', 0);
            })
            ->get();

        foreach ($streams as $stream) {
            $stats['checked']++;

            $isAlive = $this->checkStreamHealth($stream);

            if ($isAlive) {
                $stats['healthy']++;

                // Update uptime
                $this->updateUptime($stream);

                // Reset restart attempts if stream has been running long enough
                $uptime = $stream->getCurrentUptimeSeconds();
                if ($uptime > 300 && $stream->restart_attempts > 0) {
                    // Stream stable for 5+ minutes, reset restart counter
                    $stream->resetRestartAttempts();
                    $this->logger->info("Stream {$stream->id} stable, reset restart attempts");
                }

                // Record healthy check
                $stream->recordHealthCheck(true);
            } else {
                // Stream crashed
                $stats['crashed']++;
                $this->handleCrashedStream($stream);

                if ($stream->shouldAutoRestart()) {
                    $stats['restarted']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Check if stream PID is alive
     *
     * @param \Stream $stream Stream object
     * @return bool True if healthy
     */
    private function checkStreamHealth(\Stream $stream): bool
    {
        if (!$stream->pid) {
            // No PID, stream is not running
            return false;
        }

        // Check if process exists
        exec("ps -p {$stream->pid} > /dev/null 2>&1", $output, $exitCode);
        $pidExists = ($exitCode === 0);

        // Log health check
        \StreamHealthLog::logPidCheck(
            $stream->id,
            $stream->pid,
            $pidExists,
            $pidExists ? 'healthy' : 'failed',
            null,
            $pidExists ? null : 'PID not found in process table'
        );

        return $pidExists;
    }

    /**
     * Handle crashed stream
     *
     * @param \Stream $stream Stream object
     * @return void
     */
    private function handleCrashedStream(\Stream $stream): void
    {
        $this->logger->warning("Stream {$stream->id} crashed (PID {$stream->pid})");

        // Mark as crashed (this also handles auto-restart logic)
        $stream->markAsCrashed();

        // Log the crash
        \StreamHealthLog::logPidCheck(
            $stream->id,
            $stream->pid,
            false,
            'failed',
            $stream->shouldAutoRestart() ? 'restart_queued' : 'marked_as_error',
            'Stream process died unexpectedly'
        );

        if ($stream->shouldAutoRestart()) {
            $this->logger->info(
                "Stream {$stream->id} queued for auto-restart " .
                "(attempt {$stream->restart_attempts}/{$stream->max_restart_attempts})"
            );
        } else {
            $this->logger->error(
                "Stream {$stream->id} reached max restart attempts ({$stream->max_restart_attempts}), " .
                "marked as error"
            );
        }

        // Record health check failure
        $stream->recordHealthCheck(false);
    }

    /**
     * Update stream uptime
     *
     * @param \Stream $stream Stream object
     * @return void
     */
    private function updateUptime(\Stream $stream): void
    {
        $uptime = $stream->getCurrentUptimeSeconds();
        $stream->current_uptime = $uptime;
        $stream->save();
    }

    /**
     * Get monitoring statistics
     *
     * @return array
     */
    public function getMonitoringStats(): array
    {
        return [
            'running_streams' => \Stream::where('state', 'running')->count(),
            'crashed_streams' => \Stream::where('state', 'crashed')->count(),
            'error_streams' => \Stream::where('state', 'error')->count(),
            'streams_with_pid' => \Stream::whereNotNull('pid')->where('pid', '>', 0)->count(),
            'pending_restarts' => \Stream::where('scheduled_command', 'start')
                ->where('state', 'crashed')
                ->count(),
        ];
    }

    /**
     * Clean up orphaned PIDs
     * Remove PIDs from stopped streams that somehow still have a PID
     *
     * @return int Number of cleaned PIDs
     */
    public function cleanupOrphanedPids(): int
    {
        $count = 0;

        $streams = \Stream::where('state', 'stopped')
            ->whereNotNull('pid')
            ->where('pid', '>', 0)
            ->get();

        foreach ($streams as $stream) {
            $this->logger->warning("Found orphaned PID {$stream->pid} on stopped stream {$stream->id}");
            $stream->pid = null;
            $stream->save();
            $count++;
        }

        return $count;
    }

    /**
     * Force check a specific stream
     *
     * @param int $streamId Stream ID
     * @return array Result
     */
    public function checkStream(int $streamId): array
    {
        $stream = \Stream::find($streamId);

        if (!$stream) {
            return [
                'success' => false,
                'message' => 'Stream not found'
            ];
        }

        $isAlive = $this->checkStreamHealth($stream);

        if (!$isAlive && $stream->state === 'running') {
            $this->handleCrashedStream($stream);
        }

        return [
            'success' => true,
            'stream_id' => $streamId,
            'state' => $stream->state,
            'pid' => $stream->pid,
            'is_alive' => $isAlive,
            'uptime' => $stream->getCurrentUptimeSeconds()
        ];
    }
}
