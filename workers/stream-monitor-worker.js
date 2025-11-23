#!/usr/bin/env node

/**
 * Stream Monitor Worker
 *
 * Monitors running stream PIDs and detects crashes
 * Handles: Auto-restart on crash, health logging
 *
 * Poll Interval: 10 seconds
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */

const { execSync } = require('child_process');
const path = require('path');

// Configuration
const POLL_INTERVAL = 10000; // 10 seconds
const PHP_CLI = 'php';
const BASE_PATH = path.resolve(__dirname, '..');
const WORKER_SCRIPT = path.join(BASE_PATH, 'workers/php/stream-monitor.php');

// Logging
const LOG_LEVEL = process.env.LOG_LEVEL || 'warn';

function log(level, message) {
    const levels = ['debug', 'info', 'warn', 'error'];
    const currentLevel = levels.indexOf(LOG_LEVEL);
    const msgLevel = levels.indexOf(level);

    if (msgLevel >= currentLevel) {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [${level.toUpperCase()}] [stream-monitor] ${message}`);
    }
}

/**
 * Execute stream monitor PHP script
 */
function monitorStreams() {
    try {
        log('debug', 'Monitoring streams...');

        const output = execSync(`${PHP_CLI} "${WORKER_SCRIPT}"`, {
            cwd: BASE_PATH,
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'pipe']
        });

        const result = JSON.parse(output);

        if (result.checked > 0) {
            log('info',
                `Monitored ${result.checked} streams: ` +
                `${result.healthy} healthy, ` +
                `${result.crashed} crashed, ` +
                `${result.restarted} restarted`
            );

            if (result.crashed > 0) {
                log('warn', `${result.crashed} stream(s) crashed`);
            }

            if (result.restarted > 0) {
                log('info', `${result.restarted} stream(s) queued for restart`);
            }
        } else {
            log('debug', 'No streams to monitor');
        }

    } catch (error) {
        log('error', `Failed to monitor streams: ${error.message}`);

        if (LOG_LEVEL === 'debug') {
            log('debug', error.stack);
        }
    }
}

/**
 * Main worker loop
 */
function startWorker() {
    log('info', 'Stream Monitor Worker started');
    log('info', `Poll interval: ${POLL_INTERVAL}ms`);
    log('info', `Log level: ${LOG_LEVEL}`);
    log('info', `Base path: ${BASE_PATH}`);

    // Process immediately on start
    monitorStreams();

    // Set up interval
    setInterval(monitorStreams, POLL_INTERVAL);

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
