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
    'mode' => $mode,
    'streams' => []
];

try {
    // Initialize FFprobe service
    $ffprobeService = new \App\Services\FFprobeService();

    // Build query based on mode
    $query = Stream::query();

    if ($mode === 'auto') {
        // Auto mode: Find streams that need analysis OR have no profile set
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
        $streamResult = [
            'id' => $stream->id,
            'name' => $stream->name,
            'success' => false,
            'profile_set' => false,
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
            } else {
                $stats['failed']++;
                $streamResult['message'] = $stream->analysis_error ?? 'Analysis failed';
            }

        } catch (Exception $e) {
            $stats['failed']++;
            $stream->analysis_status = 'failed';
            $stream->analysis_error = $e->getMessage();
            $stream->last_analyzed = date('Y-m-d H:i:s');
            $stream->save();
            $streamResult['message'] = $e->getMessage();
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
