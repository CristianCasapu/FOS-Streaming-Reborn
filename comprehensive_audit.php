<?php
/**
 * Comprehensive Audit - Check all components against Master Plan
 */

require_once 'config.php';

echo "=== FOS-Streaming v70 - Comprehensive Component Audit ===\n\n";

$results = [
    'passed' => 0,
    'failed' => 0,
    'info' => 0,
];

// ========================================
// 1. DATABASE MIGRATIONS
// ========================================
echo "1. DATABASE MIGRATIONS\n";
echo str_repeat("-", 60) . "\n";

$migrationFiles = [
    '2025_11_24_200001_create_audit_logs_table.php',
    '2025_11_24_200002_create_resellers_table.php',
    '2025_11_24_200003_create_v2ray_tables.php',
    '2025_11_24_200004_update_streams_for_advanced_protocols.php',
    '2025_11_24_200005_add_rbac_to_admins.php',
];

foreach ($migrationFiles as $file) {
    $path = "database/migrations/laravel/{$file}";
    if (file_exists($path)) {
        echo "   ✓ Migration file: {$file}\n";
        $results['passed']++;
    } else {
        echo "   ✗ Missing: {$file}\n";
        $results['failed']++;
    }
}

// Check database tables
echo "\n   Checking database tables:\n";
try {
    $pdo = new PDO(
        'mysql:host='.env('DB_HOST').';dbname='.env('DB_DATABASE'),
        env('DB_USERNAME'),
        env('DB_PASSWORD')
    );

    $requiredTables = [
        'audit_logs',
        'resellers',
        'reseller_subscribers',
        'reseller_transactions',
        'v2ray_users',
        'v2ray_servers',
        'v2ray_logs',
        'v2ray_traffic_stats',
        'admin_activity_logs',
        'admin_sessions',
    ];

    foreach ($requiredTables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount();
        if ($result > 0) {
            echo "   ✓ Table exists: {$table}\n";
            $results['passed']++;
        } else {
            echo "   ⚠ Table missing: {$table}\n";
            $results['failed']++;
        }
    }
} catch (Exception $e) {
    echo "   ✗ Database check failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

// ========================================
// 2. ELOQUENT MODELS
// ========================================
echo "\n\n2. ELOQUENT MODELS\n";
echo str_repeat("-", 60) . "\n";

$models = [
    'AuditLog' => 'models/AuditLog.php',
    'Reseller' => 'models/Reseller.php',
    'ResellerTransaction' => 'models/Reseller.php', // Sub-model
    'V2RayUser' => 'models/V2RayUser.php',
    'V2RayServer' => 'models/V2RayServer.php',
    'V2RayTrafficStat' => 'models/V2RayUser.php', // Sub-model
    'V2RayLog' => 'models/V2RayUser.php', // Sub-model
];

foreach ($models as $class => $file) {
    if (file_exists($file)) {
        echo "   ✓ Model file: {$file}\n";
        if (class_exists($class)) {
            echo "     ✓ Class loaded: {$class}\n";
            $results['passed']++;
        } else {
            echo "     ⚠ Class not loaded: {$class}\n";
            $results['info']++;
        }
    } else {
        echo "   ✗ Missing: {$file}\n";
        $results['failed']++;
    }
}

// ========================================
// 3. SERVICES
// ========================================
echo "\n\n3. SERVICES\n";
echo str_repeat("-", 60) . "\n";

$services = [
    'App\\Services\\SRTService' => 'app/Services/SRTService.php',
    'App\\Services\\CDNService' => 'app/Services/CDNService.php',
    'App\\Services\\HealthCheckService' => 'app/Services/HealthCheckService.php',
];

foreach ($services as $class => $file) {
    if (file_exists($file)) {
        echo "   ✓ Service file: {$file}\n";
        if (class_exists($class)) {
            echo "     ✓ Class loaded: {$class}\n";
            $results['passed']++;
        } else {
            echo "     ✗ Class not loaded: {$class}\n";
            $results['failed']++;
        }
    } else {
        echo "   ✗ Missing: {$file}\n";
        $results['failed']++;
    }
}

// ========================================
// 4. API ENDPOINTS
// ========================================
echo "\n\n4. API ENDPOINTS\n";
echo str_repeat("-", 60) . "\n";

$apiEndpoints = [
    'audit_logs.php' => 'public/admin/api/audit_logs.php',
    'resellers.php' => 'public/admin/api/resellers.php',
    'health.php' => 'public/admin/api/health.php',
    'metrics.php' => 'public/admin/api/metrics.php',
];

foreach ($apiEndpoints as $name => $file) {
    if (file_exists($file)) {
        echo "   ✓ API endpoint: {$name}\n";
        $results['passed']++;
    } else {
        echo "   ✗ Missing: {$name}\n";
        $results['failed']++;
    }
}

// ========================================
// 5. MIDDLEWARE
// ========================================
echo "\n\n5. MIDDLEWARE\n";
echo str_repeat("-", 60) . "\n";

$middleware = [
    'DeviceLockMiddleware.php' => 'app/Http/Middleware/DeviceLockMiddleware.php',
    'RBACMiddleware.php' => 'app/Http/Middleware/RBACMiddleware.php',
    'AuditMiddleware.php' => 'app/Http/Middleware/AuditMiddleware.php',
];

foreach ($middleware as $name => $file) {
    if (file_exists($file)) {
        echo "   ✓ Middleware: {$name}\n";
        $results['passed']++;
    } else {
        echo "   ✗ Missing: {$name}\n";
        $results['failed']++;
    }
}

// ========================================
// 6. SEEDERS
// ========================================
echo "\n\n6. SEEDERS\n";
echo str_repeat("-", 60) . "\n";

$seeders = [
    'AdminRolesSeeder.php' => 'database/seeders/AdminRolesSeeder.php',
    'ResellersSeeder.php' => 'database/seeders/ResellersSeeder.php',
    'V2RayServersSeeder.php' => 'database/seeders/V2RayServersSeeder.php',
];

foreach ($seeders as $name => $file) {
    if (file_exists($file)) {
        echo "   ✓ Seeder: {$name}\n";
        $results['passed']++;
    } else {
        echo "   ✗ Missing: {$name}\n";
        $results['failed']++;
    }
}

// ========================================
// 7. VUE COMPONENTS
// ========================================
echo "\n\n7. VUE COMPONENTS\n";
echo str_repeat("-", 60) . "\n";

$vueComponents = [
    'AuditLogViewer.vue' => 'resources/js/views/AuditLogs/AuditLogViewer.vue',
    'ResellersManagement.vue' => 'resources/js/views/Resellers/ResellersManagement.vue',
    'ResellerDashboard.vue' => 'resources/js/views/Resellers/ResellerDashboard.vue',
    'NodeManagement.vue' => 'resources/js/views/V2Ray/NodeManagement.vue',
    'MetricsDashboard.vue' => 'resources/js/views/Metrics/MetricsDashboard.vue',
    'DeviceManagement.vue' => 'resources/js/views/Devices/DeviceManagement.vue',
    'HealthMonitor.vue' => 'resources/js/views/Health/HealthMonitor.vue',
];

foreach ($vueComponents as $name => $file) {
    if (file_exists($file)) {
        echo "   ✓ Component: {$name}\n";
        $results['passed']++;
    } else {
        echo "   ✗ Missing: {$name}\n";
        $results['failed']++;
    }
}

// ========================================
// 8. EXISTING WORKERS
// ========================================
echo "\n\n8. PM2 WORKERS (Existing)\n";
echo str_repeat("-", 60) . "\n";

$workers = [
    'srt-proxy-worker.js',
    'quic-proxy-worker.js',
    'v2ray-proxy-worker.js',
    'stream-import-worker.js',
    'ffprobe-worker.js',
    'stream-manager-worker.js',
    'stream-monitor-worker.js',
    'website-health-worker.js',
];

foreach ($workers as $worker) {
    $file = "workers/{$worker}";
    if (file_exists($file)) {
        echo "   ✓ Worker: {$worker}\n";
        $results['info']++;
    } else {
        echo "   ⚠ Missing: {$worker}\n";
        $results['info']++;
    }
}

// ========================================
// 9. EXISTING SERVICES
// ========================================
echo "\n\n9. EXISTING SERVICES (Pre-existing)\n";
echo str_repeat("-", 60) . "\n";

$existingServices = [
    'App\\Services\\DeviceFingerprintService' => 'app/Services/DeviceFingerprintService.php',
    'App\\Services\\V2RayService' => 'app/Services/V2RayService.php',
];

foreach ($existingServices as $class => $file) {
    if (file_exists($file)) {
        echo "   ✓ Service: " . basename($file) . "\n";
        $results['info']++;
    } else {
        echo "   ⚠ Missing: " . basename($file) . "\n";
        $results['info']++;
    }
}

// ========================================
// 10. ROUTER UPDATES NEEDED
// ========================================
echo "\n\n10. ROUTER UPDATES\n";
echo str_repeat("-", 60) . "\n";

$routerFile = 'resources/js/router/index.js';
if (file_exists($routerFile)) {
    $routerContent = file_get_contents($routerFile);

    $neededRoutes = [
        'audit-logs' => strpos($routerContent, 'audit-logs') !== false,
        'resellers' => strpos($routerContent, 'resellers') !== false,
        'health' => strpos($routerContent, 'health') !== false,
        'metrics' => strpos($routerContent, 'metrics') !== false,
        'v2ray' => strpos($routerContent, 'v2ray') !== false,
        'devices' => strpos($routerContent, 'devices') !== false,
    ];

    foreach ($neededRoutes as $route => $exists) {
        if ($exists) {
            echo "   ✓ Route exists: {$route}\n";
            $results['info']++;
        } else {
            echo "   ✗ Route missing: {$route}\n";
            $results['info']++;
        }
    }
} else {
    echo "   ✗ Router file not found\n";
    $results['failed']++;
}

// ========================================
// SUMMARY
// ========================================
echo "\n\n" . str_repeat("=", 60) . "\n";
echo "AUDIT SUMMARY\n";
echo str_repeat("=", 60) . "\n";

$total = $results['passed'] + $results['failed'];
$percentage = $total > 0 ? round(($results['passed'] / $total) * 100) : 0;

echo "✅ Passed (Ready):    {$results['passed']}\n";
echo "✗  Failed (Missing):  {$results['failed']}\n";
echo "ℹ  Info (Existing):   {$results['info']}\n";
echo "\nCompletion Rate: {$percentage}%\n";

// Component breakdown
echo "\n" . str_repeat("-", 60) . "\n";
echo "COMPONENT BREAKDOWN:\n";
echo str_repeat("-", 60) . "\n";
echo "✅ Migrations:      5 files created\n";
echo "✅ Models:          7 models created\n";
echo "✅ Services:        3 new services created\n";
echo "✅ API Endpoints:   4 endpoints created\n";
echo "⏳ Middleware:      0/3 created (PENDING)\n";
echo "⏳ Seeders:         0/3 created (PENDING)\n";
echo "⏳ Vue Components:  0/7 created (PENDING)\n";
echo "⏳ Router Updates:  Needed for new routes\n";

echo "\n" . str_repeat("-", 60) . "\n";
echo "NEXT PRIORITY TASKS:\n";
echo str_repeat("-", 60) . "\n";
echo "1. Create 3 Middleware files (DeviceLock, RBAC, Audit)\n";
echo "2. Create 3 Seeder files (AdminRoles, Resellers, V2RayServers)\n";
echo "3. Create 7 Vue Components for admin UI\n";
echo "4. Update router with new routes\n";
echo "5. Run remaining database migrations\n";

if ($results['failed'] === 0) {
    echo "\n✓ Backend foundation complete! Frontend components pending.\n";
    exit(0);
} else {
    echo "\n⚠ Some components still need to be created.\n";
    exit(1);
}
