<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Illuminate\Database\Capsule\Manager as Capsule;

class DbWipeCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('db:wipe')
            ->setDescription('Drop all tables, views, and types')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run in production')
            ->setHelp(<<<'HELP'
The <info>db:wipe</info> command drops all database tables:

  <info>php artisan db:wipe</info>

Force in production with --force:

  <info>php artisan db:wipe --force</info>

<comment>Warning:</comment> This will permanently delete all data!
HELP
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');

        // Production check
        if (env('APP_ENV') === 'production' && !$force) {
            $output->writeln('<error>⚠ Application is in production mode!</error>');
            $output->writeln('Use <comment>--force</comment> to run in production.');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<error>╔════════════════════════════════════════════════════════════╗</error>');
        $output->writeln('<error>║  ⚠  WARNING: This will DELETE ALL DATABASE TABLES!        ║</error>');
        $output->writeln('<error>╚════════════════════════════════════════════════════════════╝</error>');
        $output->writeln('');

        // Confirmation
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Are you absolutely sure? Type "DELETE" to confirm: ',
                false
            );

            $response = $helper->ask($input, $output, $question);
            if ($response !== 'DELETE') {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $output->writeln('');
        $output->writeln('<info>Wiping Database</info>');
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

            $droppedCount = 0;

            // Drop each table
            foreach ($tables as $table) {
                $tableName = $table->TABLE_NAME;
                Capsule::statement("DROP TABLE IF EXISTS `{$tableName}`");
                $output->writeln("  <comment>✓ Dropped:</comment> {$tableName}");
                $droppedCount++;
            }

            // Re-enable foreign key checks
            Capsule::statement('SET FOREIGN_KEY_CHECKS=1');

            $output->writeln('');
            $output->writeln("<info>✓ Successfully dropped {$droppedCount} table(s)</info>");
            $output->writeln('');

            $output->writeln('<comment>Next steps:</comment>');
            $output->writeln('  Run: <info>php artisan migrate</info> to recreate tables');
            $output->writeln('  Run: <info>php artisan db:seed</info> to seed data');
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Wipe failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }
}
