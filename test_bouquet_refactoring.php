<?php
/**
 * Test script to verify bouquet refactoring
 */

require_once __DIR__ . '/config.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "=== Testing Bouquet Refactoring ===\n\n";

// Test 1: Check bouquets table structure
echo "1. Checking bouquets table structure...\n";
$columns = Capsule::select("SHOW COLUMNS FROM bouquets");
$hasStreamIds = false;
$hasChannels = false;

foreach ($columns as $column) {
    if ($column->Field === 'stream_ids') {
        $hasStreamIds = true;
        echo "   ✓ stream_ids column exists (Type: {$column->Type})\n";
    }
    if ($column->Field === 'channels') {
        $hasChannels = true;
        echo "   ⚠ channels column still exists\n";
    }
}

if (!$hasStreamIds) {
    echo "   ✗ stream_ids column NOT found!\n";
}

// Test 2: Check for channel-related tables
echo "\n2. Checking for channel-related tables...\n";
$tables = Capsule::select("SHOW TABLES LIKE '%channel%'");
if (empty($tables)) {
    echo "   ✓ No channel-related tables found (good!)\n";
} else {
    echo "   ⚠ Found channel-related tables:\n";
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        echo "      - $tableName\n";
    }
}

// Test 3: Test Bouquet model
echo "\n3. Testing Bouquet model...\n";
try {
    $bouquet = new Bouquet();
    echo "   ✓ Bouquet model instantiated successfully\n";

    // Check if stream_ids is in fillable
    $fillable = $bouquet->getFillable();
    if (in_array('stream_ids', $fillable)) {
        echo "   ✓ stream_ids is fillable\n";
    } else {
        echo "   ✗ stream_ids is NOT fillable\n";
    }

    // Check casts
    $casts = $bouquet->getCasts();
    if (isset($casts['stream_ids']) && $casts['stream_ids'] === 'array') {
        echo "   ✓ stream_ids is cast to array\n";
    } else {
        echo "   ✗ stream_ids is NOT cast to array\n";
    }

} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Test creating a bouquet with streams
echo "\n4. Testing bouquet creation with streams...\n";
try {
    $testBouquet = Bouquet::where('name', 'Test Refactoring')->first();
    if ($testBouquet) {
        $testBouquet->delete();
    }

    $bouquet = new Bouquet();
    $bouquet->name = 'Test Refactoring';
    $bouquet->description = 'Testing stream_ids functionality';
    $bouquet->stream_ids = [1, 2, 3];
    $bouquet->is_active = 1;
    $bouquet->sort_order = 999;
    $bouquet->save();

    echo "   ✓ Bouquet created with stream_ids\n";
    echo "   ✓ Bouquet ID: {$bouquet->id}\n";

    // Reload and verify
    $reloaded = Bouquet::find($bouquet->id);
    if ($reloaded && is_array($reloaded->stream_ids) && $reloaded->stream_ids == [1, 2, 3]) {
        echo "   ✓ stream_ids saved and loaded correctly as array\n";
        echo "   ✓ Stream count: " . $reloaded->stream_count . "\n";
    } else {
        echo "   ✗ stream_ids not loaded correctly\n";
    }

    // Clean up
    $reloaded->delete();
    echo "   ✓ Test bouquet deleted\n";

} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Check existing bouquets
echo "\n5. Checking existing bouquets...\n";
try {
    $bouquets = Bouquet::all();
    echo "   Found " . $bouquets->count() . " bouquets\n";

    foreach ($bouquets as $bouquet) {
        $streamCount = is_array($bouquet->stream_ids) ? count($bouquet->stream_ids) : 0;
        echo "   - {$bouquet->name}: {$streamCount} streams\n";
    }

} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 6: Test Package model
echo "\n6. Testing Package model integration...\n";
try {
    $package = Package::first();
    if ($package) {
        echo "   ✓ Package found: {$package->name}\n";
        echo "   ✓ Stream count: " . $package->stream_count . "\n";
        echo "   ✓ Bouquet count: " . $package->bouquet_count . "\n";
    } else {
        echo "   ⊘ No packages found in database\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
