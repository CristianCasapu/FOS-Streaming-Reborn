<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Illuminate\Database\Capsule\Manager as Capsule;

class MigrateStatusCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration')
            ->setHelp(<<<'HELP'
The <info>migrate:status</info> command displays migration status:

  <info>php artisan migrate:status</info>

This shows which migrations have been run and which are pending.
HELP
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Migration Status</info>');
        $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
        $output->writeln('');

        try {
            // Check if migrations table exists
            if (!Capsule::schema()->hasTable('migrations')) {
                $output->writeln('<comment>No migrations table found. Run migrations first.</comment>');
                $output->writeln('');
                return Command::SUCCESS;
            }

            // Get all migration files
            $migrationsPath = dirname(dirname(dirname(__DIR__))) . '/database/migrations/laravel';
            $migrationFiles = glob($migrationsPath . '/*.php');
            sort($migrationFiles);

            // Get run migrations
            $ranMigrations = Capsule::table('migrations')
                ->orderBy('batch')
                ->orderBy('migration')
                ->get()
                ->keyBy('migration');

            // Prepare table data
            $tableData = [];
            $ranCount = 0;
            $pendingCount = 0;

            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');
                $ran = isset($ranMigrations[$migrationName]);

                if ($ran) {
                    $batch = $ranMigrations[$migrationName]->batch;
                    $tableData[] = [
                        '<info>✓ Ran</info>',
                        $migrationName,
                        "Batch {$batch}"
                    ];
                    $ranCount++;
                } else {
                    $tableData[] = [
                        '<comment>⊘ Pending</comment>',
                        $migrationName,
                        '-'
                    ];
                    $pendingCount++;
                }
            }

            // Display table
            $table = new Table($output);
            $table
                ->setHeaders(['Status', 'Migration', 'Batch'])
                ->setRows($tableData);
            $table->render();

            $output->writeln('');
            $output->writeln("<info>Total:</info> " . count($migrationFiles) . " migrations");
            $output->writeln("<info>Ran:</info> {$ranCount}");

            if ($pendingCount > 0) {
                $output->writeln("<comment>Pending:</comment> {$pendingCount}");
            } else {
                $output->writeln("<info>Pending:</info> 0 (all migrations are up to date)");
            }

            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Failed to get migration status:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }
}
