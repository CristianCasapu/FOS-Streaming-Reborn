#!/usr/bin/env php
<?php
/**
 * Background Stream Analysis Job
 *
 * Analyzes streams that need analysis (pending, failed >1h ago, or not analyzed in 24h)
 * Can be run via cron or manually
 *
 * Usage:
 *   php scripts/analyze-streams.php [options]
 *
 * Options:
 *   --limit=N        Analyze max N streams (default: 10)
 *   --force         Force analysis even if recently analyzed
 *   --stream-id=N   Analyze specific stream ID
 *   --verbose       Show detailed output
 *
 * Cron example (analyze 50 streams every hour):
 *   0 * * * * cd /path/to/fos && php scripts/analyze-streams.php --limit=50 >> /var/log/fos-analysis.log 2>&1
 */

// Load configuration
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/FFprobeService.php';

// Parse command line options
$options = getopt('', ['limit::', 'force', 'stream-id::', 'verbose']);

$limit = isset($options['limit']) ? (int)$options['limit'] : 10;
$force = isset($options['force']);
$streamId = isset($options['stream-id']) ? (int)$options['stream-id'] : null;
$verbose = isset($options['verbose']);

// Helper functions
function log_message($message, $isVerbose = false) {
    global $verbose;
    if (!$isVerbose || $verbose) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

function log_error($message) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $message . "\n";
}

// Start
log_message("Stream Analysis Job Started");

if ($streamId) {
    log_message("Analyzing specific stream ID: {$streamId}");
} else {
    log_message("Analyzing up to {$limit} streams that need analysis");
}

try {
    // Initialize FFprobe service
    $ffprobeService = new \App\Services\FFprobeService();

    // Get streams to analyze
    if ($streamId) {
        $stream = Stream::find($streamId);
        if (!$stream) {
            log_error("Stream ID {$streamId} not found");
            exit(1);
        }
        $streams = [$stream];
    } else {
        // Build query for streams that need analysis
        $query = Stream::query();

        if ($force) {
            // Force analyze all streams
            $streams = $query->limit($limit)->get();
        } else {
            // Get streams that need analysis
            $query->where(function($q) {
                $q->where('analysis_status', '=', 'pending')
                  ->orWhereNull('last_analyzed')
                  ->orWhere(function($q2) {
                      // Failed analysis more than 1 hour ago
                      $q2->where('analysis_status', '=', 'failed')
                         ->where('last_analyzed', '<', date('Y-m-d H:i:s', time() - 3600));
                  })
                  ->orWhere(function($q3) {
                      // Successful analysis more than 24 hours ago
                      $q3->where('analysis_status', '=', 'completed')
                         ->where('last_analyzed', '<', date('Y-m-d H:i:s', time() - 86400));
                  });
            });

            $streams = $query->limit($limit)->get();
        }
    }

    if ($streams->isEmpty()) {
        log_message("No streams need analysis at this time");
        exit(0);
    }

    log_message("Found " . count($streams) . " stream(s) to analyze");

    $stats = [
        'total' => count($streams),
        'analyzed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'start_time' => time()
    ];

    // Analyze each stream
    foreach ($streams as $index => $stream) {
        $streamNum = $index + 1;
        $streamName = $stream->name;

        log_message("({$streamNum}/{$stats['total']}) Analyzing: {$streamName}", true);

        try {
            // Mark as analyzing
            $stream->analysis_status = 'analyzing';
            $stream->save();

            // Determine if it's a live stream
            $isLive = $stream->stream_type !== 'vod';

            // Analyze stream
            $startTime = microtime(true);
            $analysis = $ffprobeService->analyzeStream($stream->streamurl, $isLive);
            $duration = round(microtime(true) - $startTime, 2);

            // Update stream with results
            $stream->updateFromAnalysis($analysis);

            if ($stream->analysis_status === 'completed') {
                $stats['analyzed']++;
                $tech = $stream->getTechnicalSummary();

                // Auto-set optimal transcode profile if not manually set
                $profileSet = false;
                if ($stream->needsProfileAssignment()) {
                    $profileSet = $stream->setOptimalTranscodeProfile();
                }

                $profileInfo = $profileSet ? ' | Profile auto-set' : '';
                log_message(
                    "  ✓ Success ({$duration}s) - " .
                    "{$tech['quality']} | {$tech['video_codec']} | {$tech['audio_codec']} | " .
                    "Health: {$tech['health_score']}/100{$profileInfo}",
                    true
                );
            } else {
                $stats['failed']++;
                $error = $stream->analysis_error ?? 'Unknown error';
                log_error("  ✗ Failed ({$duration}s) - {$error}");
            }

        } catch (Exception $e) {
            $stats['failed']++;
            $stream->analysis_status = 'failed';
            $stream->analysis_error = $e->getMessage();
            $stream->save();
            log_error("  ✗ Exception - " . $e->getMessage());
        }

        // Small delay to prevent overwhelming the system
        if ($streamNum < $stats['total']) {
            usleep(500000); // 0.5 second delay
        }
    }

    // Calculate summary
    $totalTime = time() - $stats['start_time'];
    $avgTime = $stats['total'] > 0 ? round($totalTime / $stats['total'], 2) : 0;

    log_message("");
    log_message("=== Analysis Complete ===");
    log_message("Total streams: {$stats['total']}");
    log_message("Analyzed successfully: {$stats['analyzed']}");
    log_message("Failed: {$stats['failed']}");
    log_message("Total time: {$totalTime}s");
    log_message("Average time per stream: {$avgTime}s");

    exit(0);

} catch (Exception $e) {
    log_error("Fatal error: " . $e->getMessage());
    log_error("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
