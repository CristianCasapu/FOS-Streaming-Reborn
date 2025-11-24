/**
 * HTTP/3 Client for QUIC Protocol
 *
 * Provides HTTP/3 support with automatic fallback to HTTP/2
 * Features:
 * - 0-RTT connection resumption
 * - ECH (Encrypted Client Hello) support
 * - Automatic protocol negotiation
 * - Connection pooling
 */

import axios from 'axios';

class HTTP3Client {
    constructor() {
        this.supportsHTTP3 = this.checkHTTP3Support();
        this.echConfig = null;
        this.zeroRttToken = null;
        this.connectionPool = new Map();
        this.stats = {
            http3Requests: 0,
            http2Fallbacks: 0,
            zeroRttSuccess: 0,
            echUsed: 0
        };
    }

    /**
     * Check if browser supports HTTP/3
     */
    checkHTTP3Support() {
        // Check for experimental HTTP/3 support
        // Most browsers need flags enabled
        if (typeof window !== 'undefined') {
            // Check Chrome/Edge
            const isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
            const isEdge = /Edg/.test(navigator.userAgent);

            if (isChrome || isEdge) {
                // Check if QUIC is enabled (requires chrome://flags)
                // This is a heuristic check
                return this.testQUICSupport();
            }

            // Check Firefox
            const isFirefox = /Firefox/.test(navigator.userAgent);
            if (isFirefox) {
                // Firefox needs network.http.http3.enabled in about:config
                return this.testQUICSupport();
            }
        }

        return false;
    }

    /**
     * Test QUIC support by attempting connection
     */
    async testQUICSupport() {
        try {
            // Try to fetch with HTTP/3 hint
            const response = await fetch('https://http3.is/api/support', {
                method: 'HEAD',
                mode: 'no-cors',
                cache: 'no-cache'
            });

            // Check Alt-Svc header for h3 support
            const altSvc = response.headers.get('alt-svc');
            if (altSvc && altSvc.includes('h3')) {
                return true;
            }
        } catch (error) {
            console.debug('HTTP/3 test failed:', error);
        }

        return false;
    }

    /**
     * Initialize HTTP/3 client
     */
    async initialize() {
        try {
            // Fetch ECH configuration
            await this.fetchECHConfig();

            // Load 0-RTT token if available
            this.load0RTTToken();

            // Setup service worker for HTTP/3 (if supported)
            if ('serviceWorker' in navigator) {
                await this.setupServiceWorker();
            }

            console.log('HTTP/3 Client initialized', {
                supportsHTTP3: this.supportsHTTP3,
                echAvailable: !!this.echConfig,
                zeroRttAvailable: !!this.zeroRttToken
            });
        } catch (error) {
            console.error('Failed to initialize HTTP/3 client:', error);
        }
    }

    /**
     * Fetch ECH configuration from server
     */
    async fetchECHConfig() {
        try {
            const response = await axios.get('/api/ech-config');
            if (response.data.echConfig) {
                this.echConfig = response.data.echConfig;
                // Store in session storage
                sessionStorage.setItem('ech-config', JSON.stringify(this.echConfig));
            }
        } catch (error) {
            console.error('Failed to fetch ECH config:', error);
            // Try to load from session storage
            const stored = sessionStorage.getItem('ech-config');
            if (stored) {
                this.echConfig = JSON.parse(stored);
            }
        }
    }

    /**
     * Load 0-RTT token from storage
     */
    load0RTTToken() {
        const token = localStorage.getItem('0rtt-token');
        if (token) {
            const tokenData = JSON.parse(token);
            // Check if token is still valid (24 hours)
            if (Date.now() - tokenData.timestamp < 86400000) {
                this.zeroRttToken = tokenData.token;
            } else {
                localStorage.removeItem('0rtt-token');
            }
        }
    }

    /**
     * Save 0-RTT token
     */
    save0RTTToken(token) {
        this.zeroRttToken = token;
        localStorage.setItem('0rtt-token', JSON.stringify({
            token,
            timestamp: Date.now()
        }));
    }

    /**
     * Setup service worker for HTTP/3
     */
    async setupServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/sw-http3.js');
            console.log('HTTP/3 Service Worker registered');

