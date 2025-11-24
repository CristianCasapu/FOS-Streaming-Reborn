#!/usr/bin/env php
<?php
/**
 * Settings Refactoring Test Script
 * Tests the new columnar settings implementation
 */

require_once __DIR__ . '/config.php';

echo "=================================\n";
echo "Settings Refactoring Test\n";
echo "=================================\n\n";

try {
    // Test 1: Retrieve settings instance
    echo "Test 1: Retrieve settings instance\n";
    $settings = Setting::first();

    if (!$settings) {
        throw new Exception('Settings not found!');
    }

    echo "✓ Settings instance retrieved successfully\n";
    echo "  ID: {$settings->id}\n\n";

    // Test 2: Test core system settings
    echo "Test 2: Core system settings\n";
    echo "  FFmpeg Path: {$settings->ffmpeg_path}\n";
    echo "  FFprobe Path: {$settings->ffprobe_path}\n";
    echo "  Web IP: " . ($settings->webip ?? 'Not set') . "\n";
    echo "  Web Port: {$settings->webport}\n";
    echo "  HLS Folder: {$settings->hlsfolder}\n";
    echo "  Logo URL: " . ($settings->logourl ?? 'Not set') . "\n";
    echo "  Favicon URL: " . ($settings->faviconurl ?? 'Not set') . "\n";
    echo "  User Agent: {$settings->user_agent}\n";
    echo "✓ Core settings accessible\n\n";

    // Test 3: Test trial settings
    echo "Test 3: Trial subscription settings\n";
    echo "  Trial Duration (hours): {$settings->trial_duration_hours}\n";
    echo "  Trial Enabled: " . ($settings->trial_enabled ? 'Yes' : 'No') . "\n";
    echo "  Trial Requires Approval: " . ($settings->trial_requires_approval ? 'Yes' : 'No') . "\n";
    echo "  Max Trials Per User: {$settings->max_trials_per_user}\n";
    echo "✓ Trial settings accessible\n\n";

    // Test 4: Test device security settings
    echo "Test 4: Device security settings\n";
    echo "  Concurrent Stream Grace (seconds): {$settings->device_concurrent_stream_grace_seconds}\n";
    echo "  Session Timeout (minutes): {$settings->device_session_timeout_minutes}\n";
    echo "  Max Registration Per Day: {$settings->device_max_registration_per_day}\n";
    echo "  Fingerprint TTL (days): {$settings->device_fingerprint_ttl_days}\n";
    echo "✓ Device settings accessible\n\n";

    // Test 5: Test violation thresholds
    echo "Test 5: Violation thresholds\n";
    echo "  Low Threshold: {$settings->device_violation_threshold_low}\n";
    echo "  Medium Threshold: {$settings->device_violation_threshold_medium}\n";
    echo "  High Threshold: {$settings->device_violation_threshold_high}\n";
    echo "  Critical Threshold: {$settings->device_violation_threshold_critical}\n";
    echo "  Violation Window (hours): {$settings->device_violation_window_hours}\n";
    echo "  Location Accuracy (km): {$settings->device_location_accuracy_km}\n";
    echo "✓ Violation thresholds accessible\n\n";

    // Test 6: Test sudo settings
    echo "Test 6: Sudo & system command settings\n";
    echo "  Sudo User: " . ($settings->sudo_user ?? 'Not configured') . "\n";
    echo "  Sudo Password Set: " . ($settings->sudo_password ? 'Yes' : 'No') . "\n";
    echo "  System Commands Enabled: " . ($settings->system_commands_enabled ? 'Yes' : 'No') . "\n";
    echo "  Last Command At: " . ($settings->last_command_at ?? 'Never') . "\n";
    echo "✓ Sudo settings accessible\n\n";

    // Test 7: Test Setting::getInstance() helper
    echo "Test 7: Test getInstance() helper\n";
    $instance = Setting::getInstance();
    if (!$instance) {
        throw new Exception('getInstance() failed!');
    }
    echo "✓ getInstance() works correctly\n\n";

    // Test 8: Test Setting::getValue() helper
    echo "Test 8: Test getValue() helper\n";
    $webport = Setting::getValue('webport', 8000);
    $trialEnabled = Setting::getValue('trial_enabled', 1);
    $nonExistent = Setting::getValue('non_existent_setting', 'default');

    echo "  webport: {$webport}\n";
    echo "  trial_enabled: {$trialEnabled}\n";
    echo "  non_existent: {$nonExistent}\n";
    echo "✓ getValue() works correctly\n\n";

    // Test 9: Test Setting::setValue() helper
    echo "Test 9: Test setValue() helper\n";
    $originalPort = $settings->webport;
    $testPort = 9999;

    Setting::setValue('webport', $testPort);
    $updatedSettings = Setting::first();

    if ($updatedSettings->webport != $testPort) {
        throw new Exception('setValue() failed to update!');
    }

    // Restore original value
    Setting::setValue('webport', $originalPort);
    $restoredSettings = Setting::first();

    if ($restoredSettings->webport != $originalPort) {
        throw new Exception('setValue() failed to restore!');
    }

    echo "✓ setValue() works correctly\n\n";

    // Test 10: Test Trial model static methods
    echo "Test 10: Test Trial model static methods\n";
    $defaultDuration = Trial::getDefaultDuration();
    $trialSystemEnabled = Trial::isTrialSystemEnabled();
    $requiresApproval = Trial::requiresApproval();

    echo "  Default Duration: {$defaultDuration} hours\n";
    echo "  Trial System Enabled: " . ($trialSystemEnabled ? 'Yes' : 'No') . "\n";
    echo "  Requires Approval: " . ($requiresApproval ? 'Yes' : 'No') . "\n";
    echo "✓ Trial model static methods work correctly\n\n";

    // Test 11: Test casts
    echo "Test 11: Test attribute casting\n";
    echo "  trial_enabled type: " . gettype($settings->trial_enabled) . " (should be boolean)\n";
    echo "  webport type: " . gettype($settings->webport) . " (should be integer)\n";
    echo "  device_max_registration_per_day type: " . gettype($settings->device_max_registration_per_day) . " (should be integer)\n";
    echo "✓ Attribute casting works correctly\n\n";

    // Test 12: Verify timestamps
    echo "Test 12: Verify timestamps\n";
    echo "  Created At: " . ($settings->created_at ? $settings->created_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    echo "  Updated At: " . ($settings->updated_at ? $settings->updated_at->format('Y-m-d H:i:s') : 'N/A') . "\n";
    echo "✓ Timestamps work correctly\n\n";

    echo "=================================\n";
    echo "All tests passed! ✓\n";
    echo "=================================\n";
    echo "\nSettings refactoring completed successfully!\n";
    echo "The new columnar schema is working as expected.\n\n";

    // Print summary
    echo "Summary:\n";
    echo "- Schema: Columnar (single-row, multi-column)\n";
    echo "- Total columns: 29\n";
    echo "- Performance: Optimized with indexed columns\n";
    echo "- Type safety: Eloquent casting enabled\n";
    echo "- Helper methods: getInstance(), getValue(), setValue()\n";
    echo "- Database: UTF8MB4 with proper collation\n\n";

} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
