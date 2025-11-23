/**
 * PM2 Ecosystem Configuration
 * 
 * AUTO-GENERATED from database by PM2WorkerService
 * Generated: 2025-11-23 04:09:12
 * Workers: 5
 * 
 * DO NOT EDIT MANUALLY - Use Admin UI to manage workers
 */

const isDev = process.env.NODE_ENV !== 'production';

module.exports = {
  apps: [
    {
      name: 'stream-import-worker',
      script: './workers/stream-import-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,
      max_memory_restart: '500M',
      cron_restart: '0 3 * * *',
      error_file: './storage/logs/pm2-stream-import-error.log',
      out_file: './storage/logs/pm2-stream-import-out.log',
      log_file: './storage/logs/pm2-stream-import-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      watch: false,
      ignore_watch: ['node_modules', 'logs', '*.log', 'storage', 'cache'],
      kill_timeout: 5000,
      listen_timeout: 3000,
      shutdown_with_message: true,
    },
    {
      name: 'ffprobe-worker',
      script: './workers/ffprobe-worker.js',
      instances: 2,
      exec_mode: 'cluster',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,
      max_memory_restart: '300M',
      cron_restart: '0 3 * * *',
      error_file: './storage/logs/pm2-ffprobe-error.log',
      out_file: './storage/logs/pm2-ffprobe-out.log',
      log_file: './storage/logs/pm2-ffprobe-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      watch: false,
      ignore_watch: ['node_modules', 'logs', '*.log', 'storage', 'cache'],
      kill_timeout: 30000,
      listen_timeout: 3000,
      shutdown_with_message: true,
    },
    {
      name: 'stream-manager-worker',
      script: './workers/stream-manager-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,
      max_memory_restart: '400M',
      cron_restart: '0 3 * * *',
      error_file: './storage/logs/pm2-stream-manager-error.log',
      out_file: './storage/logs/pm2-stream-manager-out.log',
      log_file: './storage/logs/pm2-stream-manager-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      watch: false,
      ignore_watch: ['node_modules', 'logs', '*.log', 'storage', 'cache'],
      kill_timeout: 10000,
      listen_timeout: 3000,
      shutdown_with_message: true,
    },
    {
      name: 'stream-monitor-worker',
      script: './workers/stream-monitor-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,
      max_memory_restart: '300M',
      cron_restart: '0 3 * * *',
      error_file: './storage/logs/pm2-stream-monitor-error.log',
      out_file: './storage/logs/pm2-stream-monitor-out.log',
      log_file: './storage/logs/pm2-stream-monitor-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      watch: false,
      ignore_watch: ['node_modules', 'logs', '*.log', 'storage', 'cache'],
      kill_timeout: 5000,
      listen_timeout: 3000,
      shutdown_with_message: true,
    },
    {
      name: 'website-health-worker',
      script: './workers/website-health-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      restart_delay: 5000,
      max_memory_restart: '200M',
      cron_restart: '0 3 * * *',
      error_file: './storage/logs/pm2-website-health-error.log',
      out_file: './storage/logs/pm2-website-health-out.log',
      log_file: './storage/logs/pm2-website-health-combined.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      watch: false,
      ignore_watch: ['node_modules', 'logs', '*.log', 'storage', 'cache'],
      kill_timeout: 5000,
      listen_timeout: 3000,
      shutdown_with_message: true,
    }  ]
};
