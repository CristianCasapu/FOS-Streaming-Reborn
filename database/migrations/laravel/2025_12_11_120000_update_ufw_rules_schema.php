<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Update ufw_rules table to match model expectations
     *
     * The model expects columns: port, protocol, action, direction, from_ip, to_ip,
     * interface, description, is_protected, is_default, enabled, priority, applied
     *
     * Current table has: action, from_ip, to_port, protocol, comment, is_active, is_default, priority
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('ufw_rules')) {
            echo "✗ ufw_rules table does not exist\n";
            return;
        }

        // Add 'enabled' column (model uses 'enabled', table has 'is_active')
        if (!$schema->hasColumn('ufw_rules', 'enabled')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->boolean('enabled')->default(true)->after('is_active');
            });

            // Copy values from is_active to enabled
            Capsule::table('ufw_rules')->update([
                'enabled' => Capsule::raw('is_active')
            ]);

            echo "✓ Added enabled column to ufw_rules\n";
        }

        // Add 'port' column (model uses 'port', table has 'to_port')
        if (!$schema->hasColumn('ufw_rules', 'port')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->integer('port')->nullable()->after('action');
            });

            // Copy values from to_port to port (converting string to int)
            Capsule::statement("UPDATE ufw_rules SET port = CAST(to_port AS UNSIGNED) WHERE to_port IS NOT NULL AND to_port REGEXP '^[0-9]+$'");

            echo "✓ Added port column to ufw_rules\n";
        }

        // Add 'direction' column
        if (!$schema->hasColumn('ufw_rules', 'direction')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->enum('direction', ['in', 'out', 'both'])->default('in')->after('action');
            });
            echo "✓ Added direction column to ufw_rules\n";
        }

        // Add 'to_ip' column
        if (!$schema->hasColumn('ufw_rules', 'to_ip')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->string('to_ip', 45)->nullable()->after('from_ip');
            });
            echo "✓ Added to_ip column to ufw_rules\n";
        }

        // Add 'interface' column
        if (!$schema->hasColumn('ufw_rules', 'interface')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->string('interface', 20)->nullable()->after('to_ip');
            });
            echo "✓ Added interface column to ufw_rules\n";
        }

        // Add 'description' column (model uses 'description', table has 'comment')
        if (!$schema->hasColumn('ufw_rules', 'description')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->text('description')->nullable()->after('interface');
            });

            // Copy values from comment to description
            Capsule::statement("UPDATE ufw_rules SET description = comment WHERE comment IS NOT NULL");

            echo "✓ Added description column to ufw_rules\n";
        }

        // Add 'is_protected' column
        if (!$schema->hasColumn('ufw_rules', 'is_protected')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->boolean('is_protected')->default(false)->after('description');
            });

            // Mark default rules as protected
            Capsule::table('ufw_rules')
                ->where('is_default', true)
                ->update(['is_protected' => true]);

            echo "✓ Added is_protected column to ufw_rules\n";
        }

        // Add 'applied' column
        if (!$schema->hasColumn('ufw_rules', 'applied')) {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->boolean('applied')->default(false)->after('priority');
            });
            echo "✓ Added applied column to ufw_rules\n";
        }

        // Add index on enabled column
        try {
            $schema->table('ufw_rules', function (Blueprint $table) {
                $table->index('enabled', 'idx_enabled');
            });
            echo "✓ Added index on enabled column\n";
        } catch (\Exception $e) {
            // Index might already exist
        }

        echo "✓ ufw_rules schema updated successfully\n";
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('ufw_rules')) {
            return;
        }

        $schema->table('ufw_rules', function (Blueprint $table) use ($schema) {
            // Drop index if exists
            try {
                $table->dropIndex('idx_enabled');
            } catch (\Exception $e) {
                // Index might not exist
            }

            // Drop columns in reverse order
            $columnsToDrop = ['applied', 'is_protected', 'description', 'interface', 'to_ip', 'direction', 'port', 'enabled'];

            foreach ($columnsToDrop as $column) {
                if ($schema->hasColumn('ufw_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
