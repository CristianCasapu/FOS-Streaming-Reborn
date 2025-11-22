<?php

namespace App\Services;

use Exception;

/**
 * Simple File-Based Job Queue System
 *
 * Uses JSON files to queue jobs for background workers.
 * Jobs are stored in storage/jobs/ directory.
 */
class JobQueueService
{
    private string $queueDir;

    public function __construct()
    {
        $this->queueDir = base_path('storage/jobs');

        // Ensure queue directory exists
        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0755, true);
        }

        // Create subdirectories for each queue
        foreach (['stream-import', 'ffprobe-analysis'] as $queue) {
            $dir = "{$this->queueDir}/{$queue}";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Add a job to the queue
     *
     * @param string $queue Queue name (stream-import, ffprobe-analysis)
     * @param array $data Job data
     * @return string Job ID
     * @throws Exception
     */
    public function push(string $queue, array $data): string
    {
        $jobId = uniqid('job_', true);
        $timestamp = time();

        $job = [
            'id' => $jobId,
            'queue' => $queue,
            'data' => $data,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', $timestamp),
            'updated_at' => date('Y-m-d H:i:s', $timestamp),
            'attempts' => 0,
            'max_attempts' => 3,
            'error' => null
        ];

        $filePath = "{$this->queueDir}/{$queue}/{$jobId}.json";

        if (file_put_contents($filePath, json_encode($job, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to write job file: {$filePath}");
        }

        return $jobId;
    }

    /**
     * Get next pending job from queue
     *
     * @param string $queue Queue name
     * @return array|null Job data or null if no jobs
     */
    public function pop(string $queue): ?array
    {
        $queuePath = "{$this->queueDir}/{$queue}";

        if (!is_dir($queuePath)) {
            return null;
        }

        $files = glob("{$queuePath}/job_*.json");

        if (empty($files)) {
            return null;
        }

        // Sort by creation time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        foreach ($files as $file) {
            $job = json_decode(file_get_contents($file), true);

            if ($job && $job['status'] === 'pending') {
                // Mark as processing
                $job['status'] = 'processing';
                $job['updated_at'] = date('Y-m-d H:i:s');
                $job['attempts']++;

                file_put_contents($file, json_encode($job, JSON_PRETTY_PRINT));

                return $job;
            }
        }

        return null;
    }

    /**
     * Mark job as completed
     *
     * @param string $queue Queue name
     * @param string $jobId Job ID
     * @param array|null $result Optional result data
     * @return bool
     */
    public function complete(string $queue, string $jobId, ?array $result = null): bool
    {
        $filePath = "{$this->queueDir}/{$queue}/{$jobId}.json";

        if (!file_exists($filePath)) {
            return false;
        }

        $job = json_decode(file_get_contents($filePath), true);
        $job['status'] = 'completed';
        $job['updated_at'] = date('Y-m-d H:i:s');
        $job['completed_at'] = date('Y-m-d H:i:s');

        if ($result) {
            $job['result'] = $result;
        }

        file_put_contents($filePath, json_encode($job, JSON_PRETTY_PRINT));

        // Move to completed directory
        $completedDir = "{$this->queueDir}/{$queue}/completed";
        if (!is_dir($completedDir)) {
            mkdir($completedDir, 0755, true);
        }

        rename($filePath, "{$completedDir}/{$jobId}.json");

        return true;
    }

    /**
     * Mark job as failed
     *
     * @param string $queue Queue name
     * @param string $jobId Job ID
     * @param string $error Error message
     * @return bool
     */
    public function fail(string $queue, string $jobId, string $error): bool
    {
        $filePath = "{$this->queueDir}/{$queue}/{$jobId}.json";

        if (!file_exists($filePath)) {
            return false;
        }

        $job = json_decode(file_get_contents($filePath), true);

        // Check if max attempts reached
        if ($job['attempts'] >= $job['max_attempts']) {
            $job['status'] = 'failed';
            $job['updated_at'] = date('Y-m-d H:i:s');
            $job['failed_at'] = date('Y-m-d H:i:s');
            $job['error'] = $error;

            file_put_contents($filePath, json_encode($job, JSON_PRETTY_PRINT));

            // Move to failed directory
            $failedDir = "{$this->queueDir}/{$queue}/failed";
            if (!is_dir($failedDir)) {
                mkdir($failedDir, 0755, true);
            }

            rename($filePath, "{$failedDir}/{$jobId}.json");
        } else {
            // Retry - set back to pending
            $job['status'] = 'pending';
            $job['updated_at'] = date('Y-m-d H:i:s');
            $job['error'] = $error;

            file_put_contents($filePath, json_encode($job, JSON_PRETTY_PRINT));
        }

        return true;
    }

    /**
     * Get job status
     *
     * @param string $queue Queue name
     * @param string $jobId Job ID
     * @return array|null Job data or null if not found
     */
    public function getJobStatus(string $queue, string $jobId): ?array
    {
        // Check in main queue
        $filePath = "{$this->queueDir}/{$queue}/{$jobId}.json";
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }

        // Check in completed
        $filePath = "{$this->queueDir}/{$queue}/completed/{$jobId}.json";
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }

        // Check in failed
        $filePath = "{$this->queueDir}/{$queue}/failed/{$jobId}.json";
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }

        return null;
    }

    /**
     * Get queue statistics
     *
     * @param string $queue Queue name
     * @return array Statistics
     */
    public function getQueueStats(string $queue): array
    {
        $queuePath = "{$this->queueDir}/{$queue}";

        $pending = count(glob("{$queuePath}/job_*.json")) ?: 0;
        $processing = 0;
        $completed = count(glob("{$queuePath}/completed/job_*.json")) ?: 0;
        $failed = count(glob("{$queuePath}/failed/job_*.json")) ?: 0;

        // Count processing jobs
        $files = glob("{$queuePath}/job_*.json");
        foreach ($files as $file) {
            $job = json_decode(file_get_contents($file), true);
            if ($job && $job['status'] === 'processing') {
                $processing++;
                $pending--;
            }
        }

        return [
            'queue' => $queue,
            'pending' => max(0, $pending),
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total' => $pending + $processing + $completed + $failed
        ];
    }

    /**
     * Clean up old completed/failed jobs
     *
     * @param string $queue Queue name
     * @param int $olderThanDays Remove jobs older than X days
     * @return int Number of jobs removed
     */
    public function cleanup(string $queue, int $olderThanDays = 7): int
    {
        $removed = 0;
        $cutoffTime = time() - ($olderThanDays * 86400);

        foreach (['completed', 'failed'] as $dir) {
            $path = "{$this->queueDir}/{$queue}/{$dir}";
            if (!is_dir($path)) {
                continue;
            }

            $files = glob("{$path}/job_*.json");
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
