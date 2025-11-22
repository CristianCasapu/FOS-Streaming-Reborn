/**
 * PM2 Ecosystem Configuration for FOS-Streaming v70
 *
 * This file configures PM2 process manager for background workers.
 *
 * Workers:
 * 1. stream-import-worker - Handles bulk stream imports
 * 2. ffprobe-worker - Processes FFprobe analysis queue
 *
 * Usage:
 *   pm2 start ecosystem.config.js
 *   pm2 save
 *   pm2 startup
 */

const path = require('path');
const isDev = process.env.APP_ENV === 'local' || process.env.NODE_ENV === 'development';

module.exports = {
  apps: [
    {
      name: 'stream-import-worker',
      script: './workers/stream-import-worker.js',
      instances: 1,
      exec_mode: 'fork',

      // Auto-restart configuration
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,

      // Watch for file changes in development
      watch: isDev,
      ignore_watch: [
        'node_modules',
        'logs',
        '*.log',
        'storage',
        'cache'
      ],

      // Logging
      error_file: './storage/logs/pm2-stream-import-error.log',
      out_file: './storage/logs/pm2-stream-import-out.log',
      log_file: './storage/logs/pm2-stream-import-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,

      // Environment variables
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      env_development: {
        NODE_ENV: 'development',
        LOG_LEVEL: 'debug'
      },
      env_local: {
        NODE_ENV: 'development',
        LOG_LEVEL: 'debug'
      },

      // Resource limits
      max_memory_restart: '500M',

      // Cron restart (restart daily at 3 AM)
      cron_restart: '0 3 * * *',

      // Graceful shutdown
      kill_timeout: 5000,
      listen_timeout: 3000,
      shutdown_with_message: true
    },

    {
      name: 'ffprobe-worker',
      script: './workers/ffprobe-worker.js',
      instances: 2, // Can process 2 streams simultaneously
      exec_mode: 'cluster',

      // Auto-restart configuration
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,

      // Watch for file changes in development
      watch: isDev,
      ignore_watch: [
        'node_modules',
        'logs',
        '*.log',
        'storage',
        'cache'
      ],

      // Logging
      error_file: './storage/logs/pm2-ffprobe-error.log',
      out_file: './storage/logs/pm2-ffprobe-out.log',
      log_file: './storage/logs/pm2-ffprobe-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,

      // Environment variables
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      env_development: {
        NODE_ENV: 'development',
        LOG_LEVEL: 'debug'
      },
      env_local: {
        NODE_ENV: 'development',
        LOG_LEVEL: 'debug'
      },

      // Resource limits
      max_memory_restart: '300M',

      // Cron restart (restart daily at 3 AM)
      cron_restart: '0 3 * * *',

      // Graceful shutdown
      kill_timeout: 30000, // FFprobe may take time
      listen_timeout: 3000,
      shutdown_with_message: true
    }
  ]
};
