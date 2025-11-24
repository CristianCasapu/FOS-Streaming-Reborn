#!/usr/bin/env node

/**
 * QUIC/HTTP3 Proxy Worker
 *
 * Next-generation streaming protocol implementation
 * Features:
 * - HTTP/3 over QUIC
 * - TLS 1.3 with ECH (Encrypted Client Hello)
 * - 0-RTT connection establishment
 * - Improved performance on lossy networks
 * - PROXY-ONLY mode (no transcoding)
 */

const { createQuicSocket } = require('net');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');
const Redis = require('ioredis');
const winston = require('winston');
const { promisify } = require('util');
require('dotenv').config({ path: '../.env' });

// Logger configuration
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: '../storage/logs/quic-proxy-error.log', level: 'error' }),
        new winston.transports.File({ filename: '../storage/logs/quic-proxy.log' }),
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

// Active QUIC connections
const activeConnections = new Map();

class QUICProxyWorker {
    constructor() {
        this.db = null;
        this.isRunning = false;
        this.quicPort = parseInt(process.env.QUIC_PORT || 4433);
        this.tlsConfig = null;
        this.echConfig = null;
        this.socket = null;
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

            // Load TLS 1.3 configuration
            await this.loadTLSConfig();

            // Setup ECH (Encrypted Client Hello)
            await this.setupECH();

            // Start QUIC server
            await this.startQUICServer();

            // Start monitoring
            this.startMonitoring();

            logger.info('QUIC Proxy Worker initialized on port ' + this.quicPort);
            this.isRunning = true;

        } catch (error) {
            logger.error('Failed to initialize QUIC worker:', error);
            process.exit(1);
        }
    }

    /**
     * Load TLS 1.3 configuration
     */
    async loadTLSConfig() {
        const certPath = process.env.TLS_CERT_PATH || '../certs/server.crt';
        const keyPath = process.env.TLS_KEY_PATH || '../certs/server.key';

        // Generate self-signed cert if not exists
        if (!fs.existsSync(certPath) || !fs.existsSync(keyPath)) {
            logger.info('Generating self-signed certificate for QUIC...');
            await this.generateSelfSignedCert(certPath, keyPath);
        }

        this.tlsConfig = {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
            alpn: ['h3', 'h3-29', 'h3-28', 'h3-27'], // HTTP/3 ALPN identifiers
            minVersion: 'TLSv1.3',
            maxVersion: 'TLSv1.3',
            ciphers: [
                'TLS_AES_128_GCM_SHA256',
                'TLS_AES_256_GCM_SHA384',
                'TLS_CHACHA20_POLY1305_SHA256'
            ].join(':'),
            ecdhCurve: 'X25519:P-256:P-384',
            sessionTimeout: 86400, // 24 hours for 0-RTT
            ticketKeys: crypto.randomBytes(48)
        };

        logger.info('TLS 1.3 configuration loaded');
    }

    /**
     * Setup ECH (Encrypted Client Hello)
     */
    async setupECH() {
        // Generate ECH keys
        const echKeyPair = crypto.generateKeyPairSync('x25519');

        this.echConfig = {
            version: 0xfe0d, // ECH draft version
            publicKey: echKeyPair.publicKey,
            privateKey: echKeyPair.privateKey,
            kemId: 0x0020, // X25519
            kdfId: 0x0001, // HKDF-SHA256
            aeadId: 0x0001, // AES-128-GCM
            maxNameLength: 255,
            publicName: process.env.ECH_PUBLIC_NAME || 'cloudflare.com',
            extensions: []
        };

        // Store ECH config in Redis for distribution
        const echConfigBase64 = Buffer.from(JSON.stringify({
            version: this.echConfig.version,
            publicKey: echKeyPair.publicKey.export({ type: 'spki', format: 'der' }).toString('base64'),
            publicName: this.echConfig.publicName
        })).toString('base64');

        await redis.set('ech:config', echConfigBase64);
        logger.info('ECH (Encrypted Client Hello) configured');
    }

    /**
     * Generate self-signed certificate
     */
    async generateSelfSignedCert(certPath, keyPath) {
        const { exec } = require('child_process');
        const execAsync = promisify(exec);

        const certDir = path.dirname(certPath);
        if (!fs.existsSync(certDir)) {
            fs.mkdirSync(certDir, { recursive: true });
        }

        const cmd = `openssl req -x509 -newkey rsa:4096 -keyout ${keyPath} -out ${certPath} -days 365 -nodes -subj "/CN=localhost"`;
        await execAsync(cmd);
        logger.info('Self-signed certificate generated');
    }

    /**
     * Start QUIC server
     */
    async startQUICServer() {
        try {
            // Create QUIC socket
            this.socket = createQuicSocket({
                endpoint: {
                    address: '0.0.0.0',
                    port: this.quicPort,
                    type: 'udp4'
                },
                server: {
                    ...this.tlsConfig,
                    validateAddress: true,
                    statelessReset: true,
                    maxConnectionsPerHost: 100,
                    qlog: process.env.ENABLE_QLOG === 'true'
                },
                retryToken: true,
                maxConnections: 1000,
                maxConnectionsPerIp: 10,
                idleTimeout: 30000,
                maxStreamDataBidiLocal: 1048576, // 1MB
                maxStreamDataBidiRemote: 1048576,
                maxStreamDataUni: 1048576,
                maxData: 10485760, // 10MB
                maxStreamsBidi: 100,
                maxStreamsUni: 100
            });

            // Handle new sessions
            this.socket.on('session', async (session) => {
                const clientAddr = session.remoteAddress;
                logger.info(`New QUIC session from ${clientAddr.address}:${clientAddr.port}`);

                // Store session
                const sessionId = crypto.randomBytes(16).toString('hex');
                activeConnections.set(sessionId, {
                    session,
                    clientAddr,
                    streams: new Map(),
                    authenticated: false,
                    deviceSession: null,
                    stats: {
                        bytesReceived: 0,
                        bytesSent: 0,
                        streamsOpened: 0,
                        startTime: Date.now()
                    }
                });

                // Handle session events
                session.on('secure', () => {
                    logger.info(`Session ${sessionId} secured with ${session.cipher}`);
                    this.handleSecureSession(sessionId, session);
                });

                session.on('stream', (stream) => {
                    this.handleStream(sessionId, stream);
                });

                session.on('close', () => {
                    logger.info(`Session ${sessionId} closed`);
                    this.cleanupSession(sessionId);
                });

                session.on('error', (error) => {
                    logger.error(`Session ${sessionId} error:`, error);
                    this.cleanupSession(sessionId);
                });
            });

            // Start listening
            await this.socket.listen();
            logger.info(`QUIC server listening on port ${this.quicPort}`);

        } catch (error) {
            logger.error('Failed to start QUIC server:', error);
            throw error;
        }
    }

    /**
     * Handle secure session establishment
     */
    async handleSecureSession(sessionId, session) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        // Check for 0-RTT data
        if (session.early_data_accepted) {
            logger.info(`Session ${sessionId} using 0-RTT`);
            connection.stats.zeroRtt = true;
        }

        // Extract ALPN protocol
        const alpn = session.alpnProtocol;
        logger.info(`Session ${sessionId} ALPN: ${alpn}`);

        if (alpn === 'h3' || alpn.startsWith('h3-')) {
            connection.protocol = 'http3';
        }

        // Store session info
        await redis.setex(
            `quic:session:${sessionId}`,
            300, // 5 minutes
            JSON.stringify({
                clientAddr: connection.clientAddr,
                protocol: connection.protocol,
                cipher: session.cipher,
                startTime: connection.stats.startTime
            })
        );
    }

    /**
     * Handle incoming stream
     */
    async handleStream(sessionId, stream) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        connection.stats.streamsOpened++;
        const streamId = stream.id;

        logger.info(`New stream ${streamId} on session ${sessionId}`);

        // Store stream reference
        connection.streams.set(streamId, stream);

        // Handle HTTP/3 frames
        stream.on('data', async (data) => {
            connection.stats.bytesReceived += data.length;
            await this.handleHTTP3Frame(sessionId, streamId, data);
        });

        stream.on('end', () => {
            logger.info(`Stream ${streamId} ended`);
            connection.streams.delete(streamId);
        });

        stream.on('error', (error) => {
            logger.error(`Stream ${streamId} error:`, error);
            connection.streams.delete(streamId);
        });
    }

    /**
     * Handle HTTP/3 frame
     */
    async handleHTTP3Frame(sessionId, streamId, data) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        const stream = connection.streams.get(streamId);
        if (!stream) return;

        try {
            // Parse HTTP/3 frame (simplified)
            const frame = this.parseHTTP3Frame(data);

            if (frame.type === 'HEADERS') {
                await this.handleHTTP3Request(sessionId, streamId, frame.headers);
            } else if (frame.type === 'DATA') {
                await this.handleHTTP3Data(sessionId, streamId, frame.data);
            }

        } catch (error) {
            logger.error('Error handling HTTP/3 frame:', error);
            stream.destroy();
        }
    }

    /**
     * Parse HTTP/3 frame (simplified implementation)
     */
    parseHTTP3Frame(data) {
        // Simplified HTTP/3 frame parsing
        // In production, use a proper HTTP/3 library

        const frameType = data[0];
        const frameLength = data.readUIntBE(1, 3);
        const frameData = data.slice(4, 4 + frameLength);

        switch (frameType) {
            case 0x00: // DATA frame
                return { type: 'DATA', data: frameData };
            case 0x01: // HEADERS frame
                return { type: 'HEADERS', headers: this.parseHeaders(frameData) };
            case 0x03: // CANCEL_PUSH
            case 0x04: // SETTINGS
            case 0x05: // PUSH_PROMISE
            case 0x07: // GOAWAY
            case 0x0d: // MAX_PUSH_ID
                return { type: 'CONTROL', data: frameData };
            default:
                return { type: 'UNKNOWN', data: frameData };
        }
    }

    /**
     * Parse headers (simplified QPACK)
     */
    parseHeaders(data) {
        // Simplified header parsing
        // In production, use proper QPACK decoder
        const headers = {};

        // Parse pseudo-headers and regular headers
        // This is a simplified version
        const headerStr = data.toString();
        const lines = headerStr.split('\r\n');

        for (const line of lines) {
            const [key, value] = line.split(': ');
            if (key && value) {
                headers[key.toLowerCase()] = value;
            }
        }

        return headers;
    }

    /**
     * Handle HTTP/3 request
     */
    async handleHTTP3Request(sessionId, streamId, headers) {
        const connection = activeConnections.get(sessionId);
        const stream = connection.streams.get(streamId);

        logger.info(`HTTP/3 request: ${headers[':method']} ${headers[':path']}`);

        // Handle authentication
        if (headers[':path'] === '/auth') {
            await this.handleAuthentication(sessionId, streamId, headers);
            return;
        }

        // Handle streaming request
        if (headers[':path'] && headers[':path'].startsWith('/stream/')) {
            await this.handleStreamingRequest(sessionId, streamId, headers);
            return;
        }

        // Default response
        this.sendHTTP3Response(stream, 404, { 'content-type': 'text/plain' }, 'Not Found');
    }

    /**
     * Handle authentication
     */
    async handleAuthentication(sessionId, streamId, headers) {
        const connection = activeConnections.get(sessionId);
        const stream = connection.streams.get(streamId);

        try {
            // Extract session token from headers
            const authorization = headers['authorization'];
            if (!authorization || !authorization.startsWith('Bearer ')) {
                this.sendHTTP3Response(stream, 401, {}, 'Unauthorized');
                return;
            }

            const sessionToken = authorization.substring(7);

            // Validate device session
            const [sessions] = await this.db.execute(`
                SELECT
                    ds.id,
                    ds.device_binding_id,
                    db.subscription_id
                FROM device_sessions ds
                JOIN device_bindings db ON db.id = ds.device_binding_id
                WHERE ds.session_token = ?
                    AND ds.is_active = 1
                LIMIT 1
            `, [sessionToken]);

            if (sessions.length === 0) {
                this.sendHTTP3Response(stream, 401, {}, 'Invalid session');
                return;
            }

            // Store authentication
            connection.authenticated = true;
            connection.deviceSession = sessions[0];

            // Send success response with 0-RTT token for future connections
            const zeroRttToken = crypto.randomBytes(32).toString('base64');
            await redis.setex(`0rtt:${zeroRttToken}`, 86400, sessionId);

            this.sendHTTP3Response(stream, 200, {
                'content-type': 'application/json',
                'x-0rtt-token': zeroRttToken
            }, JSON.stringify({ success: true, sessionId }));

        } catch (error) {
            logger.error('Authentication error:', error);
            this.sendHTTP3Response(stream, 500, {}, 'Internal Server Error');
        }
    }

    /**
     * Handle streaming request (PROXY-ONLY)
     */
    async handleStreamingRequest(sessionId, streamId, headers) {
        const connection = activeConnections.get(sessionId);
        const stream = connection.streams.get(streamId);

        if (!connection.authenticated) {
            this.sendHTTP3Response(stream, 401, {}, 'Unauthorized');
            return;
        }

        try {
            // Extract stream ID from path
            const pathParts = headers[':path'].split('/');
            const streamIdRequested = parseInt(pathParts[2]);

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
            `, [streamIdRequested]);

            if (streams.length === 0) {
                this.sendHTTP3Response(stream, 404, {}, 'Stream not found');
                return;
            }

            const streamConfig = streams[0];

            // Send response headers
            this.sendHTTP3Response(stream, 200, {
                'content-type': 'application/octet-stream',
                'cache-control': 'no-cache',
                'x-stream-id': streamConfig.id.toString(),
                'x-stream-name': streamConfig.name
            });

            // Start proxying (PROXY-ONLY - NO TRANSCODING)
            await this.startProxy(stream, streamConfig, connection);

        } catch (error) {
            logger.error('Streaming error:', error);
            this.sendHTTP3Response(stream, 500, {}, 'Streaming Error');
        }
    }

    /**
     * Start proxy streaming
     */
    async startProxy(clientStream, streamConfig, connection) {
        // This would connect to the actual stream source and relay data
        // For now, this is a placeholder
        logger.info(`Starting QUIC proxy for stream ${streamConfig.id}`);

        // Update stats
        connection.stats.streaming = true;
        connection.stats.streamId = streamConfig.id;

        // In production, this would:
        // 1. Connect to source stream
        // 2. Relay data without transcoding
        // 3. Handle disconnections and errors
    }

    /**
     * Send HTTP/3 response
     */
    sendHTTP3Response(stream, status, headers = {}, body = '') {
        // Build HTTP/3 response (simplified)
        const responseHeaders = {
            ':status': status.toString(),
            'server': 'FOS-QUIC/1.0',
            'date': new Date().toUTCString(),
            ...headers
        };

        // Encode headers (simplified - use proper QPACK in production)
        const headerData = Buffer.from(
            Object.entries(responseHeaders)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\r\n')
        );

        // Send HEADERS frame
        const headersFrame = Buffer.concat([
            Buffer.from([0x01]), // HEADERS frame type
            Buffer.from([0x00, 0x00, headerData.length]), // Frame length
            headerData
        ]);

        stream.write(headersFrame);

        // Send DATA frame if body exists
        if (body) {
            const bodyData = Buffer.from(body);
            const dataFrame = Buffer.concat([
                Buffer.from([0x00]), // DATA frame type
                Buffer.from([0x00, 0x00, bodyData.length]), // Frame length
                bodyData
            ]);

            stream.write(dataFrame);
        }

        stream.end();
    }

    /**
     * Cleanup session
     */
    async cleanupSession(sessionId) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        // Close all streams
        for (const stream of connection.streams.values()) {
            stream.destroy();
        }

        // Remove from active connections
        activeConnections.delete(sessionId);

        // Clean Redis
        await redis.del(`quic:session:${sessionId}`);

        // Log statistics
        const duration = (Date.now() - connection.stats.startTime) / 1000;
        logger.info(`Session ${sessionId} stats:`, {
            duration: `${duration}s`,
            bytesReceived: connection.stats.bytesReceived,
            bytesSent: connection.stats.bytesSent,
            streamsOpened: connection.stats.streamsOpened,
            zeroRtt: connection.stats.zeroRtt || false
        });
    }

    /**
     * Handle HTTP/3 data
     */
    async handleHTTP3Data(sessionId, streamId, data) {
        const connection = activeConnections.get(sessionId);
        if (!connection) return;

        // Process incoming data from client
        // In streaming context, this might be control messages
        logger.debug(`Received ${data.length} bytes on stream ${streamId}`);
    }

    /**
     * Start monitoring
     */
    startMonitoring() {
        setInterval(() => {
            const stats = {
                activeSessions: activeConnections.size,
                totalStreams: 0,
                authenticatedSessions: 0
            };

            for (const [sessionId, connection] of activeConnections) {
                stats.totalStreams += connection.streams.size;
                if (connection.authenticated) {
                    stats.authenticatedSessions++;
                }
            }

            logger.info('QUIC Proxy Stats:', stats);

            // Update Redis stats
            redis.set('quic:stats', JSON.stringify(stats));

        }, 30000); // Every 30 seconds
    }

    /**
     * Graceful shutdown
     */
    async shutdown() {
        logger.info('Shutting down QUIC Proxy Worker...');
        this.isRunning = false;

        // Close all sessions
        for (const sessionId of activeConnections.keys()) {
            await this.cleanupSession(sessionId);
        }

        // Close QUIC socket
        if (this.socket) {
            await this.socket.close();
        }

        // Close database connection
        if (this.db) {
            await this.db.end();
        }

        // Close Redis connection
        redis.disconnect();

        logger.info('QUIC Proxy Worker shutdown complete');
        process.exit(0);
    }
}

// Initialize and run worker
const worker = new QUICProxyWorker();

// Handle shutdown signals
process.on('SIGINT', () => worker.shutdown());
process.on('SIGTERM', () => worker.shutdown());

// Start worker
worker.init().catch(error => {
    logger.error('Failed to start QUIC Proxy Worker:', error);
    process.exit(1);
});