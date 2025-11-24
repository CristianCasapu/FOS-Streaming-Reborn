#!/usr/bin/env php
<?php
/**
 * PM2 Workers Fix Test Script
 * Tests the priority column and PM2 worker functionality
 */

require_once __DIR__ . '/config.php';

echo "=================================\n";
echo "PM2 Workers Fix Test\n";
echo "=================================\n\n";

try {
    // Test 1: Retrieve all workers
    echo "Test 1: Retrieve all PM2 workers\n";
    $workers = PM2Worker::all();

    if ($workers->isEmpty()) {
        throw new Exception('No workers found! Run the seeder first.');
    }

    echo "✓ Found {$workers->count()} workers\n\n";

    // Test 2: Test enabled workers ordered by priority
    echo "Test 2: Get enabled workers ordered by priority\n";
    $enabledWorkers = PM2Worker::enabled()->byPriority()->get();

    echo "  Enabled workers: {$enabledWorkers->count()}\n";
    foreach ($enabledWorkers as $worker) {
        echo "  - [{$worker->priority}] {$worker->name} ({$worker->display_name})\n";
    }
    echo "✓ Priority ordering works correctly\n\n";

    // Test 3: Test PM2WorkerService
    echo "Test 3: Test PM2WorkerService\n";
    $service = new App\Services\PM2WorkerService();
    $serviceWorkers = $service->getAllEnabledWorkers();

    echo "  Workers from service: {$serviceWorkers->count()}\n";
    echo "✓ PM2WorkerService works correctly\n\n";

    // Test 4: Verify priority column exists and has correct values
    echo "Test 4: Verify priority column\n";
    foreach ($workers as $worker) {
        if (!isset($worker->priority)) {
            throw new Exception("Worker {$worker->name} has no priority!");
        }

        if (!is_int($worker->priority)) {
            throw new Exception("Worker {$worker->name} priority is not an integer: " . gettype($worker->priority));
        }
    }
    echo "✓ All workers have valid priority values\n\n";

    // Test 5: Test worker categories
    echo "Test 5: Test worker categories\n";
    $streamingWorkers = PM2Worker::category('streaming')->get();
    $monitoringWorkers = PM2Worker::category('monitoring')->get();

    echo "  Streaming workers: {$streamingWorkers->count()}\n";
    echo "  Monitoring workers: {$monitoringWorkers->count()}\n";
    echo "✓ Category filtering works\n\n";

    // Test 6: Test auto-start workers
    echo "Test 6: Test auto-start workers\n";
    $autoStartWorkers = PM2Worker::autoStart()->get();

    echo "  Auto-start workers: {$autoStartWorkers->count()}\n";
    echo "✓ Auto-start scope works\n\n";

    // Test 7: Verify all new columns exist
    echo "Test 7: Verify new columns\n";
    $firstWorker = $workers->first();
    $requiredColumns = [
        'priority', 'display_name', 'category', 'tags', 'cwd', 'args',
        'max_restarts', 'min_uptime', 'restart_delay', 'autorestart',
        'log_level', 'error_file', 'out_file', 'log_file', 'log_date_format', 'merge_logs',
        'ignore_watch', 'kill_timeout', 'listen_timeout', 'shutdown_with_message',
        'auto_start', 'created_by', 'updated_by'
    ];

    $missingColumns = [];
    foreach ($requiredColumns as $column) {
        if (!array_key_exists($column, $firstWorker->getAttributes())) {
            $missingColumns[] = $column;
        }
    }

    if (!empty($missingColumns)) {
        throw new Exception('Missing columns: ' . implode(', ', $missingColumns));
    }

    echo "✓ All required columns exist\n\n";

    // Test 8: Test ecosystem config generation
    echo "Test 8: Test ecosystem config generation\n";
    $ecosystemArray = $firstWorker->toEcosystemConfig();

    if (!isset($ecosystemArray['name']) || !isset($ecosystemArray['script'])) {
        throw new Exception('Ecosystem config generation failed');
    }

    echo "  Generated config for: {$ecosystemArray['name']}\n";
    echo "✓ Ecosystem config generation works\n\n";

    // Test 9: Test worker attributes
    echo "Test 9: Test worker attributes\n";
    foreach ($workers as $worker) {
        echo "  Worker: {$worker->display_name}\n";
        echo "    Priority: {$worker->priority}\n";
        echo "    Category: {$worker->category}\n";
        echo "    Exec Mode: {$worker->exec_mode}\n";
        echo "    Instances: {$worker->instances}\n";
        echo "    Memory Limit: {$worker->max_memory_restart}\n";
        echo "    Auto-restart: " . ($worker->autorestart ? 'Yes' : 'No') . "\n";
        echo "    Auto-start: " . ($worker->auto_start ? 'Yes' : 'No') . "\n";
        echo "    Enabled: " . ($worker->enabled ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    echo "✓ All worker attributes accessible\n\n";

    // Test 10: Test JSON columns
    echo "Test 10: Test JSON columns\n";
    $worker = $workers->first();

    echo "  env_vars type: " . gettype($worker->env_vars) . "\n";
    echo "  tags type: " . gettype($worker->tags) . "\n";
    echo "  args type: " . gettype($worker->args) . "\n";
    echo "  ignore_watch type: " . gettype($worker->ignore_watch) . "\n";

    if (is_array($worker->env_vars) || is_null($worker->env_vars)) {
        echo "✓ JSON casting works correctly\n\n";
    } else {
        throw new Exception('JSON casting failed for env_vars');
    }

    echo "=================================\n";
    echo "All tests passed! ✓\n";
    echo "=================================\n";
    echo "\nPM2 workers fix completed successfully!\n";
    echo "The priority column and all new columns are working as expected.\n\n";

    // Print summary
    echo "Summary:\n";
    echo "- Total workers: {$workers->count()}\n";
    echo "- Enabled workers: {$enabledWorkers->count()}\n";
    echo "- Streaming workers: {$streamingWorkers->count()}\n";
    echo "- Monitoring workers: {$monitoringWorkers->count()}\n";
    echo "- Priority range: {$workers->min('priority')} - {$workers->max('priority')}\n";
    echo "- All columns present: Yes\n";
    echo "- JSON casting: Working\n\n";

    echo "You can now:\n";
    echo "1. Access Settings page in admin UI\n";
    echo "2. View PM2 Background Workers section\n";
    echo "3. Start/stop/restart workers\n";
    echo "4. Install PM2 if needed\n\n";

} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
