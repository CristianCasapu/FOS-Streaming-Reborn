# FOS-Streaming v70 - Comprehensive Secure Streaming Transmission Plan

**Project**: FOS-Streaming-v70 Security Enhancement
**Target**: Maximum Security & Privacy for Video Streaming
**Date**: 2025-11-21
**Status**: Planning Phase

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Security Architecture Overview](#security-architecture-overview)
3. [Layer 1: Protocol Security](#layer-1-protocol-security)
4. [Layer 2: Traffic Obfuscation](#layer-2-traffic-obfuscation)
5. [Layer 3: MITM Detection](#layer-3-mitm-detection)
6. [Implementation Roadmap](#implementation-roadmap)
7. [Technical Specifications](#technical-specifications)
8. [Deployment Guide](#deployment-guide)
9. [Testing & Validation](#testing--validation)
10. [Maintenance & Monitoring](#maintenance--monitoring)

---

## Executive Summary

This plan implements a **multi-layered security architecture** for FOS-Streaming v70, addressing both the **protocol layer** (how video is packaged) and the **tunnel layer** (how traffic is hidden from ISPs and surveillance).

### Security Goals
✅ **Encryption**: End-to-end encryption of all streaming traffic
✅ **Privacy**: Hide streaming activity from ISP detection and throttling
✅ **Integrity**: Prevent man-in-the-middle (MITM) attacks
✅ **Reliability**: Maintain low-latency, high-quality streaming
✅ **Obfuscation**: Make streaming traffic indistinguishable from normal web browsing

### Target Threat Model
- **ISP Throttling**: Preventing ISPs from detecting and slowing video streams
- **Deep Packet Inspection (DPI)**: Evading traffic analysis and classification
- **MITM Attacks**: Detecting and preventing traffic interception
- **Censorship**: Bypassing geographic or network restrictions
- **Traffic Analysis**: Preventing behavioral pattern recognition

---

## Security Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                           │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Layer 3: MITM Detection & Monitoring                       │
│  ├── Certificate Validation                                  │
│  ├── TLS Alert Analysis                                      │
│  └── Automated Threat Detection                              │
│                                                              │
│  Layer 2: Traffic Obfuscation                               │
│  ├── V2Ray (VLESS + WebSocket + TLS)                        │
│  ├── Shadowsocks (Simple-OBFS)                              │
│  └── Encrypted Client Hello (ECH)                            │
│                                                              │
│  Layer 1: Protocol Security                                  │
│  ├── SRT (AES-256) for Broadcasting                         │
│  ├── TLS 1.3 for Web Delivery                               │
│  └── HLS over HTTPS                                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Layer 1: Protocol Security

### 1.1 SRT (Secure Reliable Transport) for Broadcasting

**Use Case**: Point-to-point streaming (OBS → Server, Server → Server)

#### Current Status
- ❌ Not implemented in FOS-Streaming v70
- Current protocol: RTMP (unencrypted, vulnerable)

#### Benefits
- ✅ **Native AES-128/256 encryption** (end-to-end)
- ✅ **Low latency**: 300-500ms (vs RTMP 1-3s)
- ✅ **Packet recovery**: ARQ (Automatic Repeat Request)
- ✅ **UDP-based**: Better performance on weak networks
- ✅ **Industry standard**: Supported by OBS, vMix, FFmpeg

#### Implementation Plan

##### Phase 1: FFmpeg SRT Support
```bash
# Verify FFmpeg has SRT support
ffmpeg -protocols 2>&1 | grep srt

# If not present, rebuild FFmpeg with libsrt
git clone https://github.com/Haivision/srt.git
cd srt
./configure
make && make install

# Rebuild FFmpeg with SRT
./configure --enable-libsrt --enable-gpl --enable-nonfree
make && make install
```

##### Phase 2: SRT Ingest Configuration

**Option A: Direct SRT Reception (Recommended)**
```bash
# SRT Server listening on port (random secure port)
SRT_PORT=$(grep srt_port /home/fos-streaming/fos/www/config/ports.php | grep -oP '\d+')

# FFmpeg SRT listener
ffmpeg -i "srt://0.0.0.0:${SRT_PORT}?mode=listener&passphrase=YOUR_PASSPHRASE" \
       -c copy -f flv rtmp://127.0.0.1:${RTMP_PORT}/live/stream_key
```

**Option B: SRT-to-RTMP Gateway**
```php
// lib/SRTManager.php
class SRTManager {
    private $srt_port;
    private $passphrase;

    public function __construct() {
        $config = require(__DIR__ . '/../config/ports.php');
        $this->srt_port = $config['srt_port'] ?? 9998;
        $this->passphrase = $this->generateSecurePassphrase();
    }

    public function startSRTListener($stream_id, $rtmp_endpoint) {
        $cmd = sprintf(
            'ffmpeg -i "srt://0.0.0.0:%d?mode=listener&passphrase=%s" ' .
            '-c copy -f flv %s > /dev/null 2>&1 &',
            $this->srt_port,
            escapeshellarg($this->passphrase),
            escapeshellarg($rtmp_endpoint)
        );
        exec($cmd);
    }

    private function generateSecurePassphrase() {
        return bin2hex(random_bytes(16));
    }
}
```

##### Phase 3: OBS Configuration
```
Settings → Stream
Service: Custom
Protocol: SRT
Server: srt://YOUR_SERVER_IP:SRT_PORT
Passphrase: [Generated from admin panel]
Latency: 500ms
Encryption: AES-256
```

#### Security Features
- **Passphrase Authentication**: 16-79 character passphrase
- **AES-256 Encryption**: Military-grade encryption
- **Key Length**: 16, 24, or 32 bytes
- **No Replay Attacks**: Built-in packet sequencing

---

### 1.2 TLS 1.3 with Encrypted Client Hello (ECH)

**Use Case**: Web-based streaming delivery (HLS over HTTPS)

#### Current Status
- ✅ TLS 1.2/1.3 implemented (self-signed)
- ❌ Encrypted Client Hello (ECH) not implemented
- ⚠️ SNI leakage exposes streaming domains to ISPs

#### The SNI Problem
```
Traditional TLS Handshake (WITHOUT ECH):
┌─────────────────────────────────────────┐
│ Client Hello [PLAINTEXT]                │
│   ├── TLS Version: 1.3                  │
│   ├── SNI: stream.example.com ← VISIBLE │
│   └── Cipher Suites                     │
└─────────────────────────────────────────┘
      ↓
ISP can see: "User is accessing stream.example.com"
Result: Throttling, blocking, logging
```

```
TLS 1.3 with ECH:
┌─────────────────────────────────────────┐
│ Client Hello                             │
│   ├── TLS Version: 1.3                  │
│   ├── SNI: cloudflare-ech.com [FAKE]    │
│   ├── ECH: [ENCRYPTED PAYLOAD]          │
│   │    └── Real SNI: stream.example.com │
│   └── Cipher Suites                     │
└─────────────────────────────────────────┘
      ↓
ISP can see: "User is accessing cloudflare-ech.com"
Result: No throttling, no specific domain detection
```

#### Implementation Plan

##### Option 1: Caddy (Recommended - Native ECH Support)
```bash
# Install Caddy with ECH support
apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update
apt install caddy

# Caddyfile with ECH
# /etc/caddy/Caddyfile
{
    experimental_http3
    servers {
        protocol {
            experimental_http3
            strict_sni_host
            protocols h1 h2 h3
        }
    }
}

stream.yourdomain.com {
    tls {
        protocols tls1.3
        ciphers TLS_AES_256_GCM_SHA384 TLS_CHACHA20_POLY1305_SHA256
        curves x25519
        # ECH is automatically enabled with Caddy 2.7+
    }

    reverse_proxy localhost:8000 {
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-Proto {scheme}
    }
}
```

##### Option 2: Nginx with BoringSSL (Experimental)
```bash
# Clone nginx with ECH patches
git clone https://github.com/yaroslavros/nginx.git nginx-ech
cd nginx-ech

# Clone BoringSSL
git clone https://boringssl.googlesource.com/boringssl
cd boringssl
mkdir build && cd build
cmake -GNinja ..
ninja
cd ../..

# Build nginx with BoringSSL and ECH support
./auto/configure \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_v3_module \
    --with-openssl=../boringssl \
    --with-cc-opt="-I../boringssl/include" \
    --with-ld-opt="-L../boringssl/build/ssl -L../boringssl/build/crypto"

make && make install
```

**Nginx ECH Configuration**
```nginx
server {
    listen 443 ssl http2 http3;
    listen [::]:443 ssl http2 http3;

    server_name stream.yourdomain.com;

    # TLS 1.3 with ECH
    ssl_protocols TLSv1.3;
    ssl_ciphers 'TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256';
    ssl_prefer_server_ciphers off;

    # ECH configuration (requires BoringSSL)
    ssl_ech on;
    ssl_ech_config_path /path/to/ech-config;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    # HTTP/3 (QUIC)
    add_header Alt-Svc 'h3=":443"; ma=86400';

    location / {
        proxy_pass http://localhost:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

##### Option 3: Cloudflare (Production Ready)
```bash
# Cloudflare automatically provides ECH
# Just use Cloudflare DNS proxy (orange cloud)

# Caddyfile or nginx config remains the same
# Cloudflare handles ECH at the edge
```

#### Browser Support (2025)
- ✅ **Firefox**: ECH enabled by default (since version 118)
- ✅ **Chrome**: Available via flag `chrome://flags/#encrypted-client-hello`
- ⚠️ **Safari**: Experimental support in Safari Technology Preview
- ❌ **Mobile Apps**: Requires custom implementation

#### ECH Verification
```bash
# Test ECH support
curl --ech true https://stream.yourdomain.com -v 2>&1 | grep ECH

# Check with Firefox
# 1. Open about:config
# 2. Search: network.dns.echconfig.enabled
# 3. Value should be: true
# 4. Visit: https://www.cloudflare.com/ssl/encrypted-sni/
```

---

### 1.3 HLS over HTTPS (Web Delivery)

#### Current Implementation
```nginx
# Current: HTTP only (port 8000)
location ~ \.(m3u8|ts)$ {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Access-Control-Allow-Origin "*";
}
```

#### Enhanced Implementation
```nginx
# New: HTTPS with security headers
server {
    listen 8443 ssl http2;
    listen [::]:8443 ssl http2;

    # TLS Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_stapling on;
    ssl_stapling_verify on;

    # Security headers for streaming
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # HLS-specific configuration
    location ~ \.(m3u8|ts)$ {
        root /home/fos-streaming/fos/www/hl;

        # Prevent caching for live streams
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        add_header Pragma "no-cache" always;
        add_header Expires "0" always;

        # CORS for HLS
        add_header Access-Control-Allow-Origin "*" always;
        add_header Access-Control-Allow-Methods "GET, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Origin, X-Requested-With, Content-Type, Accept, Range" always;

        # Content types
        types {
            application/vnd.apple.mpegurl m3u8;
            video/mp2t ts;
        }

        # Rate limiting
        limit_req zone=streaming_limit burst=50 nodelay;
        limit_conn concurrent_streams 10;
    }
}
```

---

## Layer 2: Traffic Obfuscation

**Goal**: Make streaming traffic indistinguishable from normal HTTPS web browsing

### 2.1 V2Ray with VLESS + WebSocket + TLS

**Status**: 🌟 **Recommended Solution for 2025**

#### Why V2Ray?
- ✅ **VLESS Protocol**: Lightweight, fastest protocol (2025)
- ✅ **WebSocket**: Mimics normal web traffic
- ✅ **TLS Wrapping**: Looks like HTTPS
- ✅ **Fallback Mechanism**: Can redirect to real website if probed
- ✅ **Low Overhead**: Minimal performance impact
- ✅ **Proven Effectiveness**: Bypasses most DPI systems

#### Architecture
```
┌─────────────────────────────────────────────────────────────┐
│  Client (OBS/Browser)                                        │
│    ↓                                                         │
│  V2Ray Client (VLESS + WebSocket + TLS)                     │
│    ↓                                                         │
│  [Traffic looks like: wss://example.com/chat]                │
│    ↓                                                         │
│  ISP sees: Normal WebSocket connection to a website          │
│    ↓                                                         │
│  V2Ray Server                                                │
│    ↓                                                         │
│  FOS-Streaming (localhost:8000)                              │
└─────────────────────────────────────────────────────────────┘
```

#### Installation

##### Server-Side Setup
```bash
#!/bin/bash
# install-v2ray-server.sh

# Install V2Ray
bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)

# Generate UUID for VLESS
V2RAY_UUID=$(cat /proc/sys/kernel/random/uuid)
echo "V2Ray UUID: $V2RAY_UUID" > /root/v2ray-credentials.txt

# Configure V2Ray
cat > /usr/local/etc/v2ray/config.json <<EOF
{
  "log": {
    "loglevel": "warning",
    "access": "/var/log/v2ray/access.log",
    "error": "/var/log/v2ray/error.log"
  },
  "inbounds": [
    {
      "port": 443,
      "protocol": "vless",
      "settings": {
        "clients": [
          {
            "id": "${V2RAY_UUID}",
            "level": 0,
            "email": "stream@example.com"
          }
        ],
        "decryption": "none"
      },
      "streamSettings": {
        "network": "ws",
        "security": "tls",
        "wsSettings": {
          "path": "/v2ray",
          "headers": {
            "Host": "stream.yourdomain.com"
          }
        },
        "tlsSettings": {
          "serverName": "stream.yourdomain.com",
          "certificates": [
            {
              "certificateFile": "/etc/letsencrypt/live/stream.yourdomain.com/fullchain.pem",
              "keyFile": "/etc/letsencrypt/live/stream.yourdomain.com/privkey.pem"
            }
          ],
          "alpn": ["h2", "http/1.1"]
        }
      },
      "sniffing": {
        "enabled": true,
        "destOverride": ["http", "tls"]
      }
    }
  ],
  "outbounds": [
    {
      "protocol": "freedom",
      "settings": {},
      "tag": "direct"
    },
    {
      "protocol": "blackhole",
      "settings": {},
      "tag": "blocked"
    }
  ],
  "routing": {
    "domainStrategy": "IPIfNonMatch",
    "rules": [
      {
        "type": "field",
        "outboundTag": "blocked",
        "protocol": ["bittorrent"]
      },
      {
        "type": "field",
        "outboundTag": "direct",
        "network": "tcp,udp"
      }
    ]
  }
}
EOF

# Create log directory
mkdir -p /var/log/v2ray
chown nobody:nogroup /var/log/v2ray

# Enable and start V2Ray
systemctl enable v2ray
systemctl start v2ray
systemctl status v2ray
```

##### Nginx Reverse Proxy for V2Ray
```nginx
# Add to nginx config
location /v2ray {
    if ($http_upgrade != "websocket") {
        return 404;
    }

    proxy_redirect off;
    proxy_pass http://127.0.0.1:10000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

    # WebSocket timeout settings
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}

# Fallback to normal website (anti-probing)
location / {
    root /var/www/decoy;
    index index.html;
}
```

##### Client Configuration (Desktop)
```json
// v2ray-client-config.json
{
  "log": {
    "loglevel": "warning"
  },
  "inbounds": [
    {
      "port": 1080,
      "protocol": "socks",
      "sniffing": {
        "enabled": true,
        "destOverride": ["http", "tls"]
      },
      "settings": {
        "auth": "noauth",
        "udp": true
      }
    },
    {
      "port": 1081,
      "protocol": "http",
      "settings": {
        "timeout": 360
      }
    }
  ],
  "outbounds": [
    {
      "protocol": "vless",
      "settings": {
        "vnext": [
          {
            "address": "stream.yourdomain.com",
            "port": 443,
            "users": [
              {
                "id": "YOUR_V2RAY_UUID",
                "encryption": "none",
                "level": 0
              }
            ]
          }
        ]
      },
      "streamSettings": {
        "network": "ws",
        "security": "tls",
        "wsSettings": {
          "path": "/v2ray",
          "headers": {
            "Host": "stream.yourdomain.com"
          }
        },
        "tlsSettings": {
          "serverName": "stream.yourdomain.com",
          "allowInsecure": false,
          "alpn": ["h2", "http/1.1"]
        }
      },
      "tag": "proxy"
    },
    {
      "protocol": "freedom",
      "tag": "direct"
    }
  ],
  "routing": {
    "domainStrategy": "IPIfNonMatch",
    "rules": [
      {
        "type": "field",
        "outboundTag": "proxy",
        "network": "tcp,udp"
      }
    ]
  }
}
```

##### OBS Configuration via V2Ray
```
Settings → Stream
Server: rtmp://127.0.0.1:1935/live
Stream Key: your_stream_key

# Configure proxy in system settings
Proxy Type: SOCKS5
Proxy Address: 127.0.0.1
Proxy Port: 1080
```

#### Performance Impact
- **Latency overhead**: +10-30ms
- **Bandwidth overhead**: ~5% (minimal VLESS header)
- **CPU usage**: Low (efficient implementation)

---

### 2.2 Shadowsocks with Simple-OBFS

**Status**: ⚡ **Lightweight Alternative**

#### When to Use
- V2Ray is too complex
- Need minimal overhead
- Legacy client support required

#### Installation
```bash
#!/bin/bash
# install-shadowsocks.sh

# Install Shadowsocks-libev
apt install -y shadowsocks-libev simple-obfs

# Generate strong password
SS_PASSWORD=$(openssl rand -base64 32)
echo "Shadowsocks Password: $SS_PASSWORD" > /root/shadowsocks-credentials.txt

# Configure Shadowsocks
cat > /etc/shadowsocks-libev/config.json <<EOF
{
    "server": "0.0.0.0",
    "server_port": 8388,
    "local_address": "127.0.0.1",
    "local_port": 1080,
    "password": "${SS_PASSWORD}",
    "timeout": 300,
    "method": "chacha20-ietf-poly1305",
    "fast_open": true,
    "workers": 4,
    "prefer_ipv6": false,
    "plugin": "obfs-server",
    "plugin_opts": "obfs=tls;obfs-host=cloudflare.com"
}
EOF

# Enable and start
systemctl enable shadowsocks-libev
systemctl start shadowsocks-libev
systemctl status shadowsocks-libev
```

#### Client Configuration
```json
{
    "server": "stream.yourdomain.com",
    "server_port": 8388,
    "local_address": "127.0.0.1",
    "local_port": 1080,
    "password": "YOUR_SS_PASSWORD",
    "timeout": 300,
    "method": "chacha20-ietf-poly1305",
    "plugin": "obfs-local",
    "plugin_opts": "obfs=tls;obfs-host=cloudflare.com"
}
```

---

### 2.3 Comparison: V2Ray vs Shadowsocks

| Feature | V2Ray (VLESS) | Shadowsocks |
|---------|---------------|-------------|
| **Complexity** | Medium | Low |
| **Performance** | High | Very High |
| **Obfuscation** | Excellent | Good |
| **DPI Evasion** | Excellent (2025) | Good |
| **Latency** | +10-30ms | +5-15ms |
| **Setup Time** | 30-60 min | 15-30 min |
| **Client Support** | Wide | Very Wide |
| **Maintenance** | Medium | Low |

**Recommendation**: Use **V2Ray (VLESS)** for maximum security and obfuscation. Use **Shadowsocks** for performance-critical scenarios or simpler setups.

---

## Layer 3: MITM Detection

### 3.1 Certificate Validation and Pinning

#### Browser-Based Detection
```javascript
// public/js/mitm-detector.js
class MITMDetector {
    constructor() {
        this.expectedFingerprints = [
            'SHA256:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99'
        ];
    }

    async checkCertificate() {
        try {
            const response = await fetch('/api/cert-info', {
                method: 'GET',
                headers: {'Accept': 'application/json'}
            });

            const certInfo = await response.json();

            // Check certificate fingerprint
            if (!this.expectedFingerprints.includes(certInfo.fingerprint)) {
                this.alertMITM('Certificate fingerprint mismatch!');
                return false;
            }

            // Check certificate expiry
            const expiryDate = new Date(certInfo.notAfter);
            const now = new Date();
            if (expiryDate < now) {
                this.alertMITM('Certificate has expired!');
                return false;
            }

            // Check issuer
            if (!certInfo.issuer.includes("Let's Encrypt") &&
                !certInfo.issuer.includes("Your-Trusted-CA")) {
                this.alertMITM('Untrusted certificate issuer!');
                return false;
            }

            console.log('✓ Certificate validation passed');
            return true;

        } catch (error) {
            console.error('Certificate validation failed:', error);
            return false;
        }
    }

    alertMITM(message) {
        console.error('⚠️ POTENTIAL MITM ATTACK:', message);

        // Log to server
        fetch('/api/security-alert', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type: 'mitm_detection',
                message: message,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href
            })
        });

        // Show warning to user
        alert('⚠️ SECURITY WARNING: Potential man-in-the-middle attack detected!\n\n' +
              message + '\n\n' +
              'Your connection may be intercepted. Do not enter sensitive information.');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const detector = new MITMDetector();
    detector.checkCertificate();
});
```

#### Server-Side Certificate Info API
```php
<?php
// api/cert-info.php

header('Content-Type: application/json');

$certPath = '/home/fos-streaming/fos/nginx/conf/certs/fullchain.pem';

if (!file_exists($certPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Certificate not found']);
    exit;
}

$certContent = file_get_contents($certPath);
$cert = openssl_x509_parse($certContent);
$fingerprint = openssl_x509_fingerprint($certContent, 'sha256');

$response = [
    'fingerprint' => 'SHA256:' . strtoupper(chunk_split($fingerprint, 2, ':')) . '...',
    'subject' => $cert['subject']['CN'] ?? 'Unknown',
    'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
    'notBefore' => date('Y-m-d H:i:s', $cert['validFrom_time_t']),
    'notAfter' => date('Y-m-d H:i:s', $cert['validTo_time_t']),
    'serialNumber' => $cert['serialNumber'] ?? 'Unknown',
    'version' => $cert['version'] ?? 'Unknown'
];

echo json_encode($response, JSON_PRETTY_PRINT);
```

---

### 3.2 Automated Throttling Detection

#### Speed Test Implementation
```php
<?php
// lib/ThrottleDetector.php

class ThrottleDetector {
    private $testFileSize = 10485760; // 10MB
    private $testDuration = 10; // seconds
    private $expectedSpeed = 5242880; // 5 MB/s baseline

    public function runThrottleTest() {
        $results = [];

        // Test 1: Direct connection
        $results['direct'] = $this->measureSpeed();

        // Test 2: Via VPN/Proxy (if configured)
        if ($this->isProxyAvailable()) {
            $results['proxied'] = $this->measureSpeed(true);
        }

        // Analyze results
        return $this->analyzeThrottling($results);
    }

    private function measureSpeed($useProxy = false) {
        $testUrl = 'https://speed.cloudflare.com/__down?bytes=' . $this->testFileSize;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->testDuration);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($useProxy) {
            curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:1080');
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }

        $startTime = microtime(true);
        $data = curl_exec($ch);
        $endTime = microtime(true);

        $bytesDownloaded = strlen($data);
        $duration = $endTime - $startTime;
        $speedMbps = ($bytesDownloaded * 8 / $duration) / 1000000;

        curl_close($ch);

        return [
            'bytes' => $bytesDownloaded,
            'duration' => $duration,
            'speed_mbps' => round($speedMbps, 2),
            'speed_bps' => round($bytesDownloaded / $duration, 2)
        ];
    }

    private function analyzeThrottling($results) {
        $analysis = [
            'throttled' => false,
            'severity' => 'none',
            'message' => 'No throttling detected',
            'results' => $results
        ];

        if (!isset($results['proxied'])) {
            return $analysis;
        }

        $directSpeed = $results['direct']['speed_bps'];
        $proxiedSpeed = $results['proxied']['speed_bps'];

        $speedDifference = (($proxiedSpeed - $directSpeed) / $directSpeed) * 100;

        if ($speedDifference > 20) {
            $analysis['throttled'] = true;
            $analysis['severity'] = $speedDifference > 50 ? 'high' : 'medium';
            $analysis['message'] = sprintf(
                'ISP throttling detected! Speed via proxy is %.1f%% faster. ' .
                'Your ISP is likely throttling video traffic.',
                $speedDifference
            );
        }

        return $analysis;
    }

    private function isProxyAvailable() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com');
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:1080');
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }
}
```

#### Usage in Admin Panel
```php
// throttle-test.php
<?php
require 'config.php';
require 'lib/ThrottleDetector.php';

$detector = new ThrottleDetector();
$results = $detector->runThrottleTest();

echo $template->render('throttle-test', [
    'results' => $results,
    'timestamp' => date('Y-m-d H:i:s')
]);
```

---

### 3.3 Deep Packet Inspection Detection

#### Wireshark Analysis Script
```bash
#!/bin/bash
# scripts/detect-dpi.sh

INTERFACE="eth0"
DURATION=60
PCAP_FILE="/tmp/traffic-capture-$(date +%s).pcap"

echo "🔍 Starting DPI detection..."
echo "Interface: $INTERFACE"
echo "Duration: ${DURATION}s"
echo ""

# Capture traffic
tcpdump -i $INTERFACE -w $PCAP_FILE -c 10000 &
TCPDUMP_PID=$!

sleep $DURATION
kill $TCPDUMP_PID 2>/dev/null

# Analyze with tshark
echo "📊 Analyzing captured traffic..."

# Check for TLS downgrades
echo ""
echo "1. Checking for TLS downgrades..."
tshark -r $PCAP_FILE -Y "ssl.alert_message.level == 2" -T fields \
    -e frame.time -e ip.src -e ip.dst -e ssl.alert_message.desc

# Check for certificate warnings
echo ""
echo "2. Checking for certificate warnings..."
tshark -r $PCAP_FILE -Y "ssl.handshake.type == 21" -T fields \
    -e frame.time -e ip.src -e ip.dst -e ssl.handshake.type

# Check for suspicious resets
echo ""
echo "3. Checking for suspicious TCP resets..."
tshark -r $PCAP_FILE -Y "tcp.flags.reset == 1" -c 20 -T fields \
    -e frame.time -e ip.src -e ip.dst -e tcp.srcport -e tcp.dstport

# Check TLS versions
echo ""
echo "4. TLS version distribution..."
tshark -r $PCAP_FILE -Y "ssl.handshake.type == 1" -T fields \
    -e ssl.handshake.version | sort | uniq -c | sort -nr

# Generate report
echo ""
echo "✅ Analysis complete. PCAP saved to: $PCAP_FILE"
echo "To delete: rm $PCAP_FILE"
```

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
**Priority**: Critical

- [ ] **Task 1.1**: Implement TLS 1.3 on all HTTPS endpoints
  - Update nginx configuration
  - Generate/install Let's Encrypt certificates
  - Configure modern cipher suites
  - Test with SSL Labs

- [ ] **Task 1.2**: Add SRT support to FFmpeg
  - Verify/rebuild FFmpeg with libsrt
  - Create SRT listener scripts
  - Add SRT port to config/ports.php
  - Document OBS configuration

- [ ] **Task 1.3**: Enhance HLS security
  - Migrate HLS to HTTPS
  - Add security headers
  - Implement rate limiting
  - Test playback

**Deliverables**:
- ✅ TLS 1.3 operational on all ports
- ✅ SRT ingest available
- ✅ HLS over HTTPS working
- ✅ Documentation updated

**Testing**:
```bash
# TLS 1.3 verification
openssl s_client -connect stream.yourdomain.com:443 -tls1_3

# SRT test
ffmpeg -re -i test.mp4 -c copy -f mpegts "srt://server:port?mode=caller&passphrase=test"

# HLS test
curl -I https://stream.yourdomain.com:8443/hl/stream/index.m3u8
```

---

### Phase 2: Obfuscation Layer (Week 3-4)
**Priority**: High

- [ ] **Task 2.1**: Deploy V2Ray Server
  - Install V2Ray on server
  - Configure VLESS + WebSocket + TLS
  - Setup nginx reverse proxy
  - Generate client configs

- [ ] **Task 2.2**: Setup Shadowsocks (Alternative)
  - Install shadowsocks-libev
  - Configure simple-obfs
  - Generate credentials
  - Test connectivity

- [ ] **Task 2.3**: Create deployment scripts
  - Automated V2Ray installer
  - Client config generator
  - Credential management
  - Status monitoring

- [ ] **Task 2.4**: Documentation
  - Client setup guides (Windows/Mac/Linux/Android/iOS)
  - OBS configuration via proxy
  - Troubleshooting guide
  - Performance optimization tips

**Deliverables**:
- ✅ V2Ray fully operational
- ✅ Shadowsocks available as fallback
- ✅ Client configs auto-generated
- ✅ Comprehensive documentation

**Testing**:
```bash
# V2Ray connectivity
curl --proxy socks5h://127.0.0.1:1080 https://ipinfo.io

# Shadowsocks test
curl --proxy socks5h://127.0.0.1:1080 https://ipinfo.io

# Streaming via proxy (simulated)
ffmpeg -http_proxy socks5://127.0.0.1:1080 -i https://stream.yourdomain.com/hl/test/index.m3u8
```

---

### Phase 3: ECH Implementation (Week 5-6)
**Priority**: Medium

- [ ] **Task 3.1**: Evaluate ECH options
  - Test Caddy with ECH
  - Test nginx with BoringSSL (if stable)
  - Test Cloudflare proxy with ECH
  - Choose best option for deployment

- [ ] **Task 3.2**: Deploy ECH solution
  - Install chosen software
  - Configure ECH
  - Update DNS records
  - Verify ECH functionality

- [ ] **Task 3.3**: Client browser configuration
  - Document Firefox ECH setup
  - Document Chrome ECH flags
  - Create browser detection script
  - Fallback for non-ECH clients

**Deliverables**:
- ✅ ECH operational on primary domain
- ✅ SNI leakage eliminated
- ✅ Browser compatibility documented
- ✅ Fallback mechanisms working

**Testing**:
```bash
# ECH verification
curl --ech true https://stream.yourdomain.com -v 2>&1 | grep ECH

# Browser test
# Visit: https://www.cloudflare.com/ssl/encrypted-sni/
```

---

### Phase 4: MITM Detection (Week 7)
**Priority**: Medium

- [ ] **Task 4.1**: Implement certificate monitoring
  - Deploy JavaScript MITM detector
  - Create cert-info API endpoint
  - Add alert system
  - Log security events

- [ ] **Task 4.2**: Throttle detection system
  - Implement ThrottleDetector class
  - Add speed test page to admin panel
  - Create automated monitoring
  - Alert on detected throttling

- [ ] **Task 4.3**: DPI analysis tools
  - Install Wireshark/tshark
  - Deploy detect-dpi.sh script
  - Document usage
  - Create analysis guidelines

**Deliverables**:
- ✅ Real-time MITM detection
- ✅ Throttling detection dashboard
- ✅ DPI analysis tools available
- ✅ Security monitoring operational

**Testing**:
```bash
# Test MITM detection
curl https://stream.yourdomain.com/api/cert-info

# Run throttle test
php throttle-test.php

# Run DPI detection
./scripts/detect-dpi.sh
```

---

### Phase 5: Integration & Testing (Week 8)
**Priority**: Critical

- [ ] **Task 5.1**: End-to-end testing
  - Test complete workflow: OBS → SRT → HLS → Browser
  - Test with V2Ray obfuscation
  - Test with various client devices
  - Performance benchmarking

- [ ] **Task 5.2**: Security audit
  - Run automated security scans
  - Review all configurations
  - Penetration testing
  - Fix vulnerabilities

- [ ] **Task 5.3**: Documentation finalization
  - Update all documentation
  - Create video tutorials
  - FAQ and troubleshooting
  - Performance tuning guide

- [ ] **Task 5.4**: Monitoring and alerting
  - Setup log aggregation
  - Configure alerts
  - Create dashboards
  - Document incident response

**Deliverables**:
- ✅ All systems tested and validated
- ✅ Security audit passed
- ✅ Complete documentation
- ✅ Monitoring operational

---

### Phase 6: Production Deployment (Week 9)
**Priority**: Critical

- [ ] **Task 6.1**: Staging environment testing
  - Deploy to staging
  - Run full test suite
  - Load testing
  - Failover testing

- [ ] **Task 6.2**: Production rollout
  - Backup current system
  - Deploy Phase 1 changes
  - Deploy Phase 2 changes
  - Deploy Phase 3 & 4 changes

- [ ] **Task 6.3**: User migration
  - Notify users of new features
  - Provide migration guides
  - Support user onboarding
  - Collect feedback

- [ ] **Task 6.4**: Post-deployment monitoring
  - Monitor system performance
  - Track error rates
  - User satisfaction survey
  - Optimization iterations

**Deliverables**:
- ✅ Production deployment successful
- ✅ Zero downtime migration
- ✅ Users successfully migrated
- ✅ System stable and monitored

---

## Technical Specifications

### Encryption Standards

#### SRT Encryption
- **Algorithm**: AES-128 or AES-256
- **Key Exchange**: Passphrase-based
- **Mode**: CTR (Counter Mode)
- **Passphrase Length**: 16-79 characters (recommendation: 32)

#### TLS Configuration
```
Protocols: TLSv1.2, TLSv1.3 (preferred)
Cipher Suites:
  - TLS_AES_256_GCM_SHA384
  - TLS_CHACHA20_POLY1305_SHA256
  - TLS_AES_128_GCM_SHA256
  - ECDHE-ECDSA-AES256-GCM-SHA384
  - ECDHE-RSA-AES256-GCM-SHA384
  - ECDHE-ECDSA-CHACHA20-POLY1305
  - ECDHE-RSA-CHACHA20-POLY1305

Key Exchange: ECDHE (Elliptic Curve Diffie-Hellman Ephemeral)
Curves: X25519, secp384r1, secp256r1
Session Resumption: TLS 1.3 0-RTT (with replay protection)
OCSP Stapling: Enabled
```

#### V2Ray Security
```
Protocol: VLESS
Encryption: None (relies on TLS outer layer)
Transport: WebSocket over TLS 1.3
Fallback: HTTPS to decoy website
UUID Format: RFC 4122 v4
```

#### Shadowsocks Security
```
Cipher: chacha20-ietf-poly1305 (recommended)
Alternative: aes-256-gcm
Plugin: simple-obfs (obfs=tls)
Obfs-host: cloudflare.com or legitimate website
```

---

### Performance Benchmarks

#### Expected Latency
| Configuration | Added Latency | Total Latency |
|--------------|---------------|---------------|
| Direct RTMP | 0ms | 1000-3000ms |
| SRT | 0ms | 300-500ms |
| HLS over HTTPS | +50-100ms | 2000-6000ms |
| HLS + V2Ray | +10-30ms | 2010-6030ms |
| HLS + Shadowsocks | +5-15ms | 2005-6015ms |

#### Bandwidth Overhead
| Protocol | Overhead | Example (1 Mbps stream) |
|----------|----------|-------------------------|
| Plain RTMP | 0% | 1.00 Mbps |
| SRT | ~2% | 1.02 Mbps |
| TLS 1.3 | ~3% | 1.03 Mbps |
| V2Ray (VLESS) | ~5% | 1.05 Mbps |
| Shadowsocks | ~3% | 1.03 Mbps |

#### CPU Usage (Per Stream)
| Component | CPU Usage (%) | Notes |
|-----------|---------------|-------|
| FFmpeg transcoding | 30-80% | Depends on resolution |
| SRT encryption | 2-5% | Hardware acceleration available |
| TLS 1.3 | 1-3% | Modern CPUs have AES-NI |
| V2Ray | 3-7% | Per 1 Mbps throughput |
| Shadowsocks | 2-5% | Lightweight |

---

### Port Allocation

#### Default Ports (Can be randomized)
```php
// config/ports.php
return [
    'web_port' => 2053,      // Cloudflare SSL-compatible
    'stream_port' => 2083,   // Cloudflare SSL-compatible
    'rtmp_port' => 1947,     // Random RTMP
    'srt_port' => 9998,      // SRT ingest
    'v2ray_port' => 443,     // V2Ray (standard HTTPS)
    'shadowsocks_port' => 8388, // Shadowsocks
];
```

#### Firewall Configuration
```bash
# Essential ports
ufw allow 2053/tcp  # Web HTTPS
ufw allow 2083/tcp  # Streaming HTTPS
ufw allow 1947/tcp  # RTMP (random)
ufw allow 9998/udp  # SRT
ufw allow 443/tcp   # V2Ray/HTTPS
ufw allow 8388/tcp  # Shadowsocks

# Optional: Allow QUIC (HTTP/3)
ufw allow 443/udp
```

---

## Deployment Guide

### Quick Start (Automated)

#### Option 1: All-in-One Installer
```bash
#!/bin/bash
# deploy-secure-streaming.sh

wget https://raw.githubusercontent.com/YOUR_REPO/FOS-Streaming-v70/master/scripts/deploy-secure-streaming.sh
chmod +x deploy-secure-streaming.sh
./deploy-secure-streaming.sh

# Follow interactive prompts:
# 1. Domain name
# 2. Email for Let's Encrypt
# 3. Choose obfuscation: V2Ray or Shadowsocks
# 4. Enable ECH (Caddy/nginx-BoringSSL)
```

#### Option 2: Docker Compose
```yaml
# docker-compose-secure.yml
version: '3.8'

services:
  fos-streaming:
    build: .
    ports:
      - "2053:2053"   # Web HTTPS
      - "2083:2083"   # Streaming HTTPS
      - "9998:9998/udp"  # SRT
    volumes:
      - ./www:/home/fos-streaming/fos/www
      - ./config:/home/fos-streaming/fos/config
      - ./certs:/home/fos-streaming/fos/nginx/conf/certs
    environment:
      - DOMAIN=stream.yourdomain.com
      - LETSENCRYPT_EMAIL=admin@yourdomain.com
    restart: unless-stopped

  v2ray:
    image: v2fly/v2fly-core:latest
    ports:
      - "443:443"
    volumes:
      - ./v2ray-config.json:/etc/v2ray/config.json:ro
    depends_on:
      - fos-streaming
    restart: unless-stopped

  shadowsocks:
    image: shadowsocks/shadowsocks-libev:latest
    ports:
      - "8388:8388"
    environment:
      - METHOD=chacha20-ietf-poly1305
      - PASSWORD=${SS_PASSWORD}
      - ARGS=--plugin obfs-server --plugin-opts obfs=tls
    restart: unless-stopped
```

---

### Manual Deployment Steps

#### Step 1: Base System
```bash
# Run Debian 12 installer
bash /path/to/install/debian12

# Verify installation
systemctl status fos-nginx
systemctl status php8.4-fpm
systemctl status mariadb
```

#### Step 2: SRT Support
```bash
# Check FFmpeg SRT support
ffmpeg -protocols 2>&1 | grep srt

# If not present, rebuild FFmpeg
cd /tmp
git clone https://github.com/Haivision/srt.git
cd srt && ./configure && make && make install

# Rebuild FFmpeg with SRT
wget https://johnvansickle.com/ffmpeg/release-source/ffmpeg-6.1.tar.xz
tar -xf ffmpeg-6.1.tar.xz
cd ffmpeg-6.1
./configure --enable-libsrt --enable-gpl
make && make install
```

#### Step 3: V2Ray Deployment
```bash
# Install V2Ray
bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)

# Generate config
UUID=$(cat /proc/sys/kernel/random/uuid)
cat > /usr/local/etc/v2ray/config.json <<EOF
{
  "inbounds": [{
    "port": 443,
    "protocol": "vless",
    "settings": {
      "clients": [{"id": "$UUID"}],
      "decryption": "none"
    },
    "streamSettings": {
      "network": "ws",
      "security": "tls",
      "wsSettings": {"path": "/v2ray"}
    }
  }],
  "outbounds": [{"protocol": "freedom"}]
}
EOF

# Start V2Ray
systemctl enable v2ray && systemctl start v2ray
```

#### Step 4: ECH Setup (Caddy)
```bash
# Install Caddy
apt install -y caddy

# Configure Caddyfile
cat > /etc/caddy/Caddyfile <<EOF
stream.yourdomain.com {
    tls {
        protocols tls1.3
    }
    reverse_proxy localhost:2083
}
EOF

# Reload Caddy
systemctl reload caddy
```

#### Step 5: Certificate Management
```bash
# Let's Encrypt with auto-renewal
certbot certonly --standalone -d stream.yourdomain.com \
  --non-interactive --agree-tos --email admin@yourdomain.com

# Link certificates
ln -sf /etc/letsencrypt/live/stream.yourdomain.com/fullchain.pem \
  /home/fos-streaming/fos/nginx/conf/certs/fullchain.pem
ln -sf /etc/letsencrypt/live/stream.yourdomain.com/privkey.pem \
  /home/fos-streaming/fos/nginx/conf/certs/privkey.pem

# Test renewal
certbot renew --dry-run
```

---

## Testing & Validation

### Security Test Suite

#### Test 1: TLS Configuration
```bash
# SSL Labs Test
echo "Visit: https://www.ssllabs.com/ssltest/analyze.html?d=stream.yourdomain.com"

# Manual test
openssl s_client -connect stream.yourdomain.com:443 -tls1_3 -brief

# Expected output:
# Protocol version: TLSv1.3
# Ciphersuite: TLS_AES_256_GCM_SHA384
```

#### Test 2: ECH Verification
```bash
# Firefox verification
# 1. about:config
# 2. network.dns.echconfig.enabled = true
# 3. Visit: https://www.cloudflare.com/ssl/encrypted-sni/
# 4. Should show: "Encrypted SNI: Yes"

# Command-line test (if curl supports ECH)
curl --ech true https://stream.yourdomain.com -v 2>&1 | grep -i ech
```

#### Test 3: V2Ray Connectivity
```bash
# Test SOCKS5 proxy
curl --proxy socks5h://127.0.0.1:1080 https://ipinfo.io

# Expected: Should show server IP, not your local IP

# Test via browser
# Configure browser to use SOCKS5 proxy: 127.0.0.1:1080
# Visit: https://whatismyipaddress.com/
```

#### Test 4: SRT Streaming
```bash
# Terminal 1: Start SRT listener
ffmpeg -i "srt://0.0.0.0:9998?mode=listener&passphrase=testpass" \
  -c copy -f flv rtmp://localhost:1935/live/test

# Terminal 2: Send test stream
ffmpeg -re -i test.mp4 -c copy -f mpegts \
  "srt://localhost:9998?mode=caller&passphrase=testpass"

# Terminal 3: Verify HLS output
curl http://localhost:8000/hl/test/index.m3u8
```

#### Test 5: MITM Detection
```bash
# Run MITM detector
curl https://stream.yourdomain.com/api/cert-info

# Expected: Valid certificate info returned

# Simulate MITM (for testing only!)
# 1. Setup mitmproxy
mitmproxy -p 8080

# 2. Configure browser to use proxy localhost:8080
# 3. Visit: https://stream.yourdomain.com
# 4. JavaScript detector should alert
```

#### Test 6: Throttle Detection
```php
// Run from admin panel or CLI
<?php
require 'config.php';
require 'lib/ThrottleDetector.php';

$detector = new ThrottleDetector();
$results = $detector->runThrottleTest();

print_r($results);

// Expected output:
// Array (
//     [throttled] => false
//     [severity] => none
//     [message] => No throttling detected
//     ...
// )
```

#### Test 7: DPI Evasion
```bash
# Run DPI detection script
sudo ./scripts/detect-dpi.sh

# Analyze results:
# 1. No TLS downgrades detected
# 2. No suspicious resets
# 3. TLS 1.3 in use
# 4. No anomalies

# Compare with/without V2Ray:
# Without: May show resets, downgrades
# With V2Ray: Clean WebSocket traffic
```

---

### Performance Validation

#### Latency Test
```bash
#!/bin/bash
# test-latency.sh

echo "Testing end-to-end latency..."

# Test 1: Direct RTMP (baseline)
echo "1. Direct RTMP:"
time ffmpeg -re -i test.mp4 -c copy -f flv rtmp://localhost:1935/live/test \
  > /dev/null 2>&1 &
sleep 5
curl -o /dev/null -s -w "Time: %{time_total}s\n" http://localhost:8000/hl/test/index.m3u8
pkill ffmpeg

# Test 2: SRT
echo "2. SRT:"
time ffmpeg -re -i test.mp4 -c copy -f mpegts srt://localhost:9998?mode=caller \
  > /dev/null 2>&1 &
sleep 5
curl -o /dev/null -s -w "Time: %{time_total}s\n" http://localhost:8000/hl/test/index.m3u8
pkill ffmpeg

# Test 3: Via V2Ray
echo "3. Via V2Ray:"
export http_proxy=socks5://127.0.0.1:1080
curl -o /dev/null -s -w "Time: %{time_total}s\n" http://localhost:8000/hl/test/index.m3u8
```

#### Bandwidth Test
```bash
# Measure bandwidth overhead
iperf3 -s &  # Server
iperf3 -c localhost -t 30  # Client

# Compare:
# 1. Direct connection
# 2. Via V2Ray proxy
# 3. Via Shadowsocks

# Expected overhead: 3-7%
```

---

## Maintenance & Monitoring

### Daily Monitoring

#### System Health Check
```bash
#!/bin/bash
# scripts/health-check.sh

echo "=== FOS-Streaming Security Health Check ==="
echo "Date: $(date)"
echo ""

# Check services
echo "1. Service Status:"
systemctl is-active fos-nginx && echo "  ✓ Nginx: Running" || echo "  ✗ Nginx: DOWN"
systemctl is-active php8.4-fpm && echo "  ✓ PHP-FPM: Running" || echo "  ✗ PHP-FPM: DOWN"
systemctl is-active v2ray && echo "  ✓ V2Ray: Running" || echo "  ✗ V2Ray: DOWN"

# Check certificates
echo ""
echo "2. Certificate Expiry:"
CERT_PATH="/home/fos-streaming/fos/nginx/conf/certs/fullchain.pem"
EXPIRY_DATE=$(openssl x509 -enddate -noout -in $CERT_PATH | cut -d= -f2)
EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
NOW_EPOCH=$(date +%s)
DAYS_LEFT=$(( ($EXPIRY_EPOCH - $NOW_EPOCH) / 86400 ))

if [ $DAYS_LEFT -gt 30 ]; then
    echo "  ✓ Certificate valid for $DAYS_LEFT days"
elif [ $DAYS_LEFT -gt 7 ]; then
    echo "  ⚠ Certificate expires in $DAYS_LEFT days - renewal soon"
else
    echo "  ✗ Certificate expires in $DAYS_LEFT days - URGENT"
fi

# Check disk space
echo ""
echo "3. Disk Space:"
DISK_USAGE=$(df -h /home/fos-streaming | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -lt 80 ]; then
    echo "  ✓ Disk usage: ${DISK_USAGE}%"
else
    echo "  ✗ Disk usage: ${DISK_USAGE}% - HIGH"
fi

# Check logs for errors
echo ""
echo "4. Recent Errors (last 1 hour):"
ERROR_COUNT=$(grep -c "error" /home/fos-streaming/fos/logs/error.log | tail -100)
echo "  Found $ERROR_COUNT errors in logs"

# Check V2Ray connectivity
echo ""
echo "5. V2Ray Connectivity:"
if curl -s --proxy socks5h://127.0.0.1:1080 https://www.google.com -o /dev/null; then
    echo "  ✓ V2Ray proxy working"
else
    echo "  ✗ V2Ray proxy FAILED"
fi

echo ""
echo "=== Health Check Complete ==="
```

#### Setup Cron Jobs
```bash
# Add to crontab
crontab -e

# Daily health check (8 AM)
0 8 * * * /home/fos-streaming/scripts/health-check.sh | mail -s "FOS Security Health" admin@yourdomain.com

# Certificate renewal check (weekly)
0 3 * * 0 certbot renew --quiet --post-hook "systemctl reload fos-nginx"

# DPI detection (monthly)
0 2 1 * * /home/fos-streaming/scripts/detect-dpi.sh > /var/log/dpi-check-$(date +\%Y\%m).log

# Throttle test (weekly)
0 4 * * 1 php /home/fos-streaming/fos/www/throttle-test.php --cron
```

---

### Incident Response

#### Security Incident Procedure

**Level 1: MITM Detected**
```bash
1. Verify alert is genuine (check logs)
2. Identify affected users/streams
3. Rotate certificates if compromised
4. Notify users to verify certificates
5. Document incident
```

**Level 2: DPI/Throttling Detected**
```bash
1. Verify detection with multiple tests
2. Switch to alternate obfuscation (V2Ray → Shadowsocks or vice versa)
3. Notify users of recommended configuration
4. Monitor for improvement
5. Document ISP behavior
```

**Level 3: Service Compromise**
```bash
1. Immediately isolate affected service
2. Stop all streaming
3. Analyze logs and forensics
4. Restore from clean backup
5. Update all credentials
6. Security audit
7. Patch vulnerabilities
8. Document and report
```

---

### Logging and Alerting

#### Centralized Logging
```bash
# Install rsyslog or Loki for log aggregation
apt install rsyslog

# Configure forwarding
cat >> /etc/rsyslog.d/50-fos-streaming.conf <<EOF
# FOS-Streaming logs
\$ModLoad imfile
\$InputFileName /home/fos-streaming/fos/logs/error.log
\$InputFileTag fos-nginx-error:
\$InputFileStateFile stat-nginx-error
\$InputFileSeverity error
\$InputFileFacility local3
\$InputRunFileMonitor

\$InputFileName /home/fos-streaming/fos/logs/security.log
\$InputFileTag fos-security:
\$InputFileStateFile stat-security
\$InputFileSeverity warning
\$InputFileFacility local4
\$InputRunFileMonitor

# Forward to central log server (optional)
*.* @@log-server.yourdomain.com:514
EOF

systemctl restart rsyslog
```

#### Alert Configuration
```php
// lib/AlertManager.php
class AlertManager {
    private $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';

    public function sendAlert($level, $message, $details = []) {
        $payload = [
            'text' => "🚨 FOS-Streaming Security Alert",
            'attachments' => [
                [
                    'color' => $this->getLevelColor($level),
                    'fields' => [
                        ['title' => 'Level', 'value' => $level, 'short' => true],
                        ['title' => 'Time', 'value' => date('Y-m-d H:i:s'), 'short' => true],
                        ['title' => 'Message', 'value' => $message, 'short' => false],
                        ['title' => 'Details', 'value' => json_encode($details, JSON_PRETTY_PRINT), 'short' => false]
                    ]
                ]
            ]
        ];

        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getLevelColor($level) {
        return match($level) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            default => '#808080'
        };
    }
}
```

---

## Summary

This comprehensive plan provides **military-grade security** for FOS-Streaming v70 through multiple defensive layers:

### Key Achievements
✅ **Layer 1 (Protocol)**: SRT with AES-256, TLS 1.3, HLS over HTTPS
✅ **Layer 2 (Obfuscation)**: V2Ray/Shadowsocks hide streaming from ISPs
✅ **Layer 3 (Detection)**: Real-time MITM and throttling detection

### Security Posture
- **Encryption**: All traffic encrypted end-to-end
- **Privacy**: SNI leakage eliminated with ECH
- **Stealth**: Traffic indistinguishable from web browsing
- **Detection**: Active monitoring for interception attempts
- **Reliability**: Low-latency, high-quality streaming maintained

### Implementation Timeline
- **Phase 1-2** (4 weeks): Core security features
- **Phase 3-4** (4 weeks): Advanced obfuscation
- **Phase 5-6** (2 weeks): Testing and deployment

### Maintenance Requirements
- Daily: Automated health checks
- Weekly: Certificate verification, throttle tests
- Monthly: Security audits, DPI analysis
- Quarterly: Penetration testing, updates

---

## Next Steps

1. **Review and Approve Plan** ✓
2. **Allocate Resources** (1 developer, 9 weeks)
3. **Setup Development Environment**
4. **Begin Phase 1 Implementation**
5. **Iterative Testing Throughout**
6. **Documentation in Parallel**
7. **Staged Production Rollout**
8. **Continuous Monitoring**

---

**Document Version**: 1.0
**Author**: Security Team
**Date**: 2025-11-21
**Status**: Ready for Implementation
**Estimated Effort**: 9 weeks (1 developer full-time)
**Risk Level**: Medium (comprehensive testing required)
**Success Criteria**: All security tests passing, zero detected vulnerabilities
