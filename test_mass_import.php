#!/usr/bin/env php
<?php
/**
 * Test Mass Live Stream Import from M3U URL
 *
 * This will:
 * 1. Download and parse M3U from URL
 * 2. Filter for live streams only (skip VOD)
 * 3. Create import job
 * 4. Monitor worker processing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Services/JobQueueService.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧪 Mass Live Stream Import Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// M3U playlist URL
$m3uUrl = 'https://hd.qwertq.site:443/playlist/Ce62Hccxs8/49PN4rSYvS/m3u_plus?key=live';

echo "📡 Step 1: Downloading M3U playlist...\n";
echo "   URL: {$m3uUrl}\n";

// Download M3U content
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'FOS-Streaming/70.0.0'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$content = @file_get_contents($m3uUrl, false, $context);

if ($content === false) {
    die("❌ Failed to download M3U playlist\n");
}

echo "✅ Downloaded M3U playlist (" . strlen($content) . " bytes)\n";

// Step 2: Parse M3U
echo "\n📄 Step 2: Parsing M3U content...\n";

$lines = explode("\n", $content);
$streams = [];
$currentStream = [];
$vodSkipped = 0;
$liveFound = 0;

foreach ($lines as $line) {
    $line = trim($line);

    if (empty($line) || $line === '#EXTM3U') continue;

    if (strpos($line, '#EXTINF:') === 0) {
        // Parse EXTINF line
        $currentStream = [];

        // Check duration (-1 = live stream, positive number = VOD)
        if (preg_match('/#EXTINF:(-?\d+(?:\.\d+)?)/', $line, $durationMatch)) {
            $duration = (float)$durationMatch[1];

            // Skip VOD (duration > 0)
            if ($duration > 0) {
                $vodSkipped++;
                $currentStream['skip'] = true;
                continue;
            }
        }

        // Extract tvg-type (movie/series = VOD)
        if (preg_match('/tvg-type="(movie|series)"/', $line)) {
            $vodSkipped++;
            $currentStream['skip'] = true;
            continue;
        }

        // Extract category from group-title
        if (preg_match('/group-title="([^"]+)"/', $line, $matches)) {
            $currentStream['category_name'] = trim($matches[1]);
        } else {
            $currentStream['category_name'] = 'Uncategorized';
        }

        // Extract stream name (after last comma)
        $parts = explode(',', $line, 2);
        if (isset($parts[1])) {
            $currentStream['name'] = trim($parts[1]);
        } else {
            $currentStream['name'] = 'Unknown Stream';
        }

    } elseif (strpos($line, 'http') === 0 && !isset($currentStream['skip'])) {
        // URL line for live stream
        $currentStream['url'] = $line;

        // Additional VOD detection by URL
        $urlLower = strtolower($line);
        if (strpos($urlLower, '/vod/') !== false ||
            strpos($urlLower, '/movies/') !== false ||
            strpos($urlLower, '/series/') !== false ||
            preg_match('/\.(mp4|mkv|avi)$/i', $line)) {
            $vodSkipped++;
            continue;
        }

        if (!empty($currentStream['name'])) {
            $streams[] = $currentStream;
            $liveFound++;
        }

        $currentStream = [];
    }
}

echo "✅ Parsing complete:\n";
echo "   • Live streams found: {$liveFound}\n";
echo "   • VOD streams skipped: {$vodSkipped}\n";

if (empty($streams)) {
    die("❌ No live streams found to import\n");
}

// Limit for testing (you can remove this limit)
$maxStreams = 50; // Import first 50 streams for testing
if (count($streams) > $maxStreams) {
    echo "\n⚠️  Limiting to first {$maxStreams} streams for testing\n";
    $streams = array_slice($streams, 0, $maxStreams);
}

// Show category breakdown
echo "\n📊 Streams by category:\n";
$categoryCount = [];
foreach ($streams as $stream) {
    $cat = $stream['category_name'];
    if (!isset($categoryCount[$cat])) {
        $categoryCount[$cat] = 0;
    }
    $categoryCount[$cat]++;
}

arsort($categoryCount);
foreach (array_slice($categoryCount, 0, 10) as $cat => $count) {
    echo "   • {$cat}: {$count} streams\n";
}

if (count($categoryCount) > 10) {
    $remaining = count($categoryCount) - 10;
    echo "   ... and {$remaining} more categories\n";
}

// Step 3: Create import job
echo "\n⚙️  Step 3: Creating import job...\n";

$queueService = new \App\Services\JobQueueService();

try {
    $jobId = $queueService->push('stream-import', [
        'streams' => $streams,
        'import_mode' => 'live_only',
        'auto_create_categories' => true,
        'default_status' => 0, // Stopped
        'source' => 'mass_import_test'
    ]);

    echo "✅ Job created successfully!\n";
    echo "   Job ID: {$jobId}\n";
    echo "   Queue: stream-import\n";
    echo "   Streams to import: " . count($streams) . "\n";

} catch (Exception $e) {
    die("❌ Failed to create job: {$e->getMessage()}\n");
}

// Step 4: Monitor processing
echo "\n⏳ Step 4: Monitoring worker processing...\n";
echo "   💡 Tip: Open another terminal and run:\n";
echo "      pm2 logs stream-import-worker --lines 50\n";
echo "      pm2 logs ffprobe-worker --lines 50\n";
echo "\n";

sleep(3);

// Monitor job status
$checkCount = 0;
$maxChecks = 60; // 60 checks × 2 seconds = 120 seconds

while ($checkCount < $maxChecks) {
    $status = $queueService->getJobStatus('stream-import', $jobId);

    if (!$status) {
        echo "\n✅ Job completed and moved to completed folder\n";
        break;
    }

    $statusText = strtoupper($status['status']);
    echo sprintf("\r   Status: %-15s | Time elapsed: %ds", $statusText, $checkCount * 2);

    if ($status['status'] === 'completed') {
        echo "\n✅ Import job completed!\n";
        if (isset($status['result'])) {
            $result = $status['result'];
            echo "   • Imported: {$result['imported']}\n";
            echo "   • Failed: {$result['failed']}\n";
            if (isset($result['ffprobe_job_id'])) {
                echo "   • FFprobe job: {$result['ffprobe_job_id']}\n";
            }
        }
        break;
    }

    if ($status['status'] === 'failed') {
        echo "\n❌ Job failed!\n";
        if (isset($status['error'])) {
            echo "   Error: {$status['error']}\n";
        }
        break;
    }

    sleep(2);
    $checkCount++;
}

echo "\n";

// Step 5: Check database results
echo "\n📊 Step 5: Database Results\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Total streams
$totalStreams = Stream::count();
echo "✅ Total streams in database: {$totalStreams}\n";

// By status
$stopped = Stream::where('status', 0)->count();
$running = Stream::where('status', 1)->count();
$error = Stream::where('status', 2)->count();

echo "\n📍 Stream Status:\n";
echo "   • Stopped: {$stopped}\n";
echo "   • Running: {$running}\n";
echo "   • Error: {$error}\n";

// Categories
$totalCategories = Category::count();
echo "\n📁 Categories created: {$totalCategories}\n";

$topCategories = Category::select('categories.*')
    ->selectRaw('COUNT(streams.id) as stream_count')
    ->leftJoin('streams', 'categories.id', '=', 'streams.cat_id')
    ->groupBy('categories.id')
    ->orderByDesc('stream_count')
    ->limit(10)
    ->get();

echo "\n📂 Top 10 Categories:\n";
foreach ($topCategories as $cat) {
    echo sprintf("   • %-30s %3d streams\n", $cat->name, $cat->stream_count);
}

// Analysis status
echo "\n🔍 FFprobe Analysis Status:\n";

$pending = Stream::where('analysis_status', 'pending')->count();
$analyzing = Stream::where('analysis_status', 'analyzing')->count();
$completed = Stream::where('analysis_status', 'completed')->count();
$failed = Stream::where('analysis_status', 'failed')->count();

echo "   ⏸️  Pending: {$pending}\n";
echo "   ⏳ Analyzing: {$analyzing}\n";
echo "   ✅ Completed: {$completed}\n";
echo "   ❌ Failed: {$failed}\n";

// Show sample of completed analysis
if ($completed > 0) {
    echo "\n📺 Sample of analyzed streams:\n";

    $analyzed = Stream::where('analysis_status', 'completed')
        ->with('category')
        ->limit(5)
        ->get();

    foreach ($analyzed as $stream) {
        $resolution = $stream->video_width && $stream->video_height
            ? "{$stream->video_width}x{$stream->video_height}"
            : 'N/A';

        $category = $stream->category ? $stream->category->name : 'N/A';

        echo sprintf("\n   %d. %s\n", $stream->id, $stream->name);
        echo "      Category: {$category}\n";

        if ($stream->video_codec) {
            echo "      Video: {$stream->video_codec} • {$resolution}";
            if ($stream->video_fps) echo " @ {$stream->video_fps}fps";
            echo "\n";
        }

        if ($stream->audio_codec) {
            $channels = $stream->audio_channels == 2 ? 'Stereo' : ($stream->audio_channels == 1 ? 'Mono' : "{$stream->audio_channels}ch");
            echo "      Audio: {$stream->audio_codec} • {$channels}\n";
        }

        if ($stream->health_score !== null) {
            echo "      Health: {$stream->health_score}/100\n";
        }
    }
}

// Latest imports
echo "\n📋 Latest 10 imported streams:\n";
$latest = Stream::with('category')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();

foreach ($latest as $stream) {
    $statusIcon = $stream->status == 0 ? '⏸️' : ($stream->status == 1 ? '▶️' : '❌');
    $analysisIcon = $stream->analysis_status == 'completed' ? '✅' :
                   ($stream->analysis_status == 'analyzing' ? '⏳' :
                   ($stream->analysis_status == 'failed' ? '❌' : '⏸️'));
    $cat = $stream->category ? $stream->category->name : 'N/A';

    echo "   {$statusIcon}{$analysisIcon} [{$cat}] {$stream->name}\n";
}

// Final summary
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✨ Mass Import Test Complete!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📊 Summary:\n";
echo "   • Streams imported: {$totalStreams}\n";
echo "   • Categories created: {$totalCategories}\n";
echo "   • Stopped (default): {$stopped}\n";
echo "   • Analysis completed: {$completed}\n";
echo "   • Analysis pending: {$pending}\n";

if ($pending > 0) {
    echo "\n⏳ FFprobe analysis is still running...\n";
    echo "   Run this script again in a few minutes to see updated results.\n";
    echo "   Or monitor: pm2 logs ffprobe-worker\n";
}

echo "\n🎯 Next Steps:\n";
echo "   1. View in admin UI: http://localhost:7777/admin#/streams\n";
echo "   2. Monitor workers: pm2 logs --lines 100\n";
echo "   3. Check PM2 status: pm2 list\n";
echo "   4. Re-run this script to see analysis progress\n";

echo "\n";
