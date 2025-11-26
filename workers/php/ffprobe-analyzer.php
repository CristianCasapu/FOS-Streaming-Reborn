<?php
/**
 * FFprobe Analyzer PHP Script
 *
 * Called by ffprobe-worker.cjs
 * Analyzes streams that need analysis and auto-sets optimal profiles
 *
 * Environment variables:
 * - CONCURRENT_STREAMS: Number of streams to analyze per run (default: 1)
 * - MODE: 'queue' for job queue, 'auto' for auto-analyze streams without profiles
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/Services/FFprobeService.php';

// Get configuration from environment
$concurrentStreams = (int)(getenv('CONCURRENT_STREAMS') ?: 1);
$mode = getenv('MODE') ?: 'auto';

$stats = [
    'processed' => 0,
    'analyzed' => 0,
    'failed' => 0,
    'profiles_set' => 0,
    'recovered' => 0,  // Count of streams recovered from error state
    'mode' => $mode,
    'streams' => []
];

try {
    // Initialize FFprobe service
    $ffprobeService = new \App\Services\FFprobeService();

    // Build query based on mode
    $query = Stream::query();

    if ($mode === 'auto') {
        // Auto mode: Find streams that need analysis OR have no profile set OR are in error state
        $query->where(function($q) {
            // Streams with pending analysis
            $q->where('analysis_status', '=', 'pending')
              ->orWhereNull('analysis_status')
              // Streams that need re-analysis (failed > 1 hour ago)
              ->orWhere(function($q2) {
                  $q2->where('analysis_status', '=', 'failed')
                     ->where('last_analyzed', '<', date('Y-m-d H:i:s', time() - 3600));
              })
              // Streams without profiles that have been analyzed
              ->orWhere(function($q3) {
                  $q3->where('analysis_status', '=', 'completed')
                     ->where(function($q4) {
                         $q4->where('trans_id', '=', 0)
                            ->orWhereNull('trans_id');
                     });
              })
              // NEW: Streams in error/crashed state that need validation for recovery
              // Only pick up streams not currently being processed (no scheduled command)
              ->orWhere(function($q5) {
                  $q5->whereIn('state', ['error', 'crashed'])
                     ->where('enabled', 1)  // Only enabled streams
                     ->where(function($q6) {
                         $q6->where('scheduled_command', '=', 'none')
                            ->orWhereNull('scheduled_command');
                     })
                     ->whereNull('pid');  // Not currently running
              });
        });
    } else {
        // Queue mode: Only streams explicitly queued for analysis
        $query->where('analysis_status', '=', 'analyzing');
    }

    // Limit to concurrent streams count
    $streams = $query->limit($concurrentStreams)->get();

    if ($streams->isEmpty()) {
        echo json_encode($stats);
        exit(0);
    }

    foreach ($streams as $stream) {
        $stats['processed']++;

        // Track if this stream was in error state before analysis
        $wasInErrorState = in_array($stream->state, ['error', 'crashed']);

        $streamResult = [
            'id' => $stream->id,
            'name' => $stream->name,
            'success' => false,
            'profile_set' => false,
            'recovered' => false,
            'previous_state' => $stream->state,
            'message' => ''
        ];

        try {
            // Mark as analyzing
            $stream->analysis_status = 'analyzing';
            $stream->save();

            // Determine if it's a live stream
            $isLive = $stream->stream_type !== 'vod';

            // Analyze stream
            $analysis = $ffprobeService->analyzeStream($stream->streamurl, $isLive);

            // Update stream with results
            $stream->updateFromAnalysis($analysis);

            if ($stream->analysis_status === 'completed') {
                $stats['analyzed']++;
                $streamResult['success'] = true;

                // RECOVERY: If stream was in error/crashed state and analysis succeeded,
                // recover it to 'stopped' state so it can be started again
                if ($wasInErrorState) {
                    $stream->state = 'stopped';
                    $stream->restart_attempts = 0;  // Reset restart counter
                    $stream->health_check_failures = 0;  // Reset health failures
                    $stream->save();

                    $stats['recovered']++;
                    $streamResult['recovered'] = true;
                    $streamResult['message'] = 'Recovered from ' . $streamResult['previous_state'] . ' state - stream validated successfully';
                } else {
                    // Auto-set optimal transcode profile if not manually set
                    if ($stream->needsProfileAssignment()) {
                        if ($stream->setOptimalTranscodeProfile()) {
                            $stats['profiles_set']++;
                            $streamResult['profile_set'] = true;
                            $streamResult['message'] = 'Analyzed and profile auto-set';
                        } else {
                            $streamResult['message'] = 'Analyzed but no matching profile found';
                        }
                    } else {
                        $streamResult['message'] = 'Analyzed (profile already set)';
                    }
                }
            } else {
                $stats['failed']++;
                $streamResult['message'] = $stream->analysis_error ?? 'Analysis failed';

                // If stream was in error state and analysis also failed,
                // keep it in error state but update the error message
                if ($wasInErrorState) {
                    $streamResult['message'] = 'Stream remains in ' . $streamResult['previous_state'] . ' state - validation failed: ' . ($stream->analysis_error ?? 'Unknown error');
                }
            }

        } catch (Exception $e) {
            $stats['failed']++;
            $stream->analysis_status = 'failed';
            $stream->analysis_error = $e->getMessage();
            $stream->last_analyzed = date('Y-m-d H:i:s');
            $stream->save();
            $streamResult['message'] = $e->getMessage();

            if ($wasInErrorState) {
                $streamResult['message'] = 'Stream remains in ' . $streamResult['previous_state'] . ' state - validation error: ' . $e->getMessage();
            }
        }

        $stats['streams'][] = $streamResult;
    }

    echo json_encode($stats);

} catch (Exception $e) {
    echo json_encode([
        'processed' => 0,
        'analyzed' => 0,
        'failed' => 0,
        'profiles_set' => 0,
        'error' => $e->getMessage()
    ]);
    exit(1);
}