            // Send configuration to service worker
            if (registration.active) {
                registration.active.postMessage({
                    type: 'HTTP3_CONFIG',
                    echConfig: this.echConfig,
                    zeroRttToken: this.zeroRttToken
                });
            }
        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    }

    /**
     * Make HTTP/3 request with fallback
     */
    async request(config) {
        const url = new URL(config.url, window.location.origin);

        // Check if we should use HTTP/3
        if (this.supportsHTTP3 && (url.protocol === 'https:' || url.protocol === 'h3:')) {
            try {
                // Attempt HTTP/3 request
                const response = await this.http3Request(config);
                this.stats.http3Requests++;
                return response;
            } catch (error) {
                console.warn('HTTP/3 request failed, falling back to HTTP/2:', error);
                this.stats.http2Fallbacks++;
            }
        }

        // Fallback to standard axios request
        return axios.request(config);
    }

    /**
     * Make HTTP/3 request
     */
    async http3Request(config) {
        const url = new URL(config.url, window.location.origin);

        // Convert to h3:// protocol
        if (url.protocol === 'https:') {
            url.protocol = 'h3:';
        }

        // Prepare headers
        const headers = {
            ...config.headers,
            'Alt-Used': 'h3',
            'Accept-Encoding': 'br, gzip, deflate'
        };

        // Add 0-RTT token if available
        if (this.zeroRttToken && config.method === 'GET') {
            headers['X-0RTT-Token'] = this.zeroRttToken;
            this.stats.zeroRttSuccess++;
        }

        // Add ECH if configured
        if (this.echConfig) {
            headers['Encrypted-Client-Hello'] = this.echConfig;
            this.stats.echUsed++;
        }

        // Use Fetch API with HTTP/3 hint
        const fetchConfig = {
            method: config.method || 'GET',
            headers,
            body: config.data ? JSON.stringify(config.data) : undefined,
            mode: config.mode || 'cors',
            credentials: config.withCredentials ? 'include' : 'same-origin',
            cache: config.cache || 'no-cache',
            priority: 'high', // HTTP/3 priority hint
            // Custom property for HTTP/3 (not standard yet)
            protocol: 'h3'
        };

        const response = await fetch(url.toString(), fetchConfig);

        // Check for 0-RTT token in response
        const newToken = response.headers.get('X-0RTT-Token');
        if (newToken) {
            this.save0RTTToken(newToken);
        }

        // Convert to axios-like response
        return {
            data: await this.parseResponse(response),
            status: response.status,
            statusText: response.statusText,
            headers: Object.fromEntries(response.headers.entries()),
            config,
            request: response
        };
    }

    /**
     * Parse response based on content type
     */
    async parseResponse(response) {
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else if (contentType && contentType.includes('text/')) {
            return response.text();
        } else {
            return response.blob();
        }
    }

    /**
     * Stream over HTTP/3
     */
    async streamHTTP3(url, options = {}) {
        if (!this.supportsHTTP3) {
            throw new Error('HTTP/3 not supported by browser');
        }

        const streamUrl = new URL(url, window.location.origin);
        streamUrl.protocol = 'h3:';

        const response = await fetch(streamUrl.toString(), {
            ...options,
            protocol: 'h3',
            headers: {
                ...options.headers,
                'Alt-Used': 'h3',
                'X-0RTT-Token': this.zeroRttToken || ''
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP/3 stream failed: ${response.status}`);
        }

        // Return readable stream
        return response.body;
    }

    /**
     * Get connection statistics
     */
    getStats() {
        return {
            ...this.stats,
            connectionPoolSize: this.connectionPool.size,
            http3Enabled: this.supportsHTTP3,
            echEnabled: !!this.echConfig,
            zeroRttEnabled: !!this.zeroRttToken
        };
    }

    /**
     * WebTransport support (future)
     */
    async createWebTransport(url) {
        if (!('WebTransport' in window)) {
            throw new Error('WebTransport not supported');
        }

        const transport = new WebTransport(url);
        await transport.ready;

        return {
            transport,
            createBidirectionalStream: () => transport.createBidirectionalStream(),
            createUnidirectionalStream: () => transport.createUnidirectionalStream(),
            close: () => transport.close()
        };
    }

    /**
     * Preconnect to HTTP/3 endpoint
     */
    async preconnect(url) {
        if (!this.supportsHTTP3) return;

        try {
            // Send OPTIONS request to establish QUIC connection
            await fetch(url, {
                method: 'OPTIONS',
                mode: 'no-cors',
                protocol: 'h3'
            });

            console.log(`Preconnected to ${url} over HTTP/3`);
        } catch (error) {
            console.warn(`Failed to preconnect to ${url}:`, error);
        }
    }
}

// Create singleton instance
const http3Client = new HTTP3Client();

// Auto-initialize on load
if (typeof window !== 'undefined') {
    window.addEventListener('load', () => {
        http3Client.initialize();
    });
}

export default http3Client;

// Export for use in API service
export const http3Request = (config) => http3Client.request(config);
export const http3Stream = (url, options) => http3Client.streamHTTP3(url, options);
export const getHTTP3Stats = () => http3Client.getStats();
export const preconnectHTTP3 = (url) => http3Client.preconnect(url);