<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Migration: Update PM2 Workers Script Extension
 *
 * Updates worker script paths from .js to .cjs extension.
 *
 * Background: Node.js projects with "type": "module" in package.json require
 * CommonJS files to use .cjs extension. All worker files were renamed to .cjs
 * but the database records still had .js paths.
 */
return new class
{
    public function up(): void
    {
        // Update all worker scripts from .js to .cjs
        Capsule::table('pm2_workers')
            ->where('script', 'LIKE', '%.js')
            ->update([
                'script' => Capsule::raw("REPLACE(script, '.js', '.cjs')"),
                'updated_at' => now()
            ]);
    }

    public function down(): void
    {
        // Revert back to .js extension
        Capsule::table('pm2_workers')
            ->where('script', 'LIKE', '%.cjs')
            ->update([
                'script' => Capsule::raw("REPLACE(script, '.cjs', '.js')"),
                'updated_at' => now()
            ]);
    }
};
