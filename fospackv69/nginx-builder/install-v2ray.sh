#!/bin/bash

# ============================================================================
# V2Ray Installation Script
# FOS-Streaming v70
#
# This script installs V2Ray core with custom configuration
# ============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
V2RAY_VERSION="v5.12.1"
INSTALL_DIR="/usr/local/v2ray"
CONFIG_DIR="/etc/v2ray"
LOG_DIR="/var/log/v2ray"
DATA_DIR="/var/lib/v2ray"
SYSTEMD_SERVICE="/etc/systemd/system/v2ray.service"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root"
    exit 1
fi

echo "=========================================="
echo "V2Ray Installation Script"
echo "=========================================="
echo

# Detect system architecture
ARCH=$(uname -m)
case $ARCH in
    x86_64)
        V2RAY_ARCH="64"
        ;;
    aarch64)
        V2RAY_ARCH="arm64-v8a"
        ;;
    armv7l)
        V2RAY_ARCH="arm32-v7a"
        ;;
    *)
        log_error "Unsupported architecture: $ARCH"
        exit 1
        ;;
esac

log_info "Detected architecture: $ARCH (V2Ray: $V2RAY_ARCH)"

# Step 1: Install dependencies
log_info "Installing dependencies..."
apt-get update
apt-get install -y \
    curl \
    wget \
    unzip \
    jq \
    ca-certificates \
    lsb-release

# Step 2: Create directories
log_info "Creating directories..."
mkdir -p $INSTALL_DIR
mkdir -p $CONFIG_DIR
mkdir -p $LOG_DIR
mkdir -p $DATA_DIR
mkdir -p $DATA_DIR/geoip

# Step 3: Download V2Ray
log_info "Downloading V2Ray ${V2RAY_VERSION}..."
DOWNLOAD_URL="https://github.com/v2fly/v2ray-core/releases/download/${V2RAY_VERSION}/v2ray-linux-${V2RAY_ARCH}.zip"

cd /tmp
wget -O v2ray.zip "$DOWNLOAD_URL"

if [ ! -f v2ray.zip ]; then
    log_error "Failed to download V2Ray"
    exit 1
fi

# Step 4: Extract V2Ray
log_info "Extracting V2Ray..."
unzip -q v2ray.zip -d v2ray-temp
cd v2ray-temp

# Step 5: Install V2Ray binaries
log_info "Installing V2Ray binaries..."
cp v2ray $INSTALL_DIR/
cp v2ctl $INSTALL_DIR/
chmod +x $INSTALL_DIR/v2ray
chmod +x $INSTALL_DIR/v2ctl

# Step 6: Install geo data files
log_info "Installing geo data files..."
cp geoip.dat $DATA_DIR/geoip/
cp geosite.dat $DATA_DIR/geoip/

# Step 7: Create V2Ray user
if ! id -u v2ray > /dev/null 2>&1; then
    log_info "Creating v2ray user..."
    useradd -r -s /bin/false -d $DATA_DIR v2ray
fi

