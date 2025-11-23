#!/usr/bin/env node

/**
 * Stream Manager Worker
 *
 * Monitors streams table for scheduled commands and executes them
 * Handles: start, stop, restart commands for FFmpeg streams
 *
 * Poll Interval: 5 seconds
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */

const { execSync } = require('child_process');
const path = require('path');

// Configuration
const POLL_INTERVAL = 5000; // 5 seconds
const PHP_CLI = 'php';
const BASE_PATH = path.resolve(__dirname, '..');
const WORKER_SCRIPT = path.join(BASE_PATH, 'workers/php/stream-manager.php');

// Logging
const LOG_LEVEL = process.env.LOG_LEVEL || 'warn';

function log(level, message) {
    const levels = ['debug', 'info', 'warn', 'error'];
    const currentLevel = levels.indexOf(LOG_LEVEL);
    const msgLevel = levels.indexOf(level);

    if (msgLevel >= currentLevel) {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [${level.toUpperCase()}] [stream-manager] ${message}`);
    }
}

/**
 * Execute stream manager PHP script
 */
function processCommands() {
    try {
        log('debug', 'Checking for queued commands...');

        const output = execSync(`${PHP_CLI} "${WORKER_SCRIPT}"`, {
            cwd: BASE_PATH,
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'pipe']
        });

        const result = JSON.parse(output);

        if (result.processed > 0) {
            log('info', `Processed ${result.processed} commands: ${result.success} success, ${result.failed} failed`);

            // Log individual command results
            if (LOG_LEVEL === 'debug') {
                result.commands.forEach(cmd => {
                    log('debug', `Stream ${cmd.stream_id} - ${cmd.command}: ${cmd.message}`);
                });
            }
        } else {
            log('debug', 'No commands to process');
        }

    } catch (error) {
        log('error', `Failed to process commands: ${error.message}`);

        if (LOG_LEVEL === 'debug') {
            log('debug', error.stack);
        }
    }
}

/**
 * Main worker loop
 */
function startWorker() {
    log('info', 'Stream Manager Worker started');
    log('info', `Poll interval: ${POLL_INTERVAL}ms`);
    log('info', `Log level: ${LOG_LEVEL}`);
    log('info', `Base path: ${BASE_PATH}`);

    // Process immediately on start
    processCommands();

    // Set up interval
    setInterval(processCommands, POLL_INTERVAL);

    // Handle graceful shutdown
    process.on('SIGINT', () => {
        log('info', 'Received SIGINT, shutting down gracefully...');
        process.exit(0);
    });

    process.on('SIGTERM', () => {
        log('info', 'Received SIGTERM, shutting down gracefully...');
        process.exit(0);
    });
}

// Start the worker
startWorker();
