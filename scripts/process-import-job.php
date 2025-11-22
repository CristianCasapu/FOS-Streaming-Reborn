#!/usr/bin/env php
<?php
/**
 * Process Stream Import Job
 *
 * Called by the stream-import-worker to process a single import job.
 *
 * Usage: php process-import-job.php <job_id>
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/JobQueueService.php';

if ($argc < 2) {
    echo "Usage: php process-import-job.php <job_id>\n";
    exit(1);
}

$jobId = $argv[1];

try {
    $queueService = new \App\Services\JobQueueService();

    // Get job details
    $job = $queueService->getJobStatus('stream-import', $jobId);

    if (!$job) {
        throw new Exception("Job not found: {$jobId}");
    }

    echo "[INFO] Processing import job {$jobId}\n";
    echo "[INFO] Data: " . json_encode($job['data']) . "\n";

    $data = $job['data'];
    $streams = $data['streams'] ?? [];
    $categoryMap = [];

    $importedCount = 0;
    $failedCount = 0;
    $streamIds = [];

    // Process each stream
    foreach ($streams as $streamData) {
        try {
            // Handle category creation/lookup
            $catId = 0;

            if (!empty($streamData['category_name'])) {
                $categoryName = $streamData['category_name'];

                // Check cache
                if (isset($categoryMap[$categoryName])) {
                    $catId = $categoryMap[$categoryName];
                } else {
                    // Find or create category
                    $category = Category::where('name', '=', $categoryName)->first();

                    if (!$category) {
                        $category = new Category();
                        $category->name = $categoryName;
                        $category->save();
                        echo "[INFO] Created category: {$categoryName} (ID: {$category->id})\n";
                    }

                    $catId = $category->id;
                    $categoryMap[$categoryName] = $catId;
                }
            }

            // Create stream
            $stream = new Stream();
            $stream->name = $streamData['name'];
            $stream->streamurl = $streamData['url'];
            $stream->streamurl2 = '';
            $stream->streamurl3 = '';
            $stream->cat_id = $catId;
            $stream->trans_id = 0;
            $stream->status = 0;
            $stream->running = 0;
            $stream->analysis_status = 'pending'; // Will be analyzed by FFprobe worker
            $stream->save();

            $streamIds[] = $stream->id;
            $importedCount++;

            echo "[DEBUG] Created stream: {$stream->name} (ID: {$stream->id})\n";

        } catch (Exception $e) {
            $failedCount++;
            echo "[ERROR] Failed to import stream '{$streamData['name']}': {$e->getMessage()}\n";
        }
    }

    // Queue streams for FFprobe analysis
    if (count($streamIds) > 0) {
        $ffprobeJob = [
            'stream_ids' => $streamIds,
            'batch_size' => count($streamIds)
        ];

        $ffprobeJobId = $queueService->push('ffprobe-analysis', $ffprobeJob);
        echo "[INFO] Queued {count($streamIds)} streams for FFprobe analysis (Job: {$ffprobeJobId})\n";
    }

    // Mark job as complete
    $result = [
        'imported' => $importedCount,
        'failed' => $failedCount,
        'total' => count($streams),
        'ffprobe_job_id' => $ffprobeJobId ?? null
    ];

    $queueService->complete('stream-import', $jobId, $result);

    echo "[INFO] Import job completed: {$importedCount} imported, {$failedCount} failed\n";
    exit(0);

} catch (Exception $e) {
    echo "[ERROR] Job failed: {$e->getMessage()}\n";
    echo "[ERROR] Stack trace: {$e->getTraceAsString()}\n";

    // Mark job as failed
    try {
        $queueService->fail('stream-import', $jobId, $e->getMessage());
    } catch (Exception $failEx) {
        echo "[ERROR] Failed to mark job as failed: {$failEx->getMessage()}\n";
    }

    exit(1);
}
