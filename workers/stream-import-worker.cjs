#!/usr/bin/env node
/**
 * Stream Import Worker - PM2 Managed Background Service
 *
 * Processes stream import jobs from the queue.
 * Jobs are added by the web app when users import M3U playlists.
 *
 * This worker:
 * - Polls the stream-import queue
 * - Creates streams in the database
 * - Creates categories as needed
 * - Queues streams for FFprobe analysis
 * - Handles errors and retries
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

// Configuration (read from environment variables for runtime configurability)
const QUEUE_NAME = 'stream-import';
const POLL_INTERVAL = parseInt(process.env.POLL_INTERVAL, 10) || 5000; // 5 seconds default
const PHP_WORKER_SCRIPT = path.join(__dirname, '../scripts/process-import-job.php');
const LOG_LEVEL = process.env.LOG_LEVEL || 'warn';

// Logging utility
const log = {
  debug: (...args) => LOG_LEVEL === 'debug' && console.log('[DEBUG]', new Date().toISOString(), ...args),
  info: (...args) => ['debug', 'info'].includes(LOG_LEVEL) && console.log('[INFO]', new Date().toISOString(), ...args),
  warn: (...args) => console.warn('[WARN]', new Date().toISOString(), ...args),
  error: (...args) => console.error('[ERROR]', new Date().toISOString(), ...args)
};

// State
let isProcessing = false;
let processedCount = 0;
let failedCount = 0;
let shutdownRequested = false;

/**
 * Process a single import job
 */
async function processJob(jobId) {
  return new Promise((resolve, reject) => {
    log.debug(`Processing job: ${jobId}`);

    const php = spawn('php', [PHP_WORKER_SCRIPT, jobId], {
      stdio: ['ignore', 'pipe', 'pipe']
    });

    let stdout = '';
    let stderr = '';

    php.stdout.on('data', (data) => {
      stdout += data.toString();
    });

    php.stderr.on('data', (data) => {
      stderr += data.toString();
    });

    php.on('close', (code) => {
      if (code === 0) {
        log.info(`Job ${jobId} completed successfully`);
        processedCount++;
        resolve({ success: true, output: stdout });
      } else {
        log.error(`Job ${jobId} failed with code ${code}: ${stderr}`);
        failedCount++;
        reject(new Error(stderr || `Exit code: ${code}`));
      }
    });

    php.on('error', (err) => {
      log.error(`Failed to start PHP process for job ${jobId}:`, err);
      reject(err);
    });
  });
}

/**
 * Poll queue and process jobs
 */
async function pollQueue() {
  if (isProcessing || shutdownRequested) {
    return;
  }

  isProcessing = true;

  try {
    // Call PHP script to get next job
    const getNextJob = spawn('php', ['-r', `
      require_once '${path.join(__dirname, '../config.php')}';
      require_once '${path.join(__dirname, '../app/Services/JobQueueService.php')}';
      $queue = new \\App\\Services\\JobQueueService();
      $job = $queue->pop('${QUEUE_NAME}');
      if ($job) {
        echo json_encode($job);
      }
    `]);

    let output = '';

    getNextJob.stdout.on('data', (data) => {
      output += data.toString();
    });

    getNextJob.on('close', async (code) => {
      if (code === 0 && output.trim()) {
        try {
          const job = JSON.parse(output.trim());

          if (job && job.id) {
            log.info(`Found job: ${job.id}`);
            await processJob(job.id);
          }
        } catch (err) {
          log.error('Failed to parse job:', err);
        }
      }

      isProcessing = false;
    });

  } catch (err) {
    log.error('Error polling queue:', err);
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
  log.info('Shutdown requested...');

  if (!isProcessing) {
    log.info(`Worker stopped. Processed: ${processedCount}, Failed: ${failedCount}`);
    process.exit(0);
  } else {
    log.info('Waiting for current job to finish...');
    setTimeout(() => {
      log.warn('Force shutdown');
      process.exit(0);
    }, 30000); // Force shutdown after 30 seconds
  }
}

/**
 * Health check for PM2
 */
function healthCheck() {
  const stats = {
    uptime: process.uptime(),
    processed: processedCount,
    failed: failedCount,
    processing: isProcessing,
    memory: process.memoryUsage()
  };

  log.debug('Health check:', stats);
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
  log.error('Uncaught exception:', err);
  shutdown();
});

// Unhandled rejection handler
process.on('unhandledRejection', (reason, promise) => {
  log.error('Unhandled rejection at:', promise, 'reason:', reason);
});

// Start worker
log.info('Stream Import Worker started');
log.info(`Queue: ${QUEUE_NAME}`);
log.info(`Poll interval: ${POLL_INTERVAL}ms`);
log.info(`Log level: ${LOG_LEVEL}`);

// Poll queue at interval
const pollTimer = setInterval(pollQueue, POLL_INTERVAL);

// Health check every 60 seconds
setInterval(healthCheck, 60000);

// Initial poll
pollQueue();
