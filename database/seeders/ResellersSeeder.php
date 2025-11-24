<?php

require_once __DIR__ . '/../../config.php';

use Reseller;

/**
 * Resellers Seeder
 *
 * Seeds demo reseller accounts for testing
 * Phase 1: Foundation - Multi-tenant System
 */
class ResellersSeeder
{
    /**
     * Run the seeder
     */
    public function run()
    {
        echo "Seeding Resellers...\n";

        if (env('APP_ENV') === 'production') {
            echo "⚠ Skipping reseller seeding in production environment\n";
            return;
        }

        $resellers = [
            [
                'username' => 'demo_reseller',
                'email' => 'reseller@demo.local',
                'password' => password_hash('reseller123', PASSWORD_BCRYPT),
                'company_name' => 'Demo IPTV Services',
                'contact_name' => 'John Reseller',
                'phone' => '+1-555-0100',
                'address' => '123 Demo Street, Demo City',
                'commission_rate' => 15.00,
                'max_subscribers' => 100,
                'max_packages' => 5,
                'max_bouquets' => 10,
                'status' => 'active',
                'credit_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'api_enabled' => true,
                'api_rate_limit' => 1000,
                'notes' => 'Demo reseller account for testing',
            ],
            [
                'username' => 'premium_reseller',
                'email' => 'premium@demo.local',
                'password' => password_hash('premium123', PASSWORD_BCRYPT),
                'company_name' => 'Premium Streaming Ltd',
                'contact_name' => 'Jane Premium',
                'phone' => '+1-555-0200',
                'address' => '456 Premium Ave, Premium City',
                'commission_rate' => 20.00,
                'max_subscribers' => 500,
                'max_packages' => 10,
                'max_bouquets' => 20,
                'status' => 'active',
                'credit_balance' => 1500.00,
                'pending_balance' => 250.00,
                'total_earned' => 5000.00,
                'total_withdrawn' => 3500.00,
                'api_enabled' => true,
                'api_rate_limit' => 5000,
                'custom_domain' => 'premium-iptv.demo',
                'brand_name' => 'Premium IPTV',
                'theme_settings' => json_encode([
                    'primary_color' => '#1E40AF',
                    'secondary_color' => '#10B981',
                    'logo_url' => 'https://example.com/logo.png',
                ]),
                'notes' => 'Premium tier reseller with white-label features',
            ],
            [
                'username' => 'starter_reseller',
                'email' => 'starter@demo.local',
                'password' => password_hash('starter123', PASSWORD_BCRYPT),
                'company_name' => 'Starter IPTV',
                'contact_name' => 'Bob Starter',
                'phone' => '+1-555-0300',
                'commission_rate' => 10.00,
                'max_subscribers' => 50,
                'max_packages' => 3,
                'max_bouquets' => 5,
                'status' => 'active',
                'credit_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'api_enabled' => false,
                'api_rate_limit' => 100,
                'notes' => 'Starter tier reseller account',
            ],
        ];

        foreach ($resellers as $resellerData) {
            // Check if already exists
            if (Reseller::where('username', $resellerData['username'])->exists()) {
                echo "  ⚠ Reseller {$resellerData['username']} already exists\n";
                continue;
            }

            $reseller = Reseller::create($resellerData);

            // Generate API credentials
            if ($resellerData['api_enabled']) {
                $credentials = $reseller->generateApiKey();
                echo "  ✓ Created reseller: {$reseller->username}\n";
                echo "    API Key: {$credentials['api_key']}\n";
                echo "    API Secret: {$credentials['api_secret']}\n";
            } else {
                echo "  ✓ Created reseller: {$reseller->username} (API disabled)\n";
            }

            // Create some demo transactions for premium reseller
            if ($reseller->username === 'premium_reseller') {
                $this->createDemoTransactions($reseller);
            }
        }

        echo "\n  Demo Reseller Credentials:\n";
        echo "    Demo:     demo_reseller / reseller123\n";
        echo "    Premium:  premium_reseller / premium123\n";
        echo "    Starter:  starter_reseller / starter123\n";

        echo "\n✓ Resellers seeded successfully\n";
    }

    /**
     * Create demo transactions for testing
     */
    private function createDemoTransactions($reseller)
    {
        echo "    Creating demo transactions...\n";

        $transactions = [
            [
                'type' => 'commission',
                'amount' => 150.00,
                'balance_before' => 0.00,
                'balance_after' => 150.00,
                'description' => 'Commission from subscription #1',
                'status' => 'completed',
                'created_at' => now()->subDays(30),
            ],
            [
                'type' => 'commission',
                'amount' => 200.00,
                'balance_before' => 150.00,
                'balance_after' => 350.00,
                'description' => 'Commission from subscription #2',
                'status' => 'completed',
                'created_at' => now()->subDays(25),
            ],
            [
                'type' => 'withdrawal',
                'amount' => -100.00,
                'balance_before' => 350.00,
                'balance_after' => 250.00,
                'description' => 'Withdrawal to bank account',
                'status' => 'completed',
                'created_at' => now()->subDays(20),
            ],
            [
                'type' => 'commission',
                'amount' => 300.00,
                'balance_before' => 250.00,
                'balance_after' => 550.00,
                'description' => 'Monthly commission batch',
                'status' => 'completed',
                'created_at' => now()->subDays(15),
            ],
            [
                'type' => 'commission',
                'amount' => 250.00,
                'balance_before' => 550.00,
                'balance_after' => 800.00,
                'description' => 'Commission from new subscribers',
                'status' => 'pending',
                'created_at' => now()->subDays(5),
            ],
        ];

        foreach ($transactions as $txData) {
            \ResellerTransaction::create(array_merge($txData, [
                'reseller_id' => $reseller->id,
            ]));
        }

        echo "    ✓ Created 5 demo transactions\n";
    }

    /**
     * Rollback the seeder
     */
    public function rollback()
    {
        echo "Rolling back Resellers...\n";

        $demoResellers = ['demo_reseller', 'premium_reseller', 'starter_reseller'];

        // Delete transactions first (foreign key constraint)
        $resellerIds = Reseller::whereIn('username', $demoResellers)->pluck('id');
        \ResellerTransaction::whereIn('reseller_id', $resellerIds)->delete();

        // Delete resellers
        Reseller::whereIn('username', $demoResellers)->delete();

        echo "✓ Resellers rolled back\n";
    }
}

// Run seeder if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $seeder = new ResellersSeeder();

    if (isset($argv[1]) && $argv[1] === '--rollback') {
        $seeder->rollback();
    } else {
        $seeder->run();
    }
}
