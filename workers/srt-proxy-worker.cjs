#!/usr/bin/env node

/**
 * SRT Proxy Worker
 *
 * PROXY-ONLY streaming worker using SRT protocol
 * NO TRANSCODING - only secure relay with encryption
 *
 * Features:
 * - SRT to SRT proxy with AES-256 encryption
 * - Device locking validation
 * - Concurrent stream limiting
 * - Real-time monitoring
 * - Automatic reconnection
 * - No transcoding or re-encoding
 */

const SRT = require('node-srt');
const mysql = require('mysql2/promise');
const Redis = require('ioredis');
const crypto = require('crypto');
const winston = require('winston');
const { URL } = require('url');
require('dotenv').config({ path: '../.env' });

// Logger configuration
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: '../storage/logs/srt-proxy-error.log', level: 'error' }),
        new winston.transports.File({ filename: '../storage/logs/srt-proxy.log' }),
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

// Redis client for session management
const redis = new Redis({
    host: process.env.REDIS_HOST || 'localhost',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || null,
    db: process.env.REDIS_DB || 0,
    retryStrategy: (times) => Math.min(times * 50, 2000)
});

// Active proxy connections
const activeProxies = new Map();

class SRTProxyWorker {
    constructor() {
        this.db = null;
        this.isRunning = false;
        this.proxyPort = parseInt(process.env.SRT_PROXY_PORT || 9000);
        this.encryptionKey = process.env.SRT_ENCRYPTION_KEY || this.generateEncryptionKey();
    }