# Step 8: Create main configuration file
log_info "Creating V2Ray configuration..."
cat > $CONFIG_DIR/config.json <<'EOF'
{
  "log": {
    "access": "/var/log/v2ray/access.log",
    "error": "/var/log/v2ray/error.log",
    "loglevel": "warning"
  },
  "api": {
    "tag": "api",
    "services": ["HandlerService", "LogService", "StatsService"]
  },
  "stats": {},
  "policy": {
    "levels": {
      "0": {
        "handshake": 4,
        "connIdle": 300,
        "uplinkOnly": 2,
        "downlinkOnly": 5,
        "statsUserUplink": true,
        "statsUserDownlink": true,
        "bufferSize": 10240
      }
    },
    "system": {
      "statsInboundUplink": true,
      "statsInboundDownlink": true,
      "statsOutboundUplink": true,
      "statsOutboundDownlink": true
    }
  },
  "inbounds": [
    {
      "tag": "vmess-ws-tls",
      "port": 443,
      "protocol": "vmess",
      "settings": {
        "clients": []
      },
      "streamSettings": {
        "network": "ws",
        "security": "tls",
        "tlsSettings": {
          "certificates": [
            {
              "certificateFile": "/etc/v2ray/cert.pem",
              "keyFile": "/etc/v2ray/key.pem"
            }
          ],
          "alpn": ["h2", "http/1.1"]
        },
        "wsSettings": {
          "path": "/streaming",
          "headers": {
            "Host": "localhost"
          }
        }
      }
    },
    {
      "tag": "vless-tcp-xtls",
      "port": 8443,
      "protocol": "vless",
      "settings": {
        "clients": [],
        "decryption": "none",
        "fallbacks": [
          {
            "dest": 80
          }
        ]
      },
      "streamSettings": {
        "network": "tcp",
        "security": "xtls",
        "xtlsSettings": {
          "certificates": [
            {
              "certificateFile": "/etc/v2ray/cert.pem",
              "keyFile": "/etc/v2ray/key.pem"
            }
          ],
          "alpn": ["h2", "http/1.1"]
        }
      }
    },
    {
      "tag": "trojan-ws-tls",
      "port": 8444,
      "protocol": "trojan",
      "settings": {
        "clients": [],
        "fallbacks": [
          {
            "dest": 80
          }
        ]
      },
      "streamSettings": {
        "network": "ws",
        "security": "tls",
        "tlsSettings": {
          "certificates": [
            {
              "certificateFile": "/etc/v2ray/cert.pem",
              "keyFile": "/etc/v2ray/key.pem"
            }
          ],
          "alpn": ["h2", "http/1.1"]
        },
        "wsSettings": {
          "path": "/trojan",
          "headers": {
            "Host": "localhost"
          }
        }
      }
    },
    {
      "tag": "api",
      "port": 8080,
      "listen": "127.0.0.1",
      "protocol": "dokodemo-door",
      "settings": {
        "address": "127.0.0.1"
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
    "domainStrategy": "AsIs",
    "rules": [
      {
        "inboundTag": ["api"],
        "outboundTag": "api",
        "type": "field"
      },
      {
        "type": "field",
        "protocol": ["bittorrent"],
        "outboundTag": "blocked"
      },
      {
        "type": "field",
        "domain": [
          "geosite:category-ads-all"
        ],
        "outboundTag": "blocked"
      },
      {
        "type": "field",
        "ip": [
          "geoip:private"
        ],
        "outboundTag": "direct"
      },
      {
        "type": "field",
        "domain": [
          "geosite:cn"
        ],
        "outboundTag": "direct"
      },
      {
        "type": "field",
        "ip": [
          "geoip:cn"
        ],
        "outboundTag": "direct"
      }
    ]
  }
}
EOF

# Step 9: Create TLS certificates
log_info "Generating self-signed TLS certificates..."
openssl req -x509 -newkey rsa:4096 -nodes -days 365 \
    -keyout $CONFIG_DIR/key.pem \
    -out $CONFIG_DIR/cert.pem \
    -subj "/C=US/ST=State/L=City/O=FOS-Streaming/CN=localhost"

# Step 10: Create systemd service
log_info "Creating systemd service..."
cat > $SYSTEMD_SERVICE <<EOF
[Unit]
Description=V2Ray Service
Documentation=https://v2fly.org/
After=network.target nss-lookup.target

[Service]
Type=simple
User=v2ray
CapabilityBoundingSet=CAP_NET_ADMIN CAP_NET_BIND_SERVICE
AmbientCapabilities=CAP_NET_ADMIN CAP_NET_BIND_SERVICE
NoNewPrivileges=true
ExecStart=$INSTALL_DIR/v2ray run -config $CONFIG_DIR/config.json
Restart=on-failure
RestartSec=10s
LimitNOFILE=infinity

[Install]
WantedBy=multi-user.target
EOF

# Step 11: Set permissions
log_info "Setting permissions..."
chown -R v2ray:v2ray $CONFIG_DIR
chown -R v2ray:v2ray $LOG_DIR
chown -R v2ray:v2ray $DATA_DIR
chmod 600 $CONFIG_DIR/key.pem
chmod 644 $CONFIG_DIR/cert.pem

# Step 12: Create V2Ray client manager script
log_info "Creating V2Ray client manager..."
cat > $INSTALL_DIR/v2ray-manager.sh <<'EOF'
#!/bin/bash

V2RAY_CONFIG="/etc/v2ray/config.json"
V2RAY_SERVICE="v2ray"

case "$1" in
    add-vmess)
        UUID=$2
        EMAIL=$3
        if [ -z "$UUID" ] || [ -z "$EMAIL" ]; then
            echo "Usage: $0 add-vmess <uuid> <email>"
            exit 1
        fi

        # Add client to config
        jq --arg uuid "$UUID" --arg email "$EMAIL" \
            '.inbounds[0].settings.clients += [{"id": $uuid, "email": $email, "alterId": 64}]' \
            $V2RAY_CONFIG > /tmp/v2ray-config.json && \
        mv /tmp/v2ray-config.json $V2RAY_CONFIG

        systemctl reload $V2RAY_SERVICE
        echo "Added VMess client: $EMAIL ($UUID)"
        ;;

    add-vless)
        UUID=$2
        EMAIL=$3
        if [ -z "$UUID" ] || [ -z "$EMAIL" ]; then
            echo "Usage: $0 add-vless <uuid> <email>"
            exit 1
        fi

        # Add client to config
        jq --arg uuid "$UUID" --arg email "$EMAIL" \
            '.inbounds[1].settings.clients += [{"id": $uuid, "email": $email, "flow": "xtls-rprx-direct"}]' \
            $V2RAY_CONFIG > /tmp/v2ray-config.json && \
        mv /tmp/v2ray-config.json $V2RAY_CONFIG

        systemctl reload $V2RAY_SERVICE
        echo "Added VLESS client: $EMAIL ($UUID)"
        ;;

    add-trojan)
        PASSWORD=$2
        EMAIL=$3
        if [ -z "$PASSWORD" ] || [ -z "$EMAIL" ]; then
            echo "Usage: $0 add-trojan <password> <email>"
            exit 1
        fi

        # Add client to config
        jq --arg password "$PASSWORD" --arg email "$EMAIL" \
            '.inbounds[2].settings.clients += [{"password": $password, "email": $email}]' \
            $V2RAY_CONFIG > /tmp/v2ray-config.json && \
        mv /tmp/v2ray-config.json $V2RAY_CONFIG

        systemctl reload $V2RAY_SERVICE
        echo "Added Trojan client: $EMAIL"
        ;;

    remove)
        EMAIL=$2
        if [ -z "$EMAIL" ]; then
            echo "Usage: $0 remove <email>"
            exit 1
        fi

        # Remove client from all inbounds
        jq --arg email "$EMAIL" \
            '(.inbounds[].settings.clients // []) |= map(select(.email != $email))' \
            $V2RAY_CONFIG > /tmp/v2ray-config.json && \
        mv /tmp/v2ray-config.json $V2RAY_CONFIG

        systemctl reload $V2RAY_SERVICE
        echo "Removed client: $EMAIL"
        ;;

    list)
        echo "V2Ray Clients:"
        jq -r '.inbounds[] | select(.settings.clients) |
            "\(.tag): " + (.settings.clients | map(.email) | join(", "))' \
            $V2RAY_CONFIG
        ;;

    stats)
        echo "V2Ray Statistics:"
        $INSTALL_DIR/v2ctl api --server=127.0.0.1:8080 StatsService.QueryStats 'pattern: ""' | \
            jq -r '.stat[] | "\(.name): \(.value)"'
        ;;

    *)
        echo "Usage: $0 {add-vmess|add-vless|add-trojan|remove|list|stats}"
        exit 1
        ;;
