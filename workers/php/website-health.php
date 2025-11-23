<?php
/**
 * Website Health PHP Script
 *
 * Called by website-health-worker.js
 * Monitors system services and resources
 */

require_once __DIR__ . '/../../config.php';

use App\Services\WebsiteHealthMonitorService;

try {
    $service = new WebsiteHealthMonitorService();
    $result = $service->monitorSystem();

    // Output JSON for Node.js worker to parse
    echo json_encode($result);

} catch (Exception $e) {
    // Output error as JSON
    echo json_encode([
        'checked' => 0,
        'healthy' => 0,
        'warning' => 0,
        'critical' => 0,
        'error' => $e->getMessage()
    ]);

    exit(1);
}
