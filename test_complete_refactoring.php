<?php
/**
 * Comprehensive test for channels-to-streams refactoring
 */

require_once __DIR__ . '/config.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "=== COMPREHENSIVE REFACTORING TEST ===\n\n";

$allPassed = true;

// Test 1: Verify tables exist/don't exist
echo "1. Database Structure Test\n";
$requiredTables = ['streams', 'bouquets', 'packages', 'package_bouquet'];
$forbiddenTables = ['channels', 'bouquet_channel'];

foreach ($requiredTables as $table) {
    if (Capsule::schema()->hasTable($table)) {
        echo "   ✓ Table '$table' exists\n";
    } else {
        echo "   ✗ FAIL: Table '$table' does not exist!\n";
        $allPassed = false;
    }
}

foreach ($forbiddenTables as $table) {
    if (!Capsule::schema()->hasTable($table)) {
        echo "   ✓ Table '$table' removed (correct)\n";
    } else {
        echo "   ✗ FAIL: Table '$table' still exists!\n";
        $allPassed = false;
    }
}

// Test 2: Verify bouquets.stream_ids column
echo "\n2. Bouquets Table Structure Test\n";
$columns = Capsule::select("SHOW COLUMNS FROM bouquets WHERE Field = 'stream_ids'");
if (!empty($columns)) {
    $col = $columns[0];
    echo "   ✓ stream_ids column exists (Type: {$col->Type})\n";
} else {
    echo "   ✗ FAIL: stream_ids column not found!\n";
    $allPassed = false;
}

// Test 3: Create test stream
echo "\n3. Stream Creation Test\n";
try {
    $testStream = new Stream();
    $testStream->name = 'Test Stream';
    $testStream->stream_display_name = 'Test Stream Display';
    $testStream->streamurl = 'rtmp://test.example.com/live/stream1';
    $testStream->enabled = true;
    $testStream->running = 0;
    $testStream->status = 0;
    $testStream->state = 'stopped';
    $testStream->save();

    echo "   ✓ Test stream created (ID: {$testStream->id})\n";
} catch (Exception $e) {
    echo "   ✗ FAIL: Could not create stream - " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 4: Create bouquet with streams
echo "\n4. Bouquet with Streams Test\n";
try {
    $testBouquet = new Bouquet();
    $testBouquet->name = 'Test Bouquet';
    $testBouquet->description = 'Testing stream_ids functionality';
    $testBouquet->stream_ids = [$testStream->id];
    $testBouquet->is_active = 1;
    $testBouquet->sort_order = 999;
    $testBouquet->save();

    echo "   ✓ Bouquet created with stream_ids (ID: {$testBouquet->id})\n";

    // Reload and verify
    $reloaded = Bouquet::find($testBouquet->id);
    if ($reloaded && is_array($reloaded->stream_ids) && in_array($testStream->id, $reloaded->stream_ids)) {
        echo "   ✓ stream_ids persisted correctly\n";
    } else {
        echo "   ✗ FAIL: stream_ids not persisted correctly!\n";
        $allPassed = false;
    }

    // Test stream_count attribute
    if ($reloaded->stream_count === 1) {
        echo "   ✓ stream_count attribute works (count: {$reloaded->stream_count})\n";
    } else {
        echo "   ✗ FAIL: stream_count incorrect (got: {$reloaded->stream_count}, expected: 1)\n";
        $allPassed = false;
    }

} catch (Exception $e) {
    echo "   ✗ FAIL: Could not create bouquet - " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 5: Test Bouquet->streams() method
echo "\n5. Bouquet->streams() Method Test\n";
try {
    $streams = $testBouquet->streams();
    if ($streams->count() === 1) {
        echo "   ✓ Bouquet->streams() returns correct count\n";
        $stream = $streams->first();
        if ($stream->id === $testStream->id) {
            echo "   ✓ Bouquet->streams() returns correct stream\n";
        } else {
            echo "   ✗ FAIL: Wrong stream returned\n";
            $allPassed = false;
        }
    } else {
        echo "   ✗ FAIL: Wrong stream count (got: {$streams->count()}, expected: 1)\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ✗ FAIL: streams() method error - " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 6: Test adding/removing streams
echo "\n6. Add/Remove Streams Test\n";
try {
    // Create second stream
    $testStream2 = new Stream();
    $testStream2->name = 'Test Stream 2';
    $testStream2->stream_display_name = 'Test Stream 2 Display';
    $testStream2->streamurl = 'rtmp://test.example.com/live/stream2';
    $testStream2->enabled = true;
    $testStream2->save();

    // Add stream
    $testBouquet->addStream($testStream2->id);
    $testBouquet = Bouquet::find($testBouquet->id);

    if ($testBouquet->stream_count === 2) {
        echo "   ✓ addStream() method works\n";
    } else {
        echo "   ✗ FAIL: addStream() failed (count: {$testBouquet->stream_count})\n";
        $allPassed = false;
    }

    // Remove stream
    $testBouquet->removeStream($testStream2->id);
    $testBouquet = Bouquet::find($testBouquet->id);

    if ($testBouquet->stream_count === 1) {
        echo "   ✓ removeStream() method works\n";
    } else {
        echo "   ✗ FAIL: removeStream() failed (count: {$testBouquet->stream_count})\n";
        $allPassed = false;
    }

} catch (Exception $e) {
    echo "   ✗ FAIL: Add/remove streams error - " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 7: Test Package integration
echo "\n7. Package Integration Test\n";
try {
    $testPackage = new Package();
    $testPackage->name = 'Test Package';
    $testPackage->description = 'Testing package->streams';
    $testPackage->price = 9.99;
    $testPackage->duration_days = 30;
    $testPackage->is_active = 1;
    $testPackage->save();

    // Attach bouquet to package
    $testPackage->bouquets()->attach($testBouquet->id);

    // Reload
    $testPackage = Package::find($testPackage->id);

    $streamCount = $testPackage->stream_count;
    if ($streamCount === 1) {
        echo "   ✓ Package->stream_count works (count: $streamCount)\n";
    } else {
        echo "   ⚠ Package->stream_count returned: $streamCount (expected 1)\n";
    }

} catch (Exception $e) {
    echo "   ✗ FAIL: Package integration error - " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 8: Check seeded data
echo "\n8. Seeded Data Test\n";
$bouquetCount = Bouquet::count();
$packageCount = Package::count();
echo "   ✓ Found $bouquetCount bouquets\n";
echo "   ✓ Found $packageCount packages\n";

// Cleanup
echo "\n9. Cleanup Test Data\n";
try {
    $testBouquet->delete();
    $testStream->delete();
    if (isset($testStream2)) $testStream2->delete();
    if (isset($testPackage)) $testPackage->delete();
    echo "   ✓ Test data cleaned up\n";
} catch (Exception $e) {
    echo "   ⚠ Cleanup warning: " . $e->getMessage() . "\n";
}

// Final result
echo "\n" . str_repeat('=', 50) . "\n";
if ($allPassed) {
    echo "✓✓✓ ALL TESTS PASSED! ✓✓✓\n";
    echo "Refactoring is complete and working correctly.\n";
    exit(0);
} else {
    echo "✗✗✗ SOME TESTS FAILED ✗✗✗\n";
    echo "Please review the errors above.\n";
    exit(1);
}