    /**
     * Generate a secure encryption key for SRT
     */
    generateEncryptionKey() {
        const key = crypto.randomBytes(32).toString('base64');
        logger.warn('Generated new SRT encryption key. Add to .env: SRT_ENCRYPTION_KEY=' + key);
        return key;
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

            // Start monitoring
            this.startMonitoring();

            logger.info('SRT Proxy Worker initialized');
            this.isRunning = true;

            // Start processing streams
            await this.processStreams();
        } catch (error) {
            logger.error('Failed to initialize worker:', error);
            process.exit(1);
        }
    }

    /**
     * Process streams that need SRT proxy
     */
    async processStreams() {
        while (this.isRunning) {
            try {
                // Get active streams that use SRT protocol
                const [streams] = await this.db.execute(`
                    SELECT
                        s.id,
                        s.name,
                        s.source_url,
                        s.proxy_settings,
                        s.encryption_settings,
                        s.max_connections,
                        COUNT(DISTINCT ds.id) as active_connections
                    FROM streams s
                    LEFT JOIN device_sessions ds ON ds.stream_id = s.id AND ds.is_active = 1
                    WHERE s.enabled = 1
                        AND s.stream_mode = 'proxy'
                        AND (s.source_url LIKE 'srt://%' OR s.proxy_settings->>'$.protocol' = 'srt')
                    GROUP BY s.id
                    HAVING active_connections < s.max_connections OR s.max_connections = 0
                `);

                for (const stream of streams) {
                    if (!activeProxies.has(stream.id)) {
                        await this.startProxy(stream);
                    }
                }

                // Check and clean up dead proxies
                await this.cleanupDeadProxies();

            } catch (error) {
                logger.error('Error processing streams:', error);
            }

            // Wait before next check
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }

    /**
     * Start SRT proxy for a stream (PROXY ONLY - NO TRANSCODING)
     */
    async startProxy(stream) {
        try {
            logger.info(`Starting SRT proxy for stream ${stream.id}: ${stream.name}`);

            const sourceUrl = new URL(stream.source_url);
            const proxySettings = JSON.parse(stream.proxy_settings || '{}');
            const encryptionSettings = JSON.parse(stream.encryption_settings || '{}');

            // Create SRT socket for source (upstream)
            const sourceSocket = new SRT.Socket();

            // Configure source connection
            sourceSocket.setSockOpt(SRT.SRTO_PASSPHRASE, encryptionSettings.passphrase || this.encryptionKey);
            sourceSocket.setSockOpt(SRT.SRTO_PBKEYLEN, encryptionSettings.keyLength || 32); // AES-256
            sourceSocket.setSockOpt(SRT.SRTO_LATENCY, proxySettings.latency || 1000); // 1 second latency
            sourceSocket.setSockOpt(SRT.SRTO_RCVBUF, proxySettings.bufferSize || 8192000); // 8MB buffer

            // Connect to source
            await sourceSocket.connect(sourceUrl.hostname, parseInt(sourceUrl.port || 9000));

            // Create listener for clients
            const listenerSocket = new SRT.Socket();
            const listenPort = this.proxyPort + stream.id;

            // Configure listener
            listenerSocket.setSockOpt(SRT.SRTO_PASSPHRASE, this.encryptionKey);
            listenerSocket.setSockOpt(SRT.SRTO_PBKEYLEN, 32); // AES-256
            listenerSocket.setSockOpt(SRT.SRTO_LATENCY, 1000);
            listenerSocket.setSockOpt(SRT.SRTO_MAXBW, proxySettings.maxBandwidth || 0); // 0 = unlimited

            // Start listening
            await listenerSocket.bind('0.0.0.0', listenPort);
            listenerSocket.listen(10);

            const proxyInfo = {
                streamId: stream.id,
                sourceSocket,
                listenerSocket,
                listenPort,
                clients: new Set(),
                stats: {
                    bytesReceived: 0,
                    bytesSent: 0,
                    packetsReceived: 0,
                    packetsSent: 0,
                    startTime: Date.now()
                }
            };

            activeProxies.set(stream.id, proxyInfo);

            // Accept client connections
            this.acceptClients(proxyInfo, stream);

            // Start data relay (PROXY ONLY - NO TRANSCODING)
            this.relayData(proxyInfo);

            // Update stream status
            await this.updateStreamStatus(stream.id, 'active', listenPort);

            logger.info(`SRT proxy started on port ${listenPort} for stream ${stream.id}`);

        } catch (error) {
            logger.error(`Failed to start proxy for stream ${stream.id}:`, error);
            await this.updateStreamStatus(stream.id, 'error', null, error.message);
        }
    }

    /**
     * Accept client connections with device validation
     */
    async acceptClients(proxyInfo, stream) {
        const { listenerSocket } = proxyInfo;

        listenerSocket.on('connection', async (clientSocket) => {
            try {
                // Get client info
                const clientAddr = clientSocket.getSockOpt(SRT.SRTO_PEERADDR);
                const streamId = clientSocket.getSockOpt(SRT.SRTO_STREAMID) || '';

                logger.info(`New client connection from ${clientAddr} for stream ${stream.id}`);

                // Parse stream ID for session token
                const sessionToken = streamId.split(':')[0];

                // Validate device session
                const isValid = await this.validateDeviceSession(sessionToken, stream.id, clientAddr);

                if (!isValid) {
                    logger.warn(`Invalid session for client ${clientAddr}, closing connection`);
                    clientSocket.close();
                    return;
                }

                // Add to active clients
                proxyInfo.clients.add({
                    socket: clientSocket,
                    address: clientAddr,
                    sessionToken,
                    connectedAt: Date.now(),
                    bytesReceived: 0,
                    bytesSent: 0
                });

                // Update connection count
                await this.updateConnectionCount(stream.id, proxyInfo.clients.size);

                // Handle client disconnect
                clientSocket.on('close', () => {
                    logger.info(`Client ${clientAddr} disconnected from stream ${stream.id}`);
                    proxyInfo.clients.forEach(client => {
                        if (client.address === clientAddr) {
                            proxyInfo.clients.delete(client);
                        }
                    });
                    this.updateConnectionCount(stream.id, proxyInfo.clients.size);
                });

            } catch (error) {
                logger.error('Error accepting client:', error);
                clientSocket.close();
            }
        });
    }

    /**
     * Relay data from source to clients (PROXY ONLY - NO TRANSCODING)
     */
    async relayData(proxyInfo) {
        const { sourceSocket, clients, stats } = proxyInfo;

        sourceSocket.on('data', (data) => {
            stats.bytesReceived += data.length;
            stats.packetsReceived++;

            // Relay to all connected clients (DIRECT PROXY - NO TRANSCODING)
            clients.forEach(client => {
                try {
                    client.socket.send(data);
                    client.bytesSent += data.length;
                    stats.bytesSent += data.length;
                    stats.packetsSent++;
                } catch (error) {
                    logger.error(`Error sending data to client ${client.address}:`, error);
                    // Remove failed client
                    client.socket.close();
                    clients.delete(client);
                }
            });
        });

        sourceSocket.on('error', (error) => {
            logger.error(`Source socket error for stream ${proxyInfo.streamId}:`, error);
            this.stopProxy(proxyInfo.streamId);
        });

        sourceSocket.on('close', () => {
            logger.warn(`Source disconnected for stream ${proxyInfo.streamId}`);
            this.stopProxy(proxyInfo.streamId);
        });
    }

    /**
     * Validate device session for stream access
     */
    async validateDeviceSession(sessionToken, streamId, clientIp) {
        try {
            // Check session in Redis cache first
            const cachedSession = await redis.get(`session:${sessionToken}`);
            if (cachedSession) {
                const session = JSON.parse(cachedSession);
                if (session.streamId === streamId && session.isValid) {
                    return true;
                }
            }

            // Validate in database
            const [sessions] = await this.db.execute(`
                SELECT
                    ds.id,
                    ds.device_binding_id,
                    ds.is_active,
                    db.subscription_id,
                    sub.max_concurrent_streams,
                    df.is_blocked
                FROM device_sessions ds
                JOIN device_bindings db ON db.id = ds.device_binding_id
                JOIN subscriptions sub ON sub.id = db.subscription_id
                JOIN device_fingerprints df ON df.id = db.device_fingerprint_id
                WHERE ds.session_token = ?
                    AND ds.is_active = 1
                    AND db.is_active = 1
                    AND df.is_blocked = 0
                LIMIT 1
            `, [sessionToken]);

            if (sessions.length === 0) {
                logger.warn(`Invalid session token: ${sessionToken}`);
                return false;
            }

            const session = sessions[0];

            // Check concurrent streams
            const [activeStreams] = await this.db.execute(`
                SELECT COUNT(*) as count
                FROM device_sessions
                WHERE device_binding_id = ?
                    AND is_active = 1
                    AND stream_id != ?
            `, [session.device_binding_id, streamId]);

            if (activeStreams[0].count >= session.max_concurrent_streams) {
                logger.warn(`Concurrent stream limit exceeded for session ${sessionToken}`);
                await this.logViolation(session.subscription_id, 'concurrent_limit', {
                    sessionToken,
                    streamId,
                    activeStreams: activeStreams[0].count,
                    limit: session.max_concurrent_streams
                });
                return false;
            }

            // Update session with stream
            await this.db.execute(`
                UPDATE device_sessions
                SET stream_id = ?,
                    ip_address = ?,
                    last_activity = NOW()
                WHERE session_token = ?
            `, [streamId, clientIp, sessionToken]);

            // Cache session
            await redis.setex(
                `session:${sessionToken}`,
                300, // 5 minutes
                JSON.stringify({
                    sessionId: session.id,
                    streamId,
                    isValid: true,
                    deviceBindingId: session.device_binding_id
                })
            );

            return true;

        } catch (error) {
            logger.error('Error validating device session:', error);
            return false;
        }
    }

    /**
     * Stop proxy for a stream
     */
    async stopProxy(streamId) {
        const proxyInfo = activeProxies.get(streamId);
        if (!proxyInfo) return;

        logger.info(`Stopping proxy for stream ${streamId}`);

        // Close all client connections
        proxyInfo.clients.forEach(client => {
            client.socket.close();
        });

        // Close source connection
        if (proxyInfo.sourceSocket) {
            proxyInfo.sourceSocket.close();
        }

        // Close listener
        if (proxyInfo.listenerSocket) {
            proxyInfo.listenerSocket.close();
        }

        // Remove from active proxies
        activeProxies.delete(streamId);

        // Update stream status
        await this.updateStreamStatus(streamId, 'stopped');

        // Log statistics
        const runtime = (Date.now() - proxyInfo.stats.startTime) / 1000;
        logger.info(`Stream ${streamId} proxy stats:`, {
            runtime: `${runtime}s`,
            bytesReceived: proxyInfo.stats.bytesReceived,
            bytesSent: proxyInfo.stats.bytesSent,
            packetsReceived: proxyInfo.stats.packetsReceived,
            packetsSent: proxyInfo.stats.packetsSent
        });
    }

    /**
     * Clean up dead proxies
     */
    async cleanupDeadProxies() {
        for (const [streamId, proxyInfo] of activeProxies) {
            // Check if proxy is still alive
            if (!proxyInfo.sourceSocket || proxyInfo.sourceSocket.readyState !== 'open') {
                logger.warn(`Dead proxy detected for stream ${streamId}`);
                await this.stopProxy(streamId);
            }
        }
    }

    /**
     * Update stream status in database
     */
    async updateStreamStatus(streamId, status, port = null, error = null) {
        try {
            await this.db.execute(`
                UPDATE streams
                SET proxy_status = ?,
                    proxy_port = ?,
                    proxy_error = ?,
                    last_checked = NOW()
                WHERE id = ?
            `, [status, port, error, streamId]);
        } catch (err) {
            logger.error('Error updating stream status:', err);
        }
    }

    /**
     * Update connection count
     */
    async updateConnectionCount(streamId, count) {
        try {
            await this.db.execute(`
                UPDATE streams
                SET current_connections = ?
                WHERE id = ?
            `, [count, streamId]);

            // Update Redis
            await redis.set(`stream:${streamId}:connections`, count);
        } catch (err) {
            logger.error('Error updating connection count:', err);
        }
    }

    /**
     * Log violation
     */
    async logViolation(subscriptionId, type, details) {
        try {
            await this.db.execute(`
                INSERT INTO device_violations (
                    subscription_id,
                    violation_type,
                    severity,
                    details,
                    ip_address,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            `, [
                subscriptionId,
                type,
                'high',
                JSON.stringify(details),
                details.clientIp || ''
            ]);
        } catch (err) {
            logger.error('Error logging violation:', err);
        }
    }

    /**
     * Start monitoring
     */
    startMonitoring() {
        // Monitor every 30 seconds
        setInterval(async () => {
            const stats = {
                activeProxies: activeProxies.size,
                totalClients: 0,
                totalBandwidth: 0
            };

            for (const [streamId, proxyInfo] of activeProxies) {
                stats.totalClients += proxyInfo.clients.size;
                const runtime = (Date.now() - proxyInfo.stats.startTime) / 1000;
                if (runtime > 0) {
                    stats.totalBandwidth += (proxyInfo.stats.bytesSent / runtime);
                }
            }

            logger.info('SRT Proxy Worker Stats:', stats);

            // Update Redis stats
            await redis.set('srt_proxy:stats', JSON.stringify(stats));

        }, 30000);
    }

    /**
     * Graceful shutdown
     */
    async shutdown() {
        logger.info('Shutting down SRT Proxy Worker...');
        this.isRunning = false;

        // Stop all proxies
        for (const streamId of activeProxies.keys()) {
            await this.stopProxy(streamId);
        }

        // Close database connection
        if (this.db) {
            await this.db.end();
        }

        // Close Redis connection
        redis.disconnect();

        logger.info('SRT Proxy Worker shutdown complete');
        process.exit(0);
    }
}

// Initialize and run worker
const worker = new SRTProxyWorker();

// Handle shutdown signals
process.on('SIGINT', () => worker.shutdown());
process.on('SIGTERM', () => worker.shutdown());

// Start worker
worker.init().catch(error => {
    logger.error('Failed to start SRT Proxy Worker:', error);
    process.exit(1);
});