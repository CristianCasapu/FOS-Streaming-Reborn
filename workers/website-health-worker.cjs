#!/usr/bin/env node

/**
 * Website Health Worker
 *
 * Monitors system services and infrastructure health
 * Handles: Nginx, MariaDB, PHP-FPM, CPU, Memory, Disk monitoring
 *
 * Poll Interval: 30 seconds
 * Related Documentation: /docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md
 */

const { execSync } = require('child_process');
const path = require('path');

// Configuration
const POLL_INTERVAL = 30000; // 30 seconds
const PHP_CLI = 'php';
const BASE_PATH = path.resolve(__dirname, '..');
const WORKER_SCRIPT = path.join(BASE_PATH, 'workers/php/website-health.php');

// Logging
const LOG_LEVEL = process.env.LOG_LEVEL || 'warn';

function log(level, message) {
    const levels = ['debug', 'info', 'warn', 'error'];
    const currentLevel = levels.indexOf(LOG_LEVEL);
    const msgLevel = levels.indexOf(level);

    if (msgLevel >= currentLevel) {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [${level.toUpperCase()}] [website-health] ${message}`);
    }
}

/**
 * Execute website health monitor PHP script
 */
function monitorHealth() {
    try {
        log('debug', 'Monitoring system health...');

        const output = execSync(`${PHP_CLI} "${WORKER_SCRIPT}"`, {
            cwd: BASE_PATH,
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'pipe']
        });

        const result = JSON.parse(output);

        if (result.checked > 0) {
            log('info',
                `Checked ${result.checked} components: ` +
                `${result.healthy} healthy, ` +
                `${result.warning} warning, ` +
                `${result.critical} critical`
            );

            if (result.critical > 0) {
                log('error', `CRITICAL: ${result.critical} component(s) in critical state!`);
            } else if (result.warning > 0) {
                log('warn', `WARNING: ${result.warning} component(s) need attention`);
            }
        }

    } catch (error) {
        log('error', `Failed to monitor system health: ${error.message}`);

        if (LOG_LEVEL === 'debug') {
            log('debug', error.stack);
        }
    }
}

/**
 * Main worker loop
 */
function startWorker() {
    log('info', 'Website Health Worker started');
    log('info', `Poll interval: ${POLL_INTERVAL}ms`);
    log('info', `Log level: ${LOG_LEVEL}`);
    log('info', `Base path: ${BASE_PATH}`);

    // Process immediately on start
    monitorHealth();

    // Set up interval
    setInterval(monitorHealth, POLL_INTERVAL);

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
