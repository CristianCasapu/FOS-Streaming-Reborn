/**
 * Device Fingerprinting Library
 * Generates unique device identifiers for anti-sharing protection
 */

export class DeviceFingerprinter {
    constructor() {
        this.fingerprint = null;
        this.components = {};
    }

    /**
     * Generate complete device fingerprint
     */
    async generateFingerprint() {
        try {
            this.components = {
                // Basic device info
                timezone: this.getTimezone(),
                screen: this.getScreenInfo(),
                colorDepth: screen.colorDepth,
                platform: navigator.platform || 'unknown',
                languages: navigator.languages || ['en'],

                // Hardware fingerprints
                hardwareConcurrency: navigator.hardwareConcurrency || 0,
                deviceMemory: navigator.deviceMemory || 0,
                maxTouchPoints: navigator.maxTouchPoints || 0,

                // Canvas fingerprint
                canvasFingerprint: await this.getCanvasFingerprint(),

                // WebGL fingerprint
                webglFingerprint: await this.getWebGLFingerprint(),

                // Audio fingerprint
                audioFingerprint: await this.getAudioFingerprint(),

                // Font detection
                fonts: await this.getInstalledFonts(),

                // Plugin detection
                plugins: this.getPlugins(),

                // Additional entropy
                doNotTrack: navigator.doNotTrack || 'unspecified',
                cookieEnabled: navigator.cookieEnabled,

                // Network info
                connectionType: this.getConnectionType(),

                // Browser features
                webrtc: await this.getWebRTCFingerprint(),

                // Timestamp for entropy
                timestamp: Date.now()
            };

            // Generate unique hash
            this.fingerprint = await this.hashComponents(this.components);

            return {
                deviceId: this.fingerprint,
                components: this.components
            };
        } catch (error) {
            console.error('Error generating fingerprint:', error);
            // Return fallback fingerprint
            return {
                deviceId: this.generateFallbackFingerprint(),
                components: this.components
            };
        }
    }

