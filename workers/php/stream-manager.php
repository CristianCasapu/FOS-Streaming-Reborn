<?php
/**
 * Stream Manager PHP Script
 *
 * Called by stream-manager-worker.js
 * Processes queued stream commands
 */

require_once __DIR__ . '/../../config.php';

use App\Services\StreamManagerService;

try {
    $service = new StreamManagerService();
    $result = $service->processQueuedCommands();

    // Output JSON for Node.js worker to parse
    echo json_encode($result);

} catch (Exception $e) {
    // Output error as JSON
    echo json_encode([
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'error' => $e->getMessage()
    ]);

    exit(1);
}
