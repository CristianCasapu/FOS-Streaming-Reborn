<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Add is_default and priority columns to ufw_rules
     *
     * Adds support for default firewall rules and rule priority ordering
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('ufw_rules')) {
            // Add is_default column
            if (!$schema->hasColumn('ufw_rules', 'is_default')) {
                $schema->table('ufw_rules', function (Blueprint $table) {
                    $table->boolean('is_default')->default(false);
                    $table->index('is_default', 'idx_is_default');
                });
                echo "✓ Added is_default column to ufw_rules\n";
            }

            // Add priority column
            if (!$schema->hasColumn('ufw_rules', 'priority')) {
                $schema->table('ufw_rules', function (Blueprint $table) {
                    $table->unsignedInteger('priority')->default(100);
                    $table->index('priority', 'idx_priority');
                });
                echo "✓ Added priority column to ufw_rules\n";
            }

            // Insert default rules
            $defaultRules = [
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '22', 'protocol' => 'tcp', 'comment' => 'SSH Access', 'is_active' => 1, 'is_default' => 1, 'priority' => 10],
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '80', 'protocol' => 'tcp', 'comment' => 'HTTP Web Traffic', 'is_active' => 1, 'is_default' => 1, 'priority' => 20],
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '443', 'protocol' => 'tcp', 'comment' => 'HTTPS Web Traffic', 'is_active' => 1, 'is_default' => 1, 'priority' => 30],
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '7777', 'protocol' => 'tcp', 'comment' => 'FOS Admin Panel', 'is_active' => 1, 'is_default' => 1, 'priority' => 40],
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '8000', 'protocol' => 'tcp', 'comment' => 'Streaming Port', 'is_active' => 1, 'is_default' => 1, 'priority' => 50],
                ['action' => 'allow', 'from_ip' => null, 'to_port' => '1935', 'protocol' => 'tcp', 'comment' => 'RTMP Port', 'is_active' => 1, 'is_default' => 1, 'priority' => 60],
            ];

            foreach ($defaultRules as $rule) {
                // Check if rule already exists (by port and protocol)
                $exists = Capsule::table('ufw_rules')
                    ->where('to_port', $rule['to_port'])
                    ->where('protocol', $rule['protocol'])
                    ->exists();

                if (!$exists) {
                    Capsule::table('ufw_rules')->insert(array_merge($rule, [
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]));
                }
            }

            echo "✓ Inserted default UFW rules\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('ufw_rules')) {
            // Remove default rules
            Capsule::table('ufw_rules')->where('is_default', 1)->delete();

            // Remove columns
            $schema->table('ufw_rules', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('ufw_rules', 'priority')) {
                    $table->dropIndex('idx_priority');
                    $table->dropColumn('priority');
                }
                if ($schema->hasColumn('ufw_rules', 'is_default')) {
                    $table->dropIndex('idx_is_default');
                    $table->dropColumn('is_default');
                }
            });
        }
    }
};
