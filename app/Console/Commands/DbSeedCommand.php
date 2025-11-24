<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

class DbSeedCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('db:seed')
            ->setDescription('Seed the database with records')
            ->addArgument('class', InputArgument::OPTIONAL, 'The class name of the seeder')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run in production')
            ->setHelp(<<<'HELP'
The <info>db:seed</info> command seeds the database:

  <info>php artisan db:seed</info>

You can specify a specific seeder class:

  <info>php artisan db:seed PackagesSeeder</info>

Force in production with --force:

  <info>php artisan db:seed --force</info>
HELP
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $input->getArgument('class');
        $force = $input->getOption('force');

        // Production check
        if (env('APP_ENV') === 'production' && !$force) {
            $output->writeln('<error>⚠ Application is in production mode!</error>');
            $output->writeln('Use <comment>--force</comment> to run seeding in production.');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Database Seeding</info>');
        $output->writeln('<comment>═══════════════════════════════════════════════════</comment>');
        $output->writeln('');

        try {
            $basePath = dirname(dirname(dirname(__DIR__)));

            // Mock command object for seeders
            $command = new class {
                public function info($message) {
                    // Output handled by main command
                }
            };

            // If specific class provided, run only that seeder
            if ($class) {
                return $this->runSeeder($class, $command, $output, $basePath);
            }

            // Otherwise run all seeders
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

            foreach ($seeders as $seederClass => $name) {
                $exitCode = $this->runSeeder($seederClass, $command, $output, $basePath);
                if ($exitCode !== Command::SUCCESS) {
                    return $exitCode;
                }
            }

            $output->writeln('');
            $output->writeln('<info>✓ All seeders completed successfully!</info>');
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>✗ Seeding failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('');
            return Command::FAILURE;
        }
    }

    /**
     * Run a single seeder
     */
    protected function runSeeder(string $seederClass, object $command, OutputInterface $output, string $basePath): int
    {
        // Remove .php extension if provided
        $seederClass = str_replace('.php', '', $seederClass);

        $output->writeln("→ <comment>Seeding:</comment> {$seederClass}");

        $seederFile = $basePath . '/database/seeders/' . $seederClass . '.php';

        if (!file_exists($seederFile)) {
            $output->writeln("  <error>✗ Failed:</error>  File not found: {$seederFile}");
            return Command::FAILURE;
        }

        try {
            require_once $seederFile;

            // Handle different namespaces
            $className = class_exists("Database\\Seeders\\{$seederClass}")
                ? "Database\\Seeders\\{$seederClass}"
                : $seederClass;

            if (!class_exists($className)) {
                $output->writeln("  <error>✗ Failed:</error>  Class not found: {$className}");
                return Command::FAILURE;
            }

            $seeder = new $className();
            $seeder->command = $command;
            $seeder->run();

            $output->writeln("  <info>✓ Seeded:</info>   {$seederClass}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("  <error>✗ Failed:</error>  " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
