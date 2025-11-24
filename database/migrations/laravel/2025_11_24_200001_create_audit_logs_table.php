<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations - Phase 1 Foundation
     */
    public function up(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable()->comment('Admin ID who performed action');
            $table->string('user_type', 50)->default('admin')->comment('admin, subscriber, reseller, system');
            $table->string('action', 255)->comment('Action performed (create, update, delete, login, etc.)');
            $table->string('entity_type', 50)->comment('Model type (Stream, Subscriber, etc.)');
            $table->unsignedInteger('entity_id')->nullable()->comment('ID of the affected entity');
            $table->json('old_values')->nullable()->comment('Values before change');
            $table->json('new_values')->nullable()->comment('Values after change');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('method', 10)->nullable()->comment('HTTP method: GET, POST, etc.');
            $table->text('url')->nullable()->comment('Request URL');
            $table->string('status', 20)->default('success')->comment('success, failed, warning');
            $table->text('description')->nullable()->comment('Human-readable description');
            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index('user_id');
            $table->index('user_type');
            $table->index('action');
            $table->index('entity_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });

        // Add comment to table
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE audit_logs COMMENT = "Complete audit trail for all system actions"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('audit_logs');
    }
};
