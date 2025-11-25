#!/bin/bash
# FOS-Streaming Project-Specific Nginx Build Script
# Builds nginx with the current user settings and project paths
#
# Usage: sudo -E bash build-for-project.sh

set -e

# Project-specific settings
export FOS_NGINX_PREFIX="/home/casapu/projects/FOS-Streaming-v69/fospackv69/fos/nginx"
export FOS_USER="casapu"
export FOS_GROUP="casapu"
export FOS_LOG_DIR="/home/casapu/projects/FOS-Streaming-v69/fospackv69/fos/logs"

# Get the directory of this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=============================================="
echo "  FOS-Streaming Nginx Build"
echo "=============================================="
echo ""
echo "Configuration:"
echo "  Install prefix: ${FOS_NGINX_PREFIX}"
echo "  User/Group: ${FOS_USER}:${FOS_GROUP}"
echo "  Log directory: ${FOS_LOG_DIR}"
echo ""

# Verify we're running as root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: This script must be run as root (with sudo -E)"
    echo "Usage: sudo -E bash build-for-project.sh"
    exit 1
fi

# Create logs directory if it doesn't exist
mkdir -p "${FOS_LOG_DIR}"
chown "${FOS_USER}:${FOS_GROUP}" "${FOS_LOG_DIR}"

# Create streams storage directories
STREAMS_PATH="/tmp/streams"
mkdir -p "${STREAMS_PATH}/dash"
mkdir -p "${STREAMS_PATH}/hls"
chown -R "${FOS_USER}:${FOS_GROUP}" "${STREAMS_PATH}"
chmod -R 755 "${STREAMS_PATH}"

echo "Created streams directories at ${STREAMS_PATH}"

# Run the main build script
cd "${SCRIPT_DIR}"
bash build-debian12.sh

echo ""
echo "=============================================="
echo "  Build Complete for FOS-Streaming Project!"
echo "=============================================="
echo ""
echo "Binary location: ${FOS_NGINX_PREFIX}/sbin/nginx_fos"
echo "Config location: ${FOS_NGINX_PREFIX}/conf/"
echo "Logs location: ${FOS_LOG_DIR}"
echo "Streams storage: ${STREAMS_PATH}"
echo ""
echo "Next steps:"
echo "  1. Update nginx-streaming.conf for DASH support"
echo "  2. Test with: ${FOS_NGINX_PREFIX}/sbin/nginx_fos -t -c ${FOS_NGINX_PREFIX}/conf/nginx-streaming.conf"
echo "  3. Start with: ${FOS_NGINX_PREFIX}/sbin/nginx_fos -c ${FOS_NGINX_PREFIX}/conf/nginx-streaming.conf"
echo "=============================================="
