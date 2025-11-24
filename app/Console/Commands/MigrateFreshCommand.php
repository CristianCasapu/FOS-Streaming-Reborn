<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Illuminate\Database\Capsule\Manager as Capsule;

class MigrateFreshCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('seed', 's', InputOption::VALUE_NONE, 'Seed the database after migrating')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run in production')
            ->setHelp(<<<'HELP'
The <info>migrate:fresh</info> command drops all tables and re-runs all migrations:

  <info>php artisan migrate:fresh</info>

You can seed the database after migrating with --seed:

  <info>php artisan migrate:fresh --seed</info>

Force in production with --force:

  <info>php artisan migrate:fresh --force</info>
HELP
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seed = $input->getOption('seed');
        $force = $input->getOption('force');

        // Production check
        if (env('APP_ENV') === 'production' && !$force) {
            $output->writeln('<error>⚠ Application is in production mode!</error>');
            $output->writeln('Use <comment>--force</comment> to run in production.');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<error>╔════════════════════════════════════════════════════════════╗</error>');
        $output->writeln('<error>║  ⚠  WARNING: This will DELETE ALL DATA!                   ║</error>');
        $output->writeln('<error>╚════════════════════════════════════════════════════════════╝</error>');
        $output->writeln('');

        // Confirmation
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Are you sure you want to drop all tables? (yes/no) [no]: ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $output->writeln('');
        $output->writeln('<info>Dropping All Tables</info>');
        $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
        $output->writeln('');

        try {
            // Disable foreign key checks
            Capsule::statement('SET FOREIGN_KEY_CHECKS=0');

            // Get all tables
            $dbName = env('DB_DATABASE', 'fos_streaming');
            $tables = Capsule::select("
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                AND TABLE_TYPE = 'BASE TABLE'
            ", [$dbName]);

            // Drop each table
            foreach ($tables as $table) {
                $tableName = $table->TABLE_NAME;
                Capsule::statement("DROP TABLE IF EXISTS `{$tableName}`");
                $output->writeln("  <comment>✓ Dropped:</comment> {$tableName}");
            }

            // Re-enable foreign key checks
            Capsule::statement('SET FOREIGN_KEY_CHECKS=1');

            $output->writeln('');
            $output->writeln('<info>✓ All tables dropped successfully</info>');
            $output->writeln('');

            // Run migrations
            $output->writeln('<info>Running Fresh Migrations</info>');
            $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
            $output->writeln('');

            // Create migrations table
            Capsule::schema()->create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });

            // Get and run all migrations
            $migrationsPath = dirname(dirname(dirname(__DIR__))) . '/database/migrations/laravel';
            $migrationFiles = glob($migrationsPath . '/*.php');
            sort($migrationFiles);

            $migrationsRun = 0;
            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');
                $output->writeln("→ <comment>Migrating:</comment> {$migrationName}");

                try {
                    $migration = require $file;
                    $migration->up();

                    Capsule::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => 1
                    ]);

                    $output->writeln("  <info>✓ Migrated:</info>  {$migrationName}");
                    $migrationsRun++;
                } catch (\Exception $e) {
                    $output->writeln("  <error>✗ Failed:</error>   {$migrationName}");
                    $output->writeln("  <error>Error:</error> " . $e->getMessage());
                    return Command::FAILURE;
                }
            }

            $output->writeln('');
            $output->writeln("<info>✓ Successfully ran {$migrationsRun} migration(s)</info>");
            $output->writeln('');

            // Run seeders if requested
            if ($seed) {
                $output->writeln('<info>Running Database Seeders</info>');
                $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
                $output->writeln('');

                $exitCode = $this->runSeeders($output);
                if ($exitCode !== Command::SUCCESS) {
                    return $exitCode;
                }
            }

            $output->writeln('');
            $output->writeln('<info>✓ Database refreshed successfully!</info>');
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Operation failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }

    /**
     * Run database seeders
     */
    protected function runSeeders(OutputInterface $output): int
    {
        $basePath = dirname(dirname(dirname(__DIR__)));

        // Mock command object for seeders
        $command = new class {
            public function info($message) {
                // Output handled by main command
            }
        };

        $seeders = [
            'PackagesSeeder' => 'Packages',
            'BouquetsSeeder' => 'Bouquets',
            'PackageBouquetSeeder' => 'Package-Bouquet relationships',
            'PM2WorkersSeeder' => 'PM2 Workers',
            'SettingsSeeder' => 'Settings',
            'AdminRolesSeeder' => 'Admin Roles',
            'ResellersSeeder' => 'Resellers',
            'V2RayServersSeeder' => 'V2Ray Servers',
        ];

        try {
            foreach ($seeders as $seederClass => $name) {
                $output->writeln("→ <comment>Seeding:</comment> {$name}");

                $seederFile = $basePath . '/database/seeders/' . $seederClass . '.php';
                if (!file_exists($seederFile)) {
                    $output->writeln("  <comment>⊘ Skipped:</comment> File not found");
                    continue;
                }

                require_once $seederFile;

                // Handle different namespaces
                $className = class_exists("Database\\Seeders\\{$seederClass}")
                    ? "Database\\Seeders\\{$seederClass}"
                    : $seederClass;

                if (!class_exists($className)) {
                    $output->writeln("  <error>✗ Failed:</error>  Class not found");
                    continue;
                }

                $seeder = new $className();
                $seeder->command = $command;
                $seeder->run();

                $output->writeln("  <info>✓ Seeded:</info>   {$name}");
            }

            $output->writeln('');
            $output->writeln('<info>✓ All seeders completed successfully</info>');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Seeding failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
