#!/usr/bin/env php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Services/JobQueueService.php';

echo "🧪 Mass Import Test - Using Downloaded Playlist\n";
echo "═══════════════════════════════════════════════════\n\n";

$m3uFile = 'test_playlist_real.m3u';
$content = file_get_contents($m3uFile);
$lines = explode("\n", $content);

$streams = [];
$current = [];
$vodSkipped = 0;

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || $line === '#EXTM3U') continue;

    if (strpos($line, '#EXTINF:') === 0) {
        if (preg_match('/#EXTINF:(\d+)/', $line, $m) && $m[1] > 0) {
            $vodSkipped++;
            $current = ['skip' => true];
            continue;
        }

        if (preg_match('/group-title="([^"]+)"/', $line, $m)) {
            $current['category_name'] = $m[1];
        } else {
            $current['category_name'] = 'Uncategorized';
        }

        $parts = explode(',', $line, 2);
        if (isset($parts[1])) {
            $current['name'] = trim($parts[1]);
        }
    } elseif (strpos($line, 'http') === 0 && !isset($current['skip'])) {
        $current['url'] = $line;
        $streams[] = $current;
        $current = [];
    }
}

echo "📊 Parsed: " . count($streams) . " live streams\n";
echo "   VOD skipped: $vodSkipped\n\n";

// Test with 20 streams
$limit = 20;
$streams = array_slice($streams, 0, $limit);

echo "✅ Testing with $limit streams\n\n";

// Show categories
$cats = [];
foreach ($streams as $s) {
    if (!isset($cats[$s['category_name']])) $cats[$s['category_name']] = 0;
    $cats[$s['category_name']]++;
}

echo "📁 Categories:\n";
foreach ($cats as $cat => $count) {
    echo "   • $cat: $count\n";
}

// Create job
$queue = new \App\Services\JobQueueService();
$jobId = $queue->push('stream-import', [
    'streams' => $streams,
    'import_mode' => 'live_only',
    'auto_create_categories' => true,
    'default_status' => 0
]);

echo "\n✅ Job created: $jobId\n";
echo "⏳ Workers processing...\n\n";

echo "Monitor: pm2 logs stream-import-worker --lines 20\n";
echo "         pm2 logs ffprobe-worker --lines 20\n";
