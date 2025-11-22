#!/usr/bin/env php
<?php
/**
 * Process FFprobe Analysis Job
 *
 * Called by the ffprobe-worker to analyze streams.
 *
 * Usage: php process-ffprobe-job.php <job_id>
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/JobQueueService.php';
require_once __DIR__ . '/../app/Services/FFprobeService.php';

if ($argc < 2) {
    echo "Usage: php process-ffprobe-job.php <job_id>\n";
    exit(1);
}

$jobId = $argv[1];

try {
    $queueService = new \App\Services\JobQueueService();
    $ffprobeService = new \App\Services\FFprobeService();

    // Get job details
    $job = $queueService->getJobStatus('ffprobe-analysis', $jobId);

    if (!$job) {
        throw new Exception("Job not found: {$jobId}");
    }

    echo "[INFO] Processing FFprobe job {$jobId}\n";

    $data = $job['data'];
    $streamIds = $data['stream_ids'] ?? [];

    if (empty($streamIds)) {
        throw new Exception("No stream IDs in job data");
    }

    $analyzedCount = 0;
    $failedCount = 0;
    $results = [];

    // Process each stream
    foreach ($streamIds as $streamId) {
        try {
            $stream = Stream::find($streamId);

            if (!$stream) {
                echo "[WARN] Stream {$streamId} not found, skipping\n";
                continue;
            }

            echo "[INFO] Analyzing stream {$streamId}: {$stream->name}\n";

            // Mark as analyzing
            $stream->analysis_status = 'analyzing';
            $stream->save();

            // Determine if it's a live stream
            $isLive = $stream->stream_type !== 'vod';

            // Analyze with FFprobe
            $startTime = microtime(true);
            $analysis = $ffprobeService->analyzeStream($stream->streamurl, $isLive);
            $duration = round(microtime(true) - $startTime, 2);

            // Update stream with results
            $stream->updateFromAnalysis($analysis);

            if ($stream->analysis_status === 'completed') {
                $analyzedCount++;
                $tech = $stream->getTechnicalSummary();
                echo "[INFO] ✓ Success ({$duration}s) - {$tech['quality']} | {$tech['video_codec']} | {$tech['audio_codec']} | Health: {$tech['health_score']}/100\n";

                $results[] = [
                    'stream_id' => $streamId,
                    'status' => 'success',
                    'duration' => $duration,
                    'health_score' => $tech['health_score']
                ];
            } else {
                $failedCount++;
                $error = $stream->analysis_error ?? 'Unknown error';
                echo "[ERROR] ✗ Failed ({$duration}s) - {$error}\n";

                $results[] = [
                    'stream_id' => $streamId,
                    'status' => 'failed',
                    'duration' => $duration,
                    'error' => $error
                ];
            }

        } catch (Exception $e) {
            $failedCount++;
            echo "[ERROR] Exception analyzing stream {$streamId}: {$e->getMessage()}\n";

            // Mark stream as failed
            try {
                $stream = Stream::find($streamId);
                if ($stream) {
                    $stream->analysis_status = 'failed';
                    $stream->analysis_error = $e->getMessage();
                    $stream->save();
                }
            } catch (Exception $saveEx) {
                echo "[ERROR] Failed to save error status: {$saveEx->getMessage()}\n";
            }

            $results[] = [
                'stream_id' => $streamId,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        // Small delay between streams to avoid overwhelming the system
        if ($streamId !== end($streamIds)) {
            usleep(500000); // 0.5 second delay
        }
    }

    // Mark job as complete
    $result = [
        'analyzed' => $analyzedCount,
        'failed' => $failedCount,
        'total' => count($streamIds),
        'results' => $results
    ];

    $queueService->complete('ffprobe-analysis', $jobId, $result);

    echo "[INFO] FFprobe job completed: {$analyzedCount} analyzed, {$failedCount} failed\n";
    exit(0);

} catch (Exception $e) {
    echo "[ERROR] Job failed: {$e->getMessage()}\n";
    echo "[ERROR] Stack trace: {$e->getTraceAsString()}\n";

    // Mark job as failed
    try {
        $queueService->fail('ffprobe-analysis', $jobId, $e->getMessage());
    } catch (Exception $failEx) {
        echo "[ERROR] Failed to mark job as failed: {$failEx->getMessage()}\n";
    }

    exit(1);
}
