<?php
/**
 * Stream Monitor PHP Script
 *
 * Called by stream-monitor-worker.js
 * Monitors running stream PIDs
 */

require_once __DIR__ . '/../../config.php';

use App\Services\StreamMonitorService;

try {
    $service = new StreamMonitorService();
    $result = $service->monitorStreams();

    // Output JSON for Node.js worker to parse
    echo json_encode($result);

} catch (Exception $e) {
    // Output error as JSON
    echo json_encode([
        'checked' => 0,
        'healthy' => 0,
        'crashed' => 0,
        'restarted' => 0,
        'error' => $e->getMessage()
    ]);

    exit(1);
}