esac
EOF

chmod +x $INSTALL_DIR/v2ray-manager.sh

# Step 13: Create log rotation config
log_info "Setting up log rotation..."
cat > /etc/logrotate.d/v2ray <<EOF
/var/log/v2ray/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 v2ray v2ray
    sharedscripts
    postrotate
        systemctl reload v2ray > /dev/null 2>&1 || true
    endscript
}
EOF

# Step 14: Enable and start service
log_info "Enabling V2Ray service..."
systemctl daemon-reload
systemctl enable v2ray

# Step 15: Test configuration
log_info "Testing V2Ray configuration..."
$INSTALL_DIR/v2ray test -config $CONFIG_DIR/config.json

if [ $? -eq 0 ]; then
    log_success "V2Ray configuration test passed"
    systemctl start v2ray

    if systemctl is-active --quiet v2ray; then
        log_success "V2Ray service started successfully"
    else
        log_error "Failed to start V2Ray service"
        journalctl -u v2ray -n 50
        exit 1
    fi
else
    log_error "V2Ray configuration test failed"
    exit 1
fi

# Step 16: Create update script
log_info "Creating update script..."
cat > $INSTALL_DIR/update-v2ray.sh <<'EOF'
#!/bin/bash

LATEST_VERSION=$(curl -s https://api.github.com/repos/v2fly/v2ray-core/releases/latest | jq -r .tag_name)
CURRENT_VERSION=$(/usr/local/v2ray/v2ray version | grep V2Ray | cut -d' ' -f2)

echo "Current version: $CURRENT_VERSION"
echo "Latest version: $LATEST_VERSION"

if [ "$CURRENT_VERSION" != "$LATEST_VERSION" ]; then
    echo "Updating V2Ray to $LATEST_VERSION..."
    bash <(curl -L https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh)
    systemctl restart v2ray
    echo "V2Ray updated successfully"
else
    echo "V2Ray is already up to date"
fi
EOF

chmod +x $INSTALL_DIR/update-v2ray.sh

# Step 17: Create uninstall script
log_info "Creating uninstall script..."
cat > $INSTALL_DIR/uninstall-v2ray.sh <<'EOF'
#!/bin/bash

echo "Uninstalling V2Ray..."

# Stop service
systemctl stop v2ray
systemctl disable v2ray

# Remove files
rm -rf /usr/local/v2ray
rm -rf /etc/v2ray
rm -rf /var/log/v2ray
rm -rf /var/lib/v2ray
rm -f /etc/systemd/system/v2ray.service
rm -f /etc/logrotate.d/v2ray

# Remove user
userdel -r v2ray 2>/dev/null

# Reload systemd
systemctl daemon-reload

echo "V2Ray uninstalled successfully"
EOF

chmod +x $INSTALL_DIR/uninstall-v2ray.sh

# Step 18: Display status
log_info "Checking V2Ray status..."
systemctl status v2ray --no-pager

# Summary
echo
echo "=========================================="
echo -e "${GREEN}V2Ray Installation Complete!${NC}"
echo "=========================================="
echo
echo "Installation directory: $INSTALL_DIR"
echo "Configuration: $CONFIG_DIR/config.json"
echo "Logs: $LOG_DIR"
echo "Service: systemctl {start|stop|restart|status} v2ray"
echo
echo "Management Commands:"
echo "  Add VMess client: $INSTALL_DIR/v2ray-manager.sh add-vmess <uuid> <email>"
echo "  Add VLESS client: $INSTALL_DIR/v2ray-manager.sh add-vless <uuid> <email>"
echo "  Add Trojan client: $INSTALL_DIR/v2ray-manager.sh add-trojan <password> <email>"
echo "  Remove client: $INSTALL_DIR/v2ray-manager.sh remove <email>"
echo "  List clients: $INSTALL_DIR/v2ray-manager.sh list"
echo "  View stats: $INSTALL_DIR/v2ray-manager.sh stats"
echo
echo "Update V2Ray: $INSTALL_DIR/update-v2ray.sh"
echo "Uninstall V2Ray: $INSTALL_DIR/uninstall-v2ray.sh"
echo
echo "Test with:"
echo "  VMess: wss://localhost:443/streaming"
echo "  VLESS: tcp://localhost:8443"
echo "  Trojan: wss://localhost:8444/trojan"
echo
log_success "Installation completed successfully!"

# Cleanup
cd /
rm -rf /tmp/v2ray*