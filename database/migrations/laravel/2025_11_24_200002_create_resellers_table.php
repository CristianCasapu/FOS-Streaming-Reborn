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
        \Illuminate\Database\Capsule\Manager::schema()->create('resellers', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('email', 255)->unique();
            $table->string('password', 255)->comment('Argon2id hashed password');
            $table->string('company_name', 255)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();

            // Financial
            $table->decimal('commission_rate', 5, 2)->default(10.00)->comment('Percentage commission on sales');
            $table->decimal('credit_balance', 12, 2)->default(0.00)->comment('Current available balance');
            $table->decimal('pending_balance', 12, 2)->default(0.00)->comment('Pending commissions (holding period)');
            $table->decimal('total_earned', 12, 2)->default(0.00)->comment('Total earned commissions');
            $table->decimal('total_withdrawn', 12, 2)->default(0.00)->comment('Total withdrawals');

            // Limits
            $table->unsignedInteger('max_subscribers')->default(100)->comment('Maximum subscribers allowed');
            $table->unsignedInteger('max_packages')->default(10)->comment('Maximum packages allowed');
            $table->boolean('can_create_packages')->default(false)->comment('Can create custom packages');
            $table->boolean('can_set_prices')->default(false)->comment('Can set custom prices');

            // White-label
            $table->string('custom_domain', 255)->nullable()->comment('Custom domain for portal');
            $table->string('logo_url', 500)->nullable();
            $table->string('favicon_url', 500)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->json('theme_settings')->nullable()->comment('Custom theme colors, etc.');

            // API Access
            $table->string('api_key', 64)->unique()->nullable();
            $table->string('api_secret', 64)->nullable();
            $table->boolean('api_enabled')->default(false);
            $table->unsignedInteger('api_rate_limit')->default(100)->comment('Requests per hour');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('status', 20)->default('active')->comment('active, suspended, banned');
            $table->text('notes')->nullable()->comment('Admin notes');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('username');
            $table->index('email');
            $table->index('is_active');
            $table->index('status');
            $table->index('api_key');
        });

        // Create reseller_subscribers pivot table
        \Illuminate\Database\Capsule\Manager::schema()->create('reseller_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->unsignedInteger('subscriber_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['reseller_id', 'subscriber_id']);
            $table->foreign('reseller_id')->references('id')->on('resellers')->onDelete('cascade');
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
        });

        // Create reseller_transactions table
        \Illuminate\Database\Capsule\Manager::schema()->create('reseller_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->enum('type', ['commission', 'withdrawal', 'adjustment', 'refund']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->unsignedInteger('subscription_id')->nullable()->comment('Related subscription');
            $table->string('reference', 255)->nullable()->comment('Transaction reference');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('completed')->comment('pending, completed, failed, cancelled');
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('resellers')->onDelete('cascade');
            $table->index(['reseller_id', 'created_at']);
            $table->index('type');
            $table->index('status');
        });

        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE resellers COMMENT = "Reseller accounts for multi-tenant SaaS"');
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE reseller_subscribers COMMENT = "Reseller to subscriber relationships"');
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE reseller_transactions COMMENT = "Financial transactions for resellers"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('reseller_transactions');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('reseller_subscribers');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('resellers');
    }
};
