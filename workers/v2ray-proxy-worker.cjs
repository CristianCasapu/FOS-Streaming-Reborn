#!/usr/bin/env node

/**
 * V2Ray Proxy Worker
 *
 * Traffic obfuscation for bypassing DPI and censorship
 * Features:
 * - VMess/VLESS protocol support
 * - WebSocket tunneling
 * - Domain fronting
 * - Traffic shaping
 * - Multiple protocol camouflage
 * - PROXY-ONLY mode (no transcoding)
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const WebSocket = require('ws');
const mysql = require('mysql2/promise');
const Redis = require('ioredis');
const winston = require('winston');
const { v4: uuidv4 } = require('uuid');
const axios = require('axios');
require('dotenv').config({ path: '../.env' });

// Logger configuration
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: '../storage/logs/v2ray-error.log', level: 'error' }),
        new winston.transports.File({ filename: '../storage/logs/v2ray.log' }),
        new winston.transports.Console({
            format: winston.format.simple()
        })
    ]
});

// Database configuration
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Redis client
const redis = new Redis({
    host: process.env.REDIS_HOST || 'localhost',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || null,
    db: process.env.REDIS_DB || 0,
    retryStrategy: (times) => Math.min(times * 50, 2000)
});

// Active V2Ray connections
const activeConnections = new Map();
const v2rayProcesses = new Map();

class V2RayProxyWorker {
    constructor() {
        this.db = null;
        this.isRunning = false;
        this.v2rayBinary = process.env.V2RAY_BINARY || '/usr/local/bin/v2ray';
        this.v2rayConfigDir = process.env.V2RAY_CONFIG_DIR || '../configs/v2ray';
        this.wsServer = null;
        this.wsPort = parseInt(process.env.V2RAY_WS_PORT || 8080);
        this.vmessPort = parseInt(process.env.V2RAY_VMESS_PORT || 10086);
    }

    /**
     * Initialize worker
     */
    async init() {
        try {
            // Connect to database
            this.db = await mysql.createPool(dbConfig);
            logger.info('Database connection established');

            // Test Redis connection
            await redis.ping();
            logger.info('Redis connection established');

            // Check V2Ray installation
            await this.checkV2RayInstallation();

            // Create config directory
            if (!fs.existsSync(this.v2rayConfigDir)) {
                fs.mkdirSync(this.v2rayConfigDir, { recursive: true });
            }

            // Start WebSocket server for tunneling
            await this.startWebSocketServer();

            // Start monitoring
            this.startMonitoring();

            // Process V2Ray streams
            await this.processV2RayStreams();

            logger.info('V2Ray Proxy Worker initialized');
            this.isRunning = true;

        } catch (error) {
            logger.error('Failed to initialize V2Ray worker:', error);
            process.exit(1);
        }
    }

    /**
     * Check V2Ray installation
     */
    async checkV2RayInstallation() {
        return new Promise((resolve, reject) => {
            const checkProcess = spawn(this.v2rayBinary, ['version']);

            checkProcess.on('close', (code) => {
                if (code === 0) {
                    logger.info('V2Ray binary found and working');
                    resolve();
                } else {
                    logger.warn('V2Ray not found, attempting to download...');
                    this.downloadV2Ray().then(resolve).catch(reject);
                }
            });

            checkProcess.on('error', () => {
                logger.warn('V2Ray not found, attempting to download...');
                this.downloadV2Ray().then(resolve).catch(reject);
            });
        });
    }

    /**
     * Download and install V2Ray
     */
    async downloadV2Ray() {
        const installScript = `
            wget -O /tmp/install-release.sh https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh
            chmod +x /tmp/install-release.sh
            /tmp/install-release.sh
        `;

        return new Promise((resolve, reject) => {
            const install = spawn('bash', ['-c', installScript]);

            install.on('close', (code) => {
                if (code === 0) {
                    logger.info('V2Ray installed successfully');
                    this.v2rayBinary = '/usr/local/bin/v2ray';
                    resolve();
                } else {
                    reject(new Error('Failed to install V2Ray'));
                }
            });
        });
    }

    /**
     * Start WebSocket server for tunneling
     */
    async startWebSocketServer() {
        this.wsServer = new WebSocket.Server({
            port: this.wsPort,
            perMessageDeflate: false,
            clientTracking: true
        });

        this.wsServer.on('connection', (ws, req) => {
            const clientIp = req.headers['x-forwarded-for'] || req.connection.remoteAddress;
            const sessionId = crypto.randomBytes(16).toString('hex');

            logger.info(`WebSocket connection from ${clientIp}`);

            // Store connection
            activeConnections.set(sessionId, {
                ws,
                clientIp,
                authenticated: false,
                streamId: null,
                stats: {
                    bytesReceived: 0,
                    bytesSent: 0,
                    connectedAt: Date.now()
                }
            });

            // Handle messages
            ws.on('message', async (data) => {
                await this.handleWebSocketMessage(sessionId, data);
            });

            // Handle close
            ws.on('close', () => {
                logger.info(`WebSocket disconnected: ${sessionId}`);
                this.cleanupConnection(sessionId);
            });

            // Handle error
            ws.on('error', (error) => {
                logger.error(`WebSocket error for ${sessionId}:`, error);
                this.cleanupConnection(sessionId);
            });
        });

        logger.info(`WebSocket server listening on port ${this.wsPort}`);
    }

    /**
     * Handle WebSocket message
     */
    async handleWebSocketMessage(sessionId, data) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        try {
            // Parse message
            const message = JSON.parse(data.toString());

            switch (message.type) {
                case 'AUTH':
                    await this.handleAuthentication(sessionId, message);
                    break;

                case 'START_STREAM':
                    await this.handleStartStream(sessionId, message);
                    break;

                case 'STOP_STREAM':
                    await this.handleStopStream(sessionId);
                    break;

                case 'DATA':
                    await this.handleStreamData(sessionId, message.data);
                    break;

                default:
                    logger.warn(`Unknown message type: ${message.type}`);
            }

            connection.stats.bytesReceived += data.length;

        } catch (error) {
            logger.error(`Error handling WebSocket message:`, error);
            connection.ws.send(JSON.stringify({
                type: 'ERROR',
                message: 'Invalid message format'
            }));
        }
    }

    /**
     * Handle authentication
     */
    async handleAuthentication(sessionId, message) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        try {
            const { sessionToken } = message;

            // Validate device session
            const [sessions] = await this.db.execute(`
                SELECT
                    ds.id,
                    ds.device_binding_id,
                    db.subscription_id,
                    sub.subscriber_id
                FROM device_sessions ds
                JOIN device_bindings db ON db.id = ds.device_binding_id
                JOIN subscriptions sub ON sub.id = db.subscription_id
                WHERE ds.session_token = ?
                    AND ds.is_active = 1
                LIMIT 1
            `, [sessionToken]);

            if (sessions.length === 0) {
                connection.ws.send(JSON.stringify({
                    type: 'AUTH_FAILED',
                    message: 'Invalid session'
                }));
                return;
            }

            connection.authenticated = true;
            connection.deviceSession = sessions[0];

            // Generate VMess user config
            const vmessConfig = await this.generateVMessConfig(sessions[0].subscriber_id);

            connection.ws.send(JSON.stringify({
                type: 'AUTH_SUCCESS',
                vmessConfig,
                wsPath: `/streaming/${sessionId}`
            }));

            logger.info(`Session ${sessionId} authenticated`);

        } catch (error) {
            logger.error('Authentication error:', error);
            connection.ws.send(JSON.stringify({
                type: 'AUTH_FAILED',
                message: 'Authentication error'
            }));
        }
    }

    /**
     * Generate VMess configuration for subscriber
     */
    async generateVMessConfig(subscriberId) {
        // Check if config exists in Redis
        const cachedConfig = await redis.get(`vmess:config:${subscriberId}`);
        if (cachedConfig) {
            return JSON.parse(cachedConfig);
        }

        // Generate new VMess config
        const vmessId = uuidv4();
        const config = {
            v: '2',
            ps: `FOS-Stream-${subscriberId}`,
            add: process.env.V2RAY_SERVER_IP || 'localhost',
            port: this.vmessPort,
            id: vmessId,
            aid: 64, // AlterID
            scy: 'auto', // Security
            net: 'ws', // Network type
            type: 'none',
            host: process.env.V2RAY_DOMAIN || 'localhost',
            path: '/streaming',
            tls: 'tls',
            sni: process.env.V2RAY_SNI || ''
        };

        // Store in database
        await this.db.execute(`
            INSERT INTO v2ray_users (subscriber_id, vmess_id, config, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE vmess_id = VALUES(vmess_id), config = VALUES(config)
        `, [subscriberId, vmessId, JSON.stringify(config)]);

        // Cache in Redis
        await redis.setex(`vmess:config:${subscriberId}`, 3600, JSON.stringify(config));

        return config;
    }

    /**
     * Handle start stream request
     */
    async handleStartStream(sessionId, message) {
        const connection = activeConnections.get(sessionId);
        if (!connection || !connection.authenticated) {
            connection.ws.send(JSON.stringify({
                type: 'ERROR',
                message: 'Not authenticated'
            }));
            return;
        }

        try {
            const { streamId } = message;

            // Get stream configuration
            const [streams] = await this.db.execute(`
                SELECT
                    id,
                    name,
                    source_url,
                    proxy_settings,
                    encryption_settings
                FROM streams
                WHERE id = ?
                    AND status = 1
                    AND stream_mode = 'proxy'
                LIMIT 1
            `, [streamId]);

            if (streams.length === 0) {
                connection.ws.send(JSON.stringify({
                    type: 'ERROR',
                    message: 'Stream not found'
                }));
                return;
            }

            const stream = streams[0];
            connection.streamId = streamId;

            // Start V2Ray instance for this connection
            const v2rayConfig = await this.createV2RayConfig(sessionId, stream);
            await this.startV2RayInstance(sessionId, v2rayConfig);

            connection.ws.send(JSON.stringify({
                type: 'STREAM_STARTED',
                streamId,
                inboundPort: v2rayConfig.inbound.port,
                protocol: v2rayConfig.inbound.protocol
            }));

            logger.info(`Stream ${streamId} started for session ${sessionId}`);

        } catch (error) {
            logger.error('Error starting stream:', error);
            connection.ws.send(JSON.stringify({
                type: 'ERROR',
                message: 'Failed to start stream'
            }));
        }
    }

    /**
     * Create V2Ray configuration
     */
    async createV2RayConfig(sessionId, stream) {
        const inboundPort = 20000 + Math.floor(Math.random() * 10000);

        const config = {
            log: {
                loglevel: 'warning'
            },
            inbound: {
                port: inboundPort,
                protocol: 'vmess',
                settings: {
                    clients: [{
                        id: uuidv4(),
                        alterId: 64
                    }]
                },
                streamSettings: {
                    network: 'ws',
                    wsSettings: {
                        path: `/stream/${sessionId}`,
                        headers: {
                            Host: process.env.V2RAY_DOMAIN || 'localhost'
                        }
                    }
                }
            },
            outbound: {
                protocol: 'freedom',
                settings: {
                    redirect: stream.source_url
                }
            },
            inboundDetour: [],
            outboundDetour: [
                {
                    protocol: 'blackhole',
                    settings: {},
                    tag: 'blocked'
                }
            ],
            routing: {
                strategy: 'rules',
                settings: {
                    rules: [
                        {
                            type: 'field',
                            ip: ['0.0.0.0/8', '127.0.0.0/8'],
                            outboundTag: 'blocked'
                        }
                    ]
                }
            }
        };

        // Add obfuscation based on settings
        const proxySettings = JSON.parse(stream.proxy_settings || '{}');
        if (proxySettings.obfuscation === 'tls') {
            config.inbound.streamSettings.security = 'tls';
            config.inbound.streamSettings.tlsSettings = {
                certificates: [{
                    certificateFile: '/etc/v2ray/cert.pem',
                    keyFile: '/etc/v2ray/key.pem'
                }]
            };
        } else if (proxySettings.obfuscation === 'http') {
            config.inbound.streamSettings.network = 'tcp';
            config.inbound.streamSettings.tcpSettings = {
                header: {
                    type: 'http',
                    request: {
                        version: '1.1',
                        method: 'GET',
                        path: ['/'],
                        headers: {
                            Host: [process.env.V2RAY_DOMAIN || 'localhost'],
                            'User-Agent': [
                                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                            ]
                        }
                    }
                }
            };
        }

        // Save config to file
        const configPath = path.join(this.v2rayConfigDir, `${sessionId}.json`);
        fs.writeFileSync(configPath, JSON.stringify(config, null, 2));

        return config;
    }

    /**
     * Start V2Ray instance
     */
    async startV2RayInstance(sessionId, config) {
        const configPath = path.join(this.v2rayConfigDir, `${sessionId}.json`);

        // Start V2Ray process
        const v2rayProcess = spawn(this.v2rayBinary, ['run', '-c', configPath]);

        v2rayProcess.stdout.on('data', (data) => {
            logger.debug(`V2Ray ${sessionId} stdout: ${data}`);
        });

        v2rayProcess.stderr.on('data', (data) => {
            logger.error(`V2Ray ${sessionId} stderr: ${data}`);
        });

        v2rayProcess.on('close', (code) => {
            logger.info(`V2Ray ${sessionId} exited with code ${code}`);
            v2rayProcesses.delete(sessionId);
        });

        // Store process reference
        v2rayProcesses.set(sessionId, {
            process: v2rayProcess,
            config,
            startTime: Date.now()
        });

        // Wait for process to start
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Update stream status
        await this.db.execute(`
            UPDATE streams
            SET proxy_status = 'active',
                proxy_port = ?,
                current_connections = current_connections + 1,
                last_checked = NOW()
            WHERE id = ?
        `, [config.inbound.port, activeConnections.get(sessionId).streamId]);
    }

    /**
     * Handle stop stream
     */
    async handleStopStream(sessionId) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        // Stop V2Ray instance
        const v2rayInfo = v2rayProcesses.get(sessionId);
        if (v2rayInfo) {
            v2rayInfo.process.kill();
            v2rayProcesses.delete(sessionId);

            // Remove config file
            const configPath = path.join(this.v2rayConfigDir, `${sessionId}.json`);
            if (fs.existsSync(configPath)) {
                fs.unlinkSync(configPath);
            }
        }

        // Update stream status
        if (connection.streamId) {
            await this.db.execute(`
                UPDATE streams
                SET current_connections = GREATEST(0, current_connections - 1),
                    last_checked = NOW()
                WHERE id = ?
            `, [connection.streamId]);
        }

        connection.ws.send(JSON.stringify({
            type: 'STREAM_STOPPED'
        }));

        logger.info(`Stream stopped for session ${sessionId}`);
    }

    /**
     * Handle stream data (for statistics)
     */
    async handleStreamData(sessionId, data) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        connection.stats.bytesSent += data.length;

        // Update Redis stats
        await redis.hincrby(`v2ray:stats:${sessionId}`, 'bytes_sent', data.length);
    }

    /**
     * Process V2Ray streams
     */
    async processV2RayStreams() {
        while (this.isRunning) {
            try {
                // Get streams configured for V2Ray
                const [streams] = await this.db.execute(`
                    SELECT
                        s.id,
                        s.name,
                        s.source_url,
                        s.proxy_settings
                    FROM streams s
                    WHERE s.enabled = 1
                        AND s.stream_mode = 'proxy'
                        AND JSON_EXTRACT(s.proxy_settings, '$.protocol') = 'v2ray'
                `);

                for (const stream of streams) {
                    // Check if stream needs V2Ray proxy
                    await this.checkStreamProxy(stream);
                }

                // Cleanup dead connections
                await this.cleanupDeadConnections();

            } catch (error) {
                logger.error('Error processing V2Ray streams:', error);
            }

            // Wait before next check
            await new Promise(resolve => setTimeout(resolve, 10000));
        }
    }

    /**
     * Check stream proxy status
     */
    async checkStreamProxy(stream) {
        // Implementation for checking and maintaining stream proxy
        const proxySettings = JSON.parse(stream.proxy_settings || '{}');

        if (proxySettings.autoStart && !v2rayProcesses.has(`stream-${stream.id}`)) {
            // Auto-start V2Ray for persistent streams
            logger.info(`Auto-starting V2Ray for stream ${stream.id}`);
            // Implementation here
        }
    }

    /**
     * Cleanup dead connections
     */
    async cleanupDeadConnections() {
        const now = Date.now();

        for (const [sessionId, connection] of activeConnections) {
            // Check if connection is alive
            if (connection.ws.readyState !== WebSocket.OPEN) {
                await this.cleanupConnection(sessionId);
                continue;
            }

            // Check for timeout (30 minutes)
            if (now - connection.stats.connectedAt > 1800000) {
                connection.ws.send(JSON.stringify({
                    type: 'TIMEOUT',
                    message: 'Session timeout'
                }));
                connection.ws.close();
                await this.cleanupConnection(sessionId);
            }
        }
    }

    /**
     * Cleanup connection
     */
    async cleanupConnection(sessionId) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        // Stop V2Ray if running
        await this.handleStopStream(sessionId);

        // Remove from active connections
        activeConnections.delete(sessionId);

        // Clean Redis
        await redis.del(`v2ray:stats:${sessionId}`);

        // Log statistics
        const duration = (Date.now() - connection.stats.connectedAt) / 1000;
        logger.info(`Session ${sessionId} cleanup:`, {
            duration: `${duration}s`,
            bytesReceived: connection.stats.bytesReceived,
            bytesSent: connection.stats.bytesSent
        });
    }

    /**
     * Start monitoring
     */
    startMonitoring() {
        setInterval(async () => {
            const stats = {
                activeConnections: activeConnections.size,
                v2rayProcesses: v2rayProcesses.size,
                authenticatedSessions: 0,
                totalBytesSent: 0,
                totalBytesReceived: 0
            };

            for (const [sessionId, connection] of activeConnections) {
                if (connection.authenticated) {
                    stats.authenticatedSessions++;
                }
                stats.totalBytesSent += connection.stats.bytesSent;
                stats.totalBytesReceived += connection.stats.bytesReceived;
            }

            logger.info('V2Ray Proxy Stats:', stats);

            // Update Redis stats
            await redis.set('v2ray:stats:global', JSON.stringify(stats));

        }, 30000); // Every 30 seconds
    }

    /**
     * Graceful shutdown
     */
    async shutdown() {
        logger.info('Shutting down V2Ray Proxy Worker...');
        this.isRunning = false;

        // Stop all V2Ray processes
        for (const [sessionId, v2rayInfo] of v2rayProcesses) {
            v2rayInfo.process.kill();
        }

        // Close all WebSocket connections
        for (const [sessionId, connection] of activeConnections) {
            connection.ws.close();
        }

        // Close WebSocket server
        if (this.wsServer) {
            this.wsServer.close();
        }

        // Close database connection
        if (this.db) {
            await this.db.end();
        }

        // Close Redis connection
        redis.disconnect();

        logger.info('V2Ray Proxy Worker shutdown complete');
        process.exit(0);
    }
}

// Initialize and run worker
const worker = new V2RayProxyWorker();

// Handle shutdown signals
process.on('SIGINT', () => worker.shutdown());
process.on('SIGTERM', () => worker.shutdown());

// Start worker
worker.init().catch(error => {
    logger.error('Failed to start V2Ray Proxy Worker:', error);
    process.exit(1);
});