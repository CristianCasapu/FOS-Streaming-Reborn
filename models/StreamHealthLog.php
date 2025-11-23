<?php
/**
 * StreamHealthLog Model
 *
 * Tracks stream health checks, crashes, and recovery actions
 */
class StreamHealthLog extends FosStreaming {

    protected $table = 'stream_health_logs';

    /**
     * Relationship: belongs to Stream
     */
    public function stream()
    {
        return $this->belongsTo(Stream::class, 'stream_id', 'id');
    }

    /**
     * Create a PID check log entry
     *
     * @param int $streamId Stream ID
     * @param int|null $pid Process ID checked
     * @param bool $pidExists Did PID exist?
     * @param string $status Health check result
     * @param string|null $actionTaken Action taken
     * @param string|null $errorMessage Error message if failed
     * @return static|null
     */
    public static function logPidCheck(
        int $streamId,
        ?int $pid,
        bool $pidExists,
        string $status,
        ?string $actionTaken = null,
        ?string $errorMessage = null
    ): ?self
    {
        $log = new self();
        $log->stream_id = $streamId;
        $log->check_type = 'pid_check';
        $log->status = $status;
        $log->pid = $pid;
        $log->pid_exists = $pidExists;
        $log->action_taken = $actionTaken;
        $log->error_message = $errorMessage;
        $log->checked_at = date('Y-m-d H:i:s');

        return $log->save() ? $log : null;
    }

    /**
     * Create a URL check log entry
     *
     * @param int $streamId Stream ID
     * @param string $url URL checked
     * @param int|null $httpStatus HTTP response code
     * @param int|null $responseTime Response time in ms
     * @param string $status Health check result
     * @param string|null $errorMessage Error message if failed
     * @return static|null
     */
    public static function logUrlCheck(
        int $streamId,
        string $url,
        ?int $httpStatus,
        ?int $responseTime,
        string $status,
        ?string $errorMessage = null
    ): ?self
    {
        $log = new self();
        $log->stream_id = $streamId;
        $log->check_type = 'url_check';
        $log->status = $status;
        $log->url_checked = $url;
        $log->http_status = $httpStatus;
        $log->response_time = $responseTime;
        $log->error_message = $errorMessage;
        $log->checked_at = date('Y-m-d H:i:s');

        return $log->save() ? $log : null;
    }

    /**
     * Create an FFprobe check log entry
     *
     * @param int $streamId Stream ID
     * @param string $url URL checked
     * @param string $status Health check result
     * @param string|null $errorMessage Error message if failed
     * @param array|null $errorDetails Additional error details
     * @return static|null
     */
    public static function logFfprobeCheck(
        int $streamId,
        string $url,
        string $status,
        ?string $errorMessage = null,
        ?array $errorDetails = null
    ): ?self
    {
        $log = new self();
        $log->stream_id = $streamId;
        $log->check_type = 'ffprobe_check';
        $log->status = $status;
        $log->url_checked = $url;
        $log->error_message = $errorMessage;
        $log->error_details = $errorDetails ? json_encode($errorDetails) : null;
        $log->checked_at = date('Y-m-d H:i:s');

        return $log->save() ? $log : null;
    }

    /**
     * Get recent failures for a stream
     *
     * @param int $streamId Stream ID
     * @param int $limit Number of records
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRecentFailures(int $streamId, int $limit = 10)
    {
        return self::where('stream_id', $streamId)
            ->where('status', 'failed')
            ->orderBy('checked_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get crash count for a stream in a time period
     *
     * @param int $streamId Stream ID
     * @param int $hours Number of hours to look back
     * @return int
     */
    public static function getCrashCount(int $streamId, int $hours = 24): int
    {
        return self::where('stream_id', $streamId)
            ->where('check_type', 'pid_check')
            ->where('status', 'failed')
            ->where('checked_at', '>=', date('Y-m-d H:i:s', strtotime("-{$hours} hours")))
            ->count();
    }

    /**
     * Get streams with most crashes
     *
     * @param int $hours Number of hours to look back
     * @param int $limit Number of streams to return
     * @return array
     */
    public static function getTopCrashingStreams(int $hours = 24, int $limit = 10): array
    {
        return \DB::select("
            SELECT stream_id, COUNT(*) as crash_count
            FROM stream_health_logs
            WHERE check_type = 'pid_check'
              AND status = 'failed'
              AND checked_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY stream_id
            ORDER BY crash_count DESC
            LIMIT ?
        ", [$hours, $limit]);
    }
}
