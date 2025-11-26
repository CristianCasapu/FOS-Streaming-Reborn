#!/usr/bin/env node
/**
 * FFprobe Analysis Worker - PM2 Managed Background Service
 *
 * Analyzes streams using FFprobe and auto-sets optimal transcode profiles.
 *
 * Features:
 * - Analyzes streams that need analysis (pending, failed > 1h ago)
 * - Auto-sets optimal transcode profiles for streams without profiles
 * - Configurable concurrency (CONCURRENT_STREAMS env var)
 * - Sequential stream testing by default (one by one)
 *
 * Environment Variables:
 * - POLL_INTERVAL: How often to check for streams (default: 10000ms)
 * - CONCURRENT_STREAMS: Number of streams to analyze per poll (default: 1)
 * - MODE: 'auto' for auto-analyze, 'queue' for job queue (default: auto)
 * - LOG_LEVEL: debug|info|warn|error (default: warn)
 */

const { execSync } = require('child_process');
const path = require('path');

// Configuration (read from environment variables for runtime configurability)
const POLL_INTERVAL = parseInt(process.env.POLL_INTERVAL, 10) || 10000; // 10 seconds default
const CONCURRENT_STREAMS = parseInt(process.env.CONCURRENT_STREAMS, 10) || 1; // 1 stream at a time by default
const MODE = process.env.MODE || 'auto'; // 'auto' or 'queue'
const PHP_CLI = process.env.PHP_CLI || 'php';
const BASE_PATH = path.resolve(__dirname, '..');
const WORKER_SCRIPT = path.join(BASE_PATH, 'workers/php/ffprobe-analyzer.php');
const LOG_LEVEL = process.env.LOG_LEVEL || 'warn';

// Logging utility
function log(level, message) {
    const levels = ['debug', 'info', 'warn', 'error'];
    const currentLevel = levels.indexOf(LOG_LEVEL);
    const msgLevel = levels.indexOf(level);

    if (msgLevel >= currentLevel) {
        const timestamp = new Date().toISOString();
        console.log(`[${timestamp}] [${level.toUpperCase()}] [ffprobe-worker] ${message}`);
    }
}

// State
let isProcessing = false;
let totalAnalyzed = 0;
let totalFailed = 0;
let totalProfilesSet = 0;
let totalRecovered = 0;  // Streams recovered from error state
let shutdownRequested = false;

/**
 * Extract JSON from output that may contain log lines
 * The PHP script outputs log lines followed by a JSON object at the end
 */
function extractJson(output) {
    // Try to find JSON object in the output (starts with { and ends with })
    const jsonMatch = output.match(/\{[\s\S]*\}$/);
    if (jsonMatch) {
        return JSON.parse(jsonMatch[0]);
    }
    // If no JSON object found, try parsing the whole output
    return JSON.parse(output);
}

/**
 * Execute FFprobe analyzer PHP script
 */
function analyzeStreams() {
    if (isProcessing || shutdownRequested) {
        return;
    }

    isProcessing = true;

    try {
        log('debug', `Checking for streams to analyze (mode: ${MODE}, concurrent: ${CONCURRENT_STREAMS})...`);

        // Set environment variables for PHP script
        const env = {
            ...process.env,
            CONCURRENT_STREAMS: CONCURRENT_STREAMS.toString(),
            MODE: MODE
        };

        const output = execSync(`${PHP_CLI} "${WORKER_SCRIPT}"`, {
            cwd: BASE_PATH,
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'pipe'],
            env: env
        });

        const result = extractJson(output);

        if (result.processed > 0) {
            totalAnalyzed += result.analyzed;
            totalFailed += result.failed;
            totalProfilesSet += result.profiles_set;
            totalRecovered += result.recovered || 0;

            let logMsg = `Processed ${result.processed} stream(s): ` +
                `${result.analyzed} analyzed, ${result.failed} failed, ` +
                `${result.profiles_set} profiles auto-set`;

            // Highlight recoveries
            if (result.recovered > 0) {
                logMsg += `, ${result.recovered} recovered from error state`;
            }

            log('info', logMsg);

            // Log individual stream results at debug level
            if (LOG_LEVEL === 'debug' && result.streams) {
                result.streams.forEach(stream => {
                    const status = stream.success ? '✓' : '✗';
                    log('debug', `  ${status} Stream ${stream.id} (${stream.name}): ${stream.message}`);
                });
            }
        } else {
            log('debug', 'No streams need analysis at this time');
        }

    } catch (error) {
        log('error', `Failed to analyze streams: ${error.message}`);

        if (LOG_LEVEL === 'debug') {
            log('debug', error.stack);
        }
    } finally {
        isProcessing = false;
    }
}

/**
 * Graceful shutdown
 */
function shutdown() {
    if (shutdownRequested) {
        return;
    }

    shutdownRequested = true;
    log('info', 'Shutdown requested...');

    if (!isProcessing) {
        log('info', `FFprobe worker stopped. Total analyzed: ${totalAnalyzed}, Failed: ${totalFailed}, Profiles set: ${totalProfilesSet}, Recovered: ${totalRecovered}`);
        process.exit(0);
    } else {
        log('info', 'Waiting for current analysis to finish...');
        setTimeout(() => {
            log('warn', 'Force shutdown');
            process.exit(0);
        }, 60000); // Force shutdown after 60 seconds (FFprobe can take time)
    }
}

/**
 * Health check for PM2
 */
function healthCheck() {
    const stats = {
        uptime: process.uptime(),
        analyzed: totalAnalyzed,
        failed: totalFailed,
        profiles_set: totalProfilesSet,
        recovered: totalRecovered,
        processing: isProcessing,
        memory: process.memoryUsage()
    };

    log('debug', `FFprobe worker health: ${JSON.stringify(stats)}`);
}

// Signal handlers for graceful shutdown
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

// PM2 graceful shutdown
process.on('message', (msg) => {
    if (msg === 'shutdown') {
        shutdown();
    }
});

// Uncaught exception handler
process.on('uncaughtException', (err) => {
    log('error', `Uncaught exception: ${err.message}`);
    shutdown();
});

// Unhandled rejection handler
process.on('unhandledRejection', (reason, promise) => {
    log('error', `Unhandled rejection: ${reason}`);
});

/**
 * Main worker loop
 */
function startWorker() {
    log('info', 'FFprobe Analysis Worker started');
    log('info', `Mode: ${MODE}`);
    log('info', `Concurrent streams: ${CONCURRENT_STREAMS}`);
    log('info', `Poll interval: ${POLL_INTERVAL}ms`);
    log('info', `Log level: ${LOG_LEVEL}`);
    log('info', `Worker ID: ${process.pid}`);
    log('info', `Base path: ${BASE_PATH}`);

    // Initial analysis
    analyzeStreams();

    // Poll at interval
    setInterval(analyzeStreams, POLL_INTERVAL);

    // Health check every 60 seconds
    setInterval(healthCheck, 60000);
}

// Start the worker
startWorker();
