<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

class MigrateCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Run the database migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run in production')
            ->addOption('pretend', 'p', InputOption::VALUE_NONE, 'Dump the SQL queries that would be run')
            ->setHelp(<<<'HELP'
The <info>migrate</info> command runs all pending database migrations:

  <info>php artisan migrate</info>

You can also use the --force option to run migrations in production:

  <info>php artisan migrate --force</info>

Use --pretend to see what SQL will be executed without running it:

  <info>php artisan migrate --pretend</info>
HELP
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $pretend = $input->getOption('pretend');

        // Production check
        if (env('APP_ENV') === 'production' && !$force) {
            $output->writeln('<error>⚠ Application is in production mode!</error>');
            $output->writeln('Use <comment>--force</comment> to run migrations in production.');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Running Migrations</info>');
        $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
        $output->writeln('');

        try {
            // Create migrations table if it doesn't exist
            if (!Capsule::schema()->hasTable('migrations')) {
                Capsule::schema()->create('migrations', function ($table) {
                    $table->increments('id');
                    $table->string('migration');
                    $table->integer('batch');
                });
                $output->writeln('✓ Created migrations table');
            }

            // Get list of migration files
            $migrationsPath = dirname(dirname(dirname(__DIR__))) . '/database/migrations/laravel';
            $migrationFiles = glob($migrationsPath . '/*.php');
            sort($migrationFiles);

            // Get already run migrations
            $ranMigrations = Capsule::table('migrations')->pluck('migration')->toArray();

            // Determine next batch number
            $nextBatch = Capsule::table('migrations')->max('batch') + 1;

            $migrationsRun = 0;

            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');

                // Skip if already run
                if (in_array($migrationName, $ranMigrations)) {
                    continue;
                }

                $output->writeln("→ <comment>Migrating:</comment> {$migrationName}");

                if ($pretend) {
                    $output->writeln('  <info>[PRETEND MODE]</info> Would run migration');
                    continue;
                }

                try {
                    // Include and run the migration
                    $migration = require $file;
                    $migration->up();

                    // Record migration
                    Capsule::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => $nextBatch
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
            if ($migrationsRun === 0) {
                $output->writeln('<info>✓ Nothing to migrate - all migrations have been run</info>');
            } else {
                $output->writeln("<info>✓ Successfully ran {$migrationsRun} migration(s)</info>");
            }
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Migration failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }
}