    /**
     * Get timezone info
     */
    getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
            return new Date().getTimezoneOffset();
        }
    }

    /**
     * Get screen information
     */
    getScreenInfo() {
        return `${screen.width}x${screen.height}x${screen.colorDepth}`;
    }

    /**
     * Generate canvas fingerprint
     */
    async getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = 280;
            canvas.height = 60;

            // Draw unique patterns
            ctx.fillStyle = '#f0f0f0';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Text with various styles
            ctx.fillStyle = '#130f40';
            ctx.font = '14px Arial';
            ctx.fillText('FOS-Streaming 🔒 Device Lock ∑∆', 10, 20);

            ctx.font = 'italic 16px Times';
            ctx.fillStyle = '#6c5ce7';
            ctx.fillText('Fingerprint αβγδε', 10, 40);

            // Geometric shapes
            ctx.beginPath();
            ctx.arc(200, 30, 20, 0, Math.PI * 2, true);
            ctx.closePath();
            ctx.fill();

            // Get data URL
            const dataURL = canvas.toDataURL();

            // Hash the result
            return await this.hashString(dataURL);
        } catch (error) {
            console.error('Canvas fingerprint error:', error);
            return 'canvas_blocked';
        }
    }

    /**
     * Generate WebGL fingerprint
     */
    async getWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

            if (!gl) {
                return 'webgl_not_supported';
            }

            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            const vendor = debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR);
            const renderer = debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);

            // Get additional WebGL parameters
            const params = {
                vendor: vendor,
                renderer: renderer,
                version: gl.getParameter(gl.VERSION),
                shadingLanguageVersion: gl.getParameter(gl.SHADING_LANGUAGE_VERSION),
                maxTextureSize: gl.getParameter(gl.MAX_TEXTURE_SIZE),
                maxVertexAttribs: gl.getParameter(gl.MAX_VERTEX_ATTRIBS),
                maxVertexTextureImageUnits: gl.getParameter(gl.MAX_VERTEX_TEXTURE_IMAGE_UNITS),
                maxVaryingVectors: gl.getParameter(gl.MAX_VARYING_VECTORS),
                maxFragmentUniformVectors: gl.getParameter(gl.MAX_FRAGMENT_UNIFORM_VECTORS),
                redBits: gl.getParameter(gl.RED_BITS),
                greenBits: gl.getParameter(gl.GREEN_BITS),
                blueBits: gl.getParameter(gl.BLUE_BITS),
                alphaBits: gl.getParameter(gl.ALPHA_BITS),
                depthBits: gl.getParameter(gl.DEPTH_BITS),
                stencilBits: gl.getParameter(gl.STENCIL_BITS),
            };

            return await this.hashString(JSON.stringify(params));
        } catch (error) {
            console.error('WebGL fingerprint error:', error);
            return 'webgl_error';
        }
    }

    /**
     * Generate audio fingerprint
     */
    async getAudioFingerprint() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                return 'audio_not_supported';
            }

            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const analyser = context.createAnalyser();
            const gain = context.createGain();
            const scriptProcessor = context.createScriptProcessor(4096, 1, 1);

            gain.gain.value = 0; // Mute
            oscillator.connect(analyser);
            analyser.connect(scriptProcessor);
            scriptProcessor.connect(gain);
            gain.connect(context.destination);

            oscillator.start(0);

            return new Promise((resolve) => {
                let fingerprint = [];

                scriptProcessor.onaudioprocess = (event) => {
                    const output = event.inputBuffer.getChannelData(0);

                    // Get a sample of the audio data
                    for (let i = 0; i < output.length; i += 100) {
                        fingerprint.push(output[i]);
                    }

                    if (fingerprint.length > 30) {
                        oscillator.stop();
                        scriptProcessor.disconnect();
                        analyser.disconnect();
                        gain.disconnect();

                        // Hash the audio fingerprint
                        this.hashString(fingerprint.join(',')).then(resolve);
                    }
                };

                // Fallback timeout
                setTimeout(() => {
                    resolve('audio_timeout');
                }, 1000);
            });
        } catch (error) {
            console.error('Audio fingerprint error:', error);
            return 'audio_error';
        }
    }

    /**
     * Detect installed fonts
     */
    async getInstalledFonts() {
        const testFonts = [
            'Arial', 'Arial Black', 'Comic Sans MS', 'Courier New',
            'Georgia', 'Impact', 'Times New Roman', 'Trebuchet MS',
            'Verdana', 'Helvetica', 'Tahoma', 'Monaco', 'Calibri',
            'Cambria', 'Consolas'
        ];

        const baseFonts = ['monospace', 'sans-serif', 'serif'];
        const testString = 'mmmmmmmmmmlli';
        const testSize = '72px';

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        const detectFont = (font) => {
            const baseWidths = {};

            // Measure base font widths
            baseFonts.forEach((baseFont) => {
                ctx.font = `${testSize} ${baseFont}`;
                baseWidths[baseFont] = ctx.measureText(testString).width;
            });

            // Check if test font differs from base fonts
            return baseFonts.some((baseFont) => {
                ctx.font = `${testSize} '${font}', ${baseFont}`;
                const width = ctx.measureText(testString).width;
                return width !== baseWidths[baseFont];
            });
        };

        return testFonts.filter(detectFont);
    }

    /**
     * Get browser plugins
     */
    getPlugins() {
        const plugins = [];

        if (navigator.plugins) {
            for (let i = 0; i < navigator.plugins.length; i++) {
                plugins.push({
                    name: navigator.plugins[i].name,
                    description: navigator.plugins[i].description,
                    filename: navigator.plugins[i].filename
                });
            }
        }

        return plugins;
    }

    /**
     * Get connection type
     */
    getConnectionType() {
        if (navigator.connection) {
            return {
                effectiveType: navigator.connection.effectiveType,
                downlink: navigator.connection.downlink,
                rtt: navigator.connection.rtt,
                saveData: navigator.connection.saveData
            };
        }
        return 'unknown';
    }

    /**
     * Get WebRTC fingerprint
     */
    async getWebRTCFingerprint() {
        try {
            const pc = new RTCPeerConnection({ iceServers: [] });
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            // Get local IPs from ICE candidates
            const ips = [];

            return new Promise((resolve) => {
                pc.onicecandidate = (event) => {
                    if (!event.candidate) {
                        pc.close();
                        resolve(ips.join(','));
                        return;
                    }

                    const candidate = event.candidate.candidate;
                    const ipRegex = /([0-9]{1,3}\.){3}[0-9]{1,3}/;
                    const match = candidate.match(ipRegex);

                    if (match && !ips.includes(match[0])) {
                        ips.push(match[0]);
                    }
                };

                // Timeout fallback
                setTimeout(() => {
                    pc.close();
                    resolve('webrtc_timeout');
                }, 1000);
            });
        } catch (error) {
            return 'webrtc_error';
        }
    }

    /**
     * Hash string using Web Crypto API
     */
    async hashString(str) {
        try {
            const encoder = new TextEncoder();
            const data = encoder.encode(str);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        } catch {
            // Fallback to simple hash
            return this.simpleHash(str);
        }
    }

    /**
     * Hash components object
     */
    async hashComponents(components) {
        const str = JSON.stringify(components);
        return await this.hashString(str);
    }

    /**
     * Simple hash fallback
     */
    simpleHash(str) {
        let hash = 0;
        if (str.length === 0) return hash.toString();

        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }

        return Math.abs(hash).toString(16);
    }

    /**
     * Generate fallback fingerprint
     */
    generateFallbackFingerprint() {
        const fallbackData = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            screenResolution: this.getScreenInfo(),
            timezone: this.getTimezone(),
            timestamp: Date.now(),
            random: Math.random()
        };

        return this.simpleHash(JSON.stringify(fallbackData));
    }

    /**
     * Store fingerprint in local storage
     */
    storeFingerprint(fingerprint) {
        try {
            localStorage.setItem('fos_device_fingerprint', JSON.stringify({
                deviceId: fingerprint.deviceId,
                timestamp: Date.now()
            }));
        } catch {
            // Storage blocked
        }
    }

    /**
     * Retrieve stored fingerprint
     */
    getStoredFingerprint() {
        try {
            const stored = localStorage.getItem('fos_device_fingerprint');
            if (stored) {
                const data = JSON.parse(stored);
                // Check if fingerprint is not too old (7 days)
                if (Date.now() - data.timestamp < 7 * 24 * 60 * 60 * 1000) {
                    return data.deviceId;
                }
            }
        } catch {
            // Storage blocked
        }
        return null;
    }

    /**
     * Validate fingerprint consistency
     */
    async validateFingerprint() {
        const stored = this.getStoredFingerprint();
        const current = await this.generateFingerprint();

        if (stored && stored !== current.deviceId) {
            // Fingerprint changed - possible evasion attempt
            return {
                valid: false,
                reason: 'fingerprint_mismatch',
                stored: stored,
                current: current.deviceId
            };
        }

        // Store new fingerprint
        this.storeFingerprint(current);

        return {
            valid: true,
            fingerprint: current
        };
    }
}

// Export singleton instance
export default new DeviceFingerprinter();