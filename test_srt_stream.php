<?php
/**
 * Test SRT stream setup for proxy-only streaming
 */

require_once 'config.php';
require_once 'models/Stream.php';

echo "\033[34m========================================\033[0m\n";
echo "\033[34mSRT Proxy Stream Test Setup\033[0m\n";
echo "\033[34m========================================\033[0m\n\n";

// Create a test SRT stream
$testStream = [
    'name' => 'Test SRT Proxy Stream',
    'streamurl' => 'srt://source.example.com:9000',
    'source_url' => 'srt://source.example.com:9000?passphrase=test123&pbkeylen=32',
    'stream_mode' => 'proxy', // PROXY ONLY - NO TRANSCODING
    'stream_type' => 'live',
    'running' => 0,
    'status' => 1,
    'cat_id' => 1,
    'logo' => '',  // Include logo field
    'tvid' => '',  // Include tvid field
    'require_device_lock' => 1,
    'max_connections' => 10,
    'proxy_settings' => json_encode([
        'protocol' => 'srt',
        'latency' => 1000,
        'bufferSize' => 8192000,
        'maxBandwidth' => 0 // unlimited
    ]),
    'encryption_settings' => json_encode([
        'passphrase' => 'test123',
        'keyLength' => 32, // AES-256
        'algorithm' => 'AES-256'
    ])
];

// Check if test stream exists
$existingStream = Stream::where('name', 'Test SRT Proxy Stream')->first();

if ($existingStream) {
    echo "\033[33mTest stream already exists, updating...\033[0m\n";
    $existingStream->update($testStream);
    $stream = $existingStream;
} else {
    echo "\033[32mCreating test SRT stream...\033[0m\n";
    $stream = Stream::create($testStream);
}

echo "\n\033[34mStream Details:\033[0m\n";
echo "  ID: {$stream->id}\n";
echo "  Name: {$stream->name}\n";
echo "  Mode: {$stream->stream_mode} (PROXY ONLY)\n";
echo "  Source: {$stream->source_url}\n";
echo "  Device Lock: " . ($stream->require_device_lock ? 'Yes' : 'No') . "\n";
echo "  Max Connections: {$stream->max_connections}\n";
echo "  Proxy Port: " . ($stream->proxy_port ?: 'Not assigned yet') . "\n";

echo "\n\033[34mProxy Settings:\033[0m\n";
$proxySettings = json_decode($stream->proxy_settings, true);
foreach ($proxySettings as $key => $value) {
    echo "  $key: $value\n";
}

echo "\n\033[34mEncryption Settings:\033[0m\n";
$encryptionSettings = json_decode($stream->encryption_settings, true);
echo "  Algorithm: {$encryptionSettings['algorithm']}\n";
echo "  Key Length: {$encryptionSettings['keyLength']} bytes\n";
echo "  Passphrase: ***hidden***\n";

echo "\n\033[32m✓ Test stream configured successfully!\033[0m\n";
echo "\n\033[34mNext Steps:\033[0m\n";
echo "1. Start the SRT proxy worker: pm2 start srt-proxy-worker\n";
echo "2. The proxy will listen on port: " . (9000 + $stream->id) . "\n";
echo "3. Connect with SRT client using:\n";
echo "   srt://your-server:" . (9000 + $stream->id) . "?passphrase=<encryption_key>&streamid=<session_token>:stream\n";
echo "\n\033[33mNote: This is PROXY-ONLY mode - no transcoding will occur!\033[0m\n";