<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('serve')
            ->setDescription('Serve the FOS Streaming application on the PHP development server')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve on', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve on', 8000)
            ->addOption('tries', null, InputOption::VALUE_OPTIONAL, 'The max number of ports to try', 10);
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $tries = $input->getOption('tries');

        $base = $port;

        // Try to find an available port
        while ($tries > 0) {
            if ($this->isPortAvailable($host, $port)) {
                break;
            }

            $port++;
            $tries--;
        }

        if ($tries === 0) {
            $output->writeln('<error>Unable to find an available port. Please try a different port range.</error>');
            return Command::FAILURE;
        }

        $publicPath = $this->getPublicPath();

        $output->writeln("<info>FOS Streaming development server started:</info> <http://{$host}:{$port}>");

        if ($port !== $base) {
            $output->writeln("<comment>Port {$base} was busy, using port {$port} instead.</comment>");
        }

        $output->writeln('Press Ctrl+C to stop the server');
        $output->writeln('');

        // Set environment variables for the server
        putenv("APP_ENV=" . ($_ENV['APP_ENV'] ?? 'local'));
        putenv("APP_DEBUG=" . ($_ENV['APP_DEBUG'] ?? 'true'));

        // Get router script path
        $basePath = dirname(dirname(dirname(__DIR__)));
        $routerPath = $basePath . '/server.php';

        // Start the PHP built-in server with router script
        passthru(
            sprintf(
                'php -S %s:%d -t %s %s',
                $host,
                $port,
                escapeshellarg($publicPath),
                file_exists($routerPath) ? escapeshellarg($routerPath) : ''
            ),
            $exitCode
        );

        return $exitCode ?: Command::SUCCESS;
    }

    /**
     * Check if a port is available.
     */
    protected function isPortAvailable($host, $port): bool
    {
        $connection = @fsockopen($host, $port);

        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }

        return true;
    }

    /**
     * Get the public path for the application.
     */
    protected function getPublicPath(): string
    {
        $basePath = dirname(dirname(dirname(__DIR__)));

        // Check if there's a public directory
        if (is_dir($basePath . '/public')) {
            return $basePath . '/public';
        }

        // Otherwise use the base path (project root)
        return $basePath;
    }
}
