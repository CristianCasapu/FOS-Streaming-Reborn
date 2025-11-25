#!/bin/bash
################################################################################
# FOS-Streaming v70 - Unified Installation Script
#
# This is the official installer for FOS-Streaming v70. It combines all
# previous installation scripts into a single, comprehensive installer.
#
# Features:
#   - Runs as normal user with sudo privileges
#   - PHP 8.4 with all modern extensions
#   - MariaDB 11.4 (latest stable) with UTF8MB4 support
#   - Nginx 1.26.x with HTTP-FLV, HTTP/2, HTTP/3 support
#   - FFmpeg latest static build
#   - NVM + Node.js 20 LTS for Vue.js frontend builds
#   - Composer 2.x for Laravel components
#   - Laravel Eloquent ORM integration
#   - Vue.js 3 + Vite 5 admin panel
#   - Enhanced security configurations
#   - Automated setup and deployment
#   - Environment-based configuration (.env)
#
# Repository: https://github.com/YourRepo/FOS-Streaming-v70
# Date: 2025-11-25
# Version: 70.0.0
# Supported OS: Debian 12 (Bookworm), Ubuntu 22.04 LTS, Ubuntu 24.04 LTS
# Run as: Normal user (will request sudo password)
#
# Usage:
#   chmod +x install/install.sh
#   ./install/install.sh
#
# Legacy alias also works:
#   ./install/debian12-installer
################################################################################

# Don't exit on error - we handle errors gracefully
set +e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# =============================================================================
# Dynamic Configuration - Detect from environment
# =============================================================================
PHP_VERSION="8.4"
MARIADB_VERSION="11.4"
NODE_VERSION="20"  # LTS version

# Detect current user (the one running the script, not root)
FOS_USER="$(whoami)"
FOS_USER_HOME="$(eval echo ~$FOS_USER)"

# Detect project directory (where this script is located)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FOS_DIR="$(dirname "$SCRIPT_DIR")"  # Parent of install/ directory

# Validate we're in a valid project directory
if [ ! -f "${FOS_DIR}/config.php" ] && [ ! -f "${FOS_DIR}/composer.json" ]; then
    echo -e "${RED}[ERROR]${NC} This script must be run from within the FOS-Streaming project directory."
    echo -e "${RED}[ERROR]${NC} Expected to find config.php or composer.json in: ${FOS_DIR}"
    exit 1
fi

# Configuration paths
PORTS_CONFIG="${FOS_DIR}/config/ports.php"
CERTS_DIR="${FOS_DIR}/fospackv69/fos/nginx/conf/certs"
STATE_FILE="${FOS_DIR}/.fos-install-state"
STATE_LOCK="${FOS_DIR}/.fos-install-lock"

# Ports will be selected during installation
WEB_PORT=""
STREAM_PORT=""
RTMP_PORT=""

# Installation state tracking
CURRENT_STEP=0
TOTAL_STEPS=22
declare -A INSTALL_STATE

# =============================================================================
# Logging Functions
# =============================================================================
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BLUE}===${NC} $1 ${BLUE}===${NC}\n"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_progress() {
    echo -e "${CYAN}[STEP ${CURRENT_STEP}/${TOTAL_STEPS}]${NC} $1"
}

# =============================================================================
# State Management Functions
# =============================================================================
save_state() {
    local step="$1"
    log_info "Saving installation state at step ${step}..."

    cat > "$STATE_FILE" <<EOF
# FOS-Streaming Installation State
# Generated: $(date '+%Y-%m-%d %H:%M:%S')
# Do not edit manually

SAVED_STEP=${step}
SAVED_WEB_PORT=${WEB_PORT}
SAVED_STREAM_PORT=${STREAM_PORT}
SAVED_RTMP_PORT=${RTMP_PORT}
SAVED_ADMIN_PATH=${ADMIN_PATH}
SAVED_DOMAIN_NAME=${DOMAIN_NAME}
SAVED_SQL_PASSWD=${SQL_PASSWD}
SAVED_APP_KEY=${APP_KEY}
SAVED_PUBLIC_IP=${PUBLIC_IP}
EOF
    chmod 600 "$STATE_FILE"
}

load_state() {
    if [ -f "$STATE_FILE" ]; then
        log_info "Found previous installation state..."
        source "$STATE_FILE"

        # Restore saved values
        WEB_PORT="${SAVED_WEB_PORT}"
        STREAM_PORT="${SAVED_STREAM_PORT}"
        RTMP_PORT="${SAVED_RTMP_PORT}"
        ADMIN_PATH="${SAVED_ADMIN_PATH}"
        DOMAIN_NAME="${SAVED_DOMAIN_NAME}"
        SQL_PASSWD="${SAVED_SQL_PASSWD}"
        APP_KEY="${SAVED_APP_KEY}"
        PUBLIC_IP="${SAVED_PUBLIC_IP}"

        return 0
    fi
    return 1
}

clear_state() {
    rm -f "$STATE_FILE" "$STATE_LOCK"
}

# =============================================================================
# Graceful Input Functions
# =============================================================================

# Read input with validation, retry on empty/invalid
# Usage: read_required "prompt" "variable_name" "validation_regex" "error_message"
read_required() {
    local prompt="$1"
    local var_name="$2"
    local validation="$3"
    local error_msg="$4"
    local default="$5"
    local value=""

    while true; do
        if [ -n "$default" ]; then
            read -p "${prompt} [${default}]: " value
            value="${value:-$default}"
        else
            read -p "${prompt}: " value
        fi

        # Check if empty (and no default)
        if [ -z "$value" ] && [ -z "$default" ]; then
            log_error "This field is required. Please enter a value."
            continue
        fi

        # Validate against regex if provided
        if [ -n "$validation" ]; then
            if [[ ! "$value" =~ $validation ]]; then
                log_error "$error_msg"
                continue
            fi
        fi

        # Set the variable
        eval "$var_name=\"$value\""
        break
    done
}

# Read yes/no with default
# Usage: read_confirm "prompt" "default_yes_or_no"
read_confirm() {
    local prompt="$1"
    local default="$2"
    local response

    while true; do
        if [ "$default" = "y" ]; then
            read -p "${prompt} [Y/n]: " response
            response="${response:-y}"
        else
            read -p "${prompt} [y/N]: " response
            response="${response:-n}"
        fi

        case "$response" in
            [Yy]|[Yy][Ee][Ss]) return 0 ;;
            [Nn]|[Nn][Oo]) return 1 ;;
            *) log_error "Please answer 'y' or 'n'" ;;
        esac
    done
}

# Read password with confirmation
# Usage: read_password "prompt" "variable_name"
read_password() {
    local prompt="$1"
    local var_name="$2"
    local pass1 pass2

    while true; do
        read -s -p "${prompt}: " pass1
        echo

        if [ -z "$pass1" ]; then
            log_error "Password cannot be empty. Please try again."
            continue
        fi

        if [ ${#pass1} -lt 8 ]; then
            log_error "Password must be at least 8 characters. Please try again."
            continue
        fi

        read -s -p "Confirm password: " pass2
        echo

        if [ "$pass1" != "$pass2" ]; then
            log_error "Passwords do not match. Please try again."
            continue
        fi

        eval "$var_name=\"$pass1\""
        break
    done
}

# =============================================================================
# Error Handler with Recovery
# =============================================================================
handle_error() {
    local step="$1"
    local message="$2"

    log_error "Installation failed at step ${step}: ${message}"
    save_state "$((step - 1))"

    echo ""
    log_warn "Installation state has been saved."
    log_warn "Run the installer again to resume from step $((step - 1))."
    echo ""

    if read_confirm "Would you like to retry this step now?"; then
        return 0  # Retry
    else
        exit 1
    fi
}

# Wrapper to run commands with error handling
run_cmd() {
    local step="$1"
    local description="$2"
    shift 2

    log_info "$description"
    if ! "$@"; then
        if read_confirm "Command failed. Retry?"; then
            if ! "$@"; then
                handle_error "$step" "$description failed"
                return 1
            fi
        else
            handle_error "$step" "$description failed"
            return 1
        fi
    fi
    return 0
}

# ============================================================================
# Initial Checks
# ============================================================================

# Check if running as root (we DON'T want root)
if [ "$EUID" -eq 0 ]; then
    log_error "This script should NOT be run as root."
    log_error "Run as normal user with sudo privileges: ./install/install.sh"
    exit 1
fi

# Check if user has sudo access
log_info "This script requires sudo privileges. You may be prompted for your password."
while ! sudo -v; do
    log_error "Failed to obtain sudo privileges. Please try again."
    if ! read_confirm "Retry sudo authentication?"; then
        exit 1
    fi
done

# Keep sudo alive throughout the script
(while true; do
    sudo -n true
    sleep 50
    kill -0 "$$" 2>/dev/null || exit
done) &
SUDO_KEEPER_PID=$!
trap "kill $SUDO_KEEPER_PID 2>/dev/null" EXIT

# =============================================================================
# OS Detection - Debian 12 / Ubuntu 22.04 / Ubuntu 24.04
# =============================================================================
OS_TYPE=""
OS_VERSION=""
OS_CODENAME=""

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_TYPE="$ID"
        OS_VERSION="$VERSION_ID"
        OS_CODENAME="$VERSION_CODENAME"
    elif [ -f /etc/debian_version ]; then
        OS_TYPE="debian"
        OS_VERSION=$(cat /etc/debian_version | cut -d. -f1)
    else
        OS_TYPE="unknown"
    fi

    log_info "Detected OS: ${OS_TYPE} ${OS_VERSION} (${OS_CODENAME})"
}

detect_os

# Validate supported OS
case "${OS_TYPE}" in
    debian)
        if [ "$OS_VERSION" != "12" ]; then
            log_warn "This script is optimized for Debian 12. Detected: Debian ${OS_VERSION}"
            if ! read_confirm "Continue anyway? (may require manual adjustments)"; then
                exit 1
            fi
        fi
        ;;
    ubuntu)
        # Extract major version (22, 24, etc.)
        UBUNTU_MAJOR=$(echo "$OS_VERSION" | cut -d. -f1)
        case "$UBUNTU_MAJOR" in
            22|24)
                log_info "Ubuntu ${OS_VERSION} detected - fully supported"
                ;;
            *)
                log_warn "This script is optimized for Ubuntu 22.x/24.x. Detected: Ubuntu ${OS_VERSION}"
                if ! read_confirm "Continue anyway? (may require manual adjustments)"; then
                    exit 1
                fi
                ;;
        esac
        ;;
    *)
        log_warn "This script is designed for Debian/Ubuntu systems. Detected: ${OS_TYPE}"
        if ! read_confirm "Continue on unsupported system? (may not work correctly)"; then
            exit 1
        fi
        ;;
esac

# ============================================================================
# Resume Previous Installation Check
# ============================================================================
RESUME_STEP=0

if load_state; then
    echo ""
    log_warn "Found a previous incomplete installation!"
    log_info "  Last successful step: ${SAVED_STEP}"
    log_info "  Domain: ${SAVED_DOMAIN_NAME:-not set}"
    log_info "  Admin path: ${SAVED_ADMIN_PATH:-not set}"
    log_info "  Ports: Web=${SAVED_WEB_PORT:-auto} Stream=${SAVED_STREAM_PORT:-auto} RTMP=${SAVED_RTMP_PORT:-auto}"
    echo ""

    echo "Options:"
    echo "  1) Resume from step $((SAVED_STEP + 1))"
    echo "  2) Start fresh (clear saved state)"
    echo "  3) Exit"
    echo ""

    while true; do
        read -p "Choose option [1-3]: " choice
        case "$choice" in
            1)
                RESUME_STEP=$((SAVED_STEP + 1))
                log_info "Resuming installation from step ${RESUME_STEP}..."
                break
                ;;
            2)
                clear_state
                RESUME_STEP=0
                log_info "Starting fresh installation..."
                break
                ;;
            3)
                log_info "Exiting. Run the installer again when ready."
                exit 0
                ;;
            *)
                log_error "Invalid choice. Please enter 1, 2, or 3."
                ;;
        esac
    done
fi

# ============================================================================
# Display Installation Info
# ============================================================================
log_step "FOS-Streaming v70 Enhanced Installation for ${OS_TYPE^} ${OS_VERSION}"
log_info "PHP Version: ${PHP_VERSION}"
log_info "MariaDB Version: ${MARIADB_VERSION}"
log_info "Node.js Version: ${NODE_VERSION} LTS"
log_info "Project Directory: ${FOS_DIR}"
log_info "Running as user: ${FOS_USER}"
log_info "User home: ${FOS_USER_HOME}"
echo ""

if [ $RESUME_STEP -gt 0 ]; then
    log_info "Resuming from step ${RESUME_STEP} with saved configuration."
else
    log_info "Starting new installation."
fi

# ============================================================================
# Port Selection Functions
# ============================================================================

is_port_available() {
    local port=$1
    if sudo lsof -i :${port} -sTCP:LISTEN -t >/dev/null 2>&1 ; then
        return 1
    fi
    if sudo netstat -tuln 2>/dev/null | grep -q ":${port} " ; then
        return 1
    fi
    return 0
}

get_random_ssl_port() {
    local cloudflare_ports=(2053 2083 2087 2096 8443)
    local exclude_ports=("$@")

    if command -v shuf &> /dev/null; then
        cloudflare_ports=($(printf '%s\n' "${cloudflare_ports[@]}" | shuf))
    fi

    for port in "${cloudflare_ports[@]}"; do
        local excluded=0
        for exclude_port in "${exclude_ports[@]}"; do
            if [ "$port" == "$exclude_port" ]; then
                excluded=1
                break
            fi
        done

        if [ $excluded -eq 0 ] && is_port_available $port; then
            echo $port
            return 0
        fi
    done

    for port in $(seq 8000 8999 | sort -R | head -20); do
        local excluded=0
        for exclude_port in "${exclude_ports[@]}"; do
            if [ "$port" == "$exclude_port" ]; then
                excluded=1
                break
            fi
        done

        if [ $excluded -eq 0 ] && is_port_available $port; then
            echo $port
            return 0
        fi
    done

    return 1
}

get_random_rtmp_port() {
    local exclude_ports=("$@")
    local port_ranges=(
        $(seq 1935 1999 | sort -R | head -10)
        $(seq 8000 8999 | sort -R | head -20)
    )

    if command -v shuf &> /dev/null; then
        port_ranges=($(printf '%s\n' "${port_ranges[@]}" | shuf))
    fi

    for port in "${port_ranges[@]}"; do
        local excluded=0
        for exclude_port in "${exclude_ports[@]}"; do
            if [ "$port" == "$exclude_port" ]; then
                excluded=1
                break
            fi
        done

        if [ $excluded -eq 0 ] && is_port_available $port; then
            echo $port
            return 0
        fi
    done

    return 1
}

select_cloudflare_ssl_ports() {
    log_step "Port Configuration (Cloudflare SSL Compatible)"

    # Cloudflare SSL-compatible ports
    # Source: https://developers.cloudflare.com/fundamentals/reference/network-ports/
    local CF_SSL_PORTS=(443 2053 2083 2087 2096 8443)

    log_info "Automatically selecting available Cloudflare SSL-compatible ports..."

    # Select Web Port
    WEB_PORT=""
    for port in "${CF_SSL_PORTS[@]}"; do
        if port_available "$port"; then
            WEB_PORT=$port
            log_info "Selected web port: ${WEB_PORT} (HTTPS, Cloudflare compatible)"
            break
        fi
    done
    [ -z "$WEB_PORT" ] && error_exit "Unable to find available Cloudflare SSL port for web service"

    # Select Streaming Port (different from web)
    STREAM_PORT=""
    for port in "${CF_SSL_PORTS[@]}"; do
        if [ "$port" != "$WEB_PORT" ] && port_available "$port"; then
            STREAM_PORT=$port
            log_info "Selected streaming port: ${STREAM_PORT} (HTTPS, Cloudflare compatible)"
            break
        fi
    done
    [ -z "$STREAM_PORT" ] && error_exit "Unable to find available Cloudflare SSL port for streaming service"

    # Select RTMP Port (from Cloudflare SSL ports, different from web and streaming)
    RTMP_PORT=""
    for port in "${CF_SSL_PORTS[@]}"; do
        if [ "$port" != "$WEB_PORT" ] && [ "$port" != "$STREAM_PORT" ] && port_available "$port"; then
            RTMP_PORT=$port
            log_info "Selected RTMP port: ${RTMP_PORT} (SSL, Cloudflare compatible)"
            break
        fi
    done
    [ -z "$RTMP_PORT" ] && error_exit "Unable to find available Cloudflare SSL port for RTMP service"

    log_info "Port allocation complete (all Cloudflare SSL compatible):"
    log_info "  Web (HTTPS):       ${WEB_PORT}"
    log_info "  Streaming (HTTPS): ${STREAM_PORT}"
    log_info "  RTMP (SSL):        ${RTMP_PORT}"
}

# Function to check if port is available
port_available() {
    local port=$1
    ! sudo lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1
}

# ============================================================================
# SSL Certificate Functions
# ============================================================================

generate_self_signed_cert() {
    local domain=$1
    local cert_dir=$2

    log_info "Generating self-signed SSL certificate..."

    sudo mkdir -p "$cert_dir"

    sudo openssl req -x509 -nodes -days 365 -newkey rsa:4096 \
        -keyout "${cert_dir}/privkey.pem" \
        -out "${cert_dir}/fullchain.pem" \
        -subj "/C=US/ST=State/L=City/O=FOS-Streaming/CN=${domain}" \
        2>/dev/null

    if [ $? -eq 0 ]; then
        log_info "Self-signed certificate generated successfully"
        sudo chmod 600 "${cert_dir}/privkey.pem"
        sudo chmod 644 "${cert_dir}/fullchain.pem"
        return 0
    else
        log_error "Failed to generate self-signed certificate"
        return 1
    fi
}

# ============================================================================
# STEP 0: Port Selection and Security Configuration
# ============================================================================
CURRENT_STEP=0

if [ $RESUME_STEP -le 0 ]; then
    log_step "Step 0: Port Selection and Security Configuration"
    log_progress "Configuring ports and security settings"

    # Automatically select Cloudflare SSL-compatible ports (only if not already set)
    if [ -z "$WEB_PORT" ] || [ -z "$STREAM_PORT" ] || [ -z "$RTMP_PORT" ]; then
        select_cloudflare_ssl_ports
    else
        log_info "Using saved port configuration:"
        log_info "  Web: ${WEB_PORT}, Stream: ${STREAM_PORT}, RTMP: ${RTMP_PORT}"
    fi

    # Admin Path Configuration
    log_step "Admin Panel Security Configuration"
    log_warn "IMPORTANT: The admin panel path should be unique and hard to guess for security."
    log_info "Examples: /control-xyz123, /management-abc456, /secure-admin-portal"
    log_info "Default: /admin (NOT RECOMMENDED for production)"
    echo ""

    # Only prompt if not already set from resume
    if [ -z "$ADMIN_PATH" ]; then
        while true; do
            read_required "Enter admin panel path" ADMIN_PATH "^/[a-zA-Z0-9_-]+$" \
                "Invalid admin path. Must start with / and contain only letters, numbers, hyphens, and underscores. Example: /my-admin-panel" \
                "/admin"

            # Warn if using default
            if [ "$ADMIN_PATH" = "/admin" ]; then
                log_warn "You are using the default admin path '/admin' which is NOT secure."
                if ! read_confirm "Are you sure you want to continue with /admin?"; then
                    ADMIN_PATH=""
                    continue
                fi
            fi

            log_info "Admin panel path set to: ${ADMIN_PATH}"
            break
        done
    else
        log_info "Using saved admin path: ${ADMIN_PATH}"
    fi

    # Domain Configuration
    log_step "Domain Configuration"
    log_info "This installation uses Cloudflare proxy with SSL/TLS."
    log_warn "Enter the domain name that points to this server (e.g., stream.example.com)"
    echo ""

    # Only prompt if not already set from resume
    if [ -z "$DOMAIN_NAME" ]; then
        # Domain validation regex (supports subdomain.domain.tld format)
        DOMAIN_REGEX="^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$"

        while true; do
            read_required "Enter your domain name" DOMAIN_NAME "$DOMAIN_REGEX" \
                "Invalid domain format. Please enter a valid domain (e.g., stream.example.com or example.com)"

            log_info "Domain set to: ${DOMAIN_NAME}"

            # Get server IP for reference
            PUBLIC_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
            log_info "Server IP: ${PUBLIC_IP}"
            log_warn "Make sure your domain ${DOMAIN_NAME} points to this IP in Cloudflare DNS"

            if read_confirm "Is this correct?"; then
                break
            fi
            # Reset to prompt again
            DOMAIN_NAME=""
        done
    else
        log_info "Using saved domain: ${DOMAIN_NAME}"
        # Get IP if not set
        if [ -z "$PUBLIC_IP" ]; then
            PUBLIC_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
        fi
        log_info "Server IP: ${PUBLIC_IP}"
    fi

    # Save state after successful configuration
    save_state 0
else
    log_info "Skipping Step 0 (already completed)"
fi

# ============================================================================
# STEP 1: System Update
# ============================================================================
CURRENT_STEP=1

if [ $RESUME_STEP -le 1 ]; then
    log_step "Step 1: Updating System"
    log_progress "Updating system packages"

    sudo apt-get update -y || handle_error 1 "apt-get update failed"
    sudo apt-get upgrade -y || handle_error 1 "apt-get upgrade failed"
    sudo apt-get dist-upgrade -y || log_warn "dist-upgrade had issues, continuing..."
    sudo apt-get autoremove -y || true

    save_state 1
else
    log_info "Skipping Step 1 (already completed)"
fi

# ============================================================================
# STEP 2: Install Build Dependencies
# ============================================================================
CURRENT_STEP=2

if [ $RESUME_STEP -le 2 ]; then
    log_step "Step 2: Installing Build Dependencies"
    log_progress "Installing build tools and utilities"

    sudo apt-get install -y \
        build-essential \
        libssl-dev \
        libpcre3 \
        libpcre3-dev \
        zlib1g-dev \
        curl \
        nano \
        wget \
        zip \
        unzip \
        git \
        lsof \
        iftop \
        htop \
        vim \
        ca-certificates \
        apt-transport-https \
        gnupg2 \
        software-properties-common \
        dirmngr \
        imagemagick \
        webp \
        icoutils || handle_error 2 "Failed to install build dependencies"

    save_state 2
else
    log_info "Skipping Step 2 (already completed)"
fi

# ============================================================================
# STEP 3: Install Library Dependencies
# ============================================================================
CURRENT_STEP=3

if [ $RESUME_STEP -le 3 ]; then
    log_step "Step 3: Installing Library Dependencies"
    log_progress "Installing development libraries"

    sudo apt-get install -y \
        libxml2-dev \
        libbz2-dev \
        libcurl4-openssl-dev \
        libxslt1-dev \
        libpq-dev \
        libsqlite3-dev \
        libgd-dev \
        libgeoip-dev \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxpm-dev \
        libtidy-dev \
        libzip-dev \
        libonig-dev \
        libreadline-dev \
        libedit-dev \
        libsodium-dev || handle_error 3 "Failed to install library dependencies"

    sudo apt-get autoremove -y || true

    save_state 3
else
    log_info "Skipping Step 3 (already completed)"
fi

# ============================================================================
# STEP 4: Setup PHP 8.4 from Sury Repository
# ============================================================================
CURRENT_STEP=4

if [ $RESUME_STEP -le 4 ]; then
    log_step "Step 4: Setting up PHP ${PHP_VERSION}"
    log_progress "Installing PHP ${PHP_VERSION} from Sury/Ondrej repository"

    log_info "Adding PHP repository for ${OS_TYPE}..."

    if [ "$OS_TYPE" = "ubuntu" ]; then
        # Ubuntu uses PPA
        sudo apt-get install -y software-properties-common || handle_error 4 "Failed to install software-properties-common"
        sudo add-apt-repository -y ppa:ondrej/php || handle_error 4 "Failed to add Ondrej PHP PPA"
    else
        # Debian uses Sury repository
        sudo curl -sSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php-sury.gpg || handle_error 4 "Failed to add PHP GPG key"
        echo "deb https://packages.sury.org/php/ ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/php-sury.list
    fi

    sudo apt-get update -y || handle_error 4 "apt-get update failed after adding PHP repo"

    log_info "Installing PHP ${PHP_VERSION} and extensions..."
    sudo apt-get install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-bz2 \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-xsl \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-imagick || handle_error 4 "Failed to install PHP packages"

    save_state 4
else
    log_info "Skipping Step 4 (already completed)"
fi

# ============================================================================
# STEP 4.5: Install Composer
# ============================================================================
CURRENT_STEP=5

if [ $RESUME_STEP -le 5 ]; then
    log_step "Step 4.5: Installing Composer"
    log_progress "Installing Composer package manager"

    log_info "Downloading and installing Composer..."
    cd /tmp
    curl -sS https://getcomposer.org/installer -o composer-setup.php || handle_error 5 "Failed to download Composer installer"
    sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer || handle_error 5 "Failed to install Composer"
    rm -f composer-setup.php

    if ! composer --version; then
        handle_error 5 "Composer installation verification failed"
    fi
    log_success "Composer installed successfully"

    save_state 5
else
    log_info "Skipping Step 4.5 (already completed)"
fi

# ============================================================================
# STEP 4.6: Install NVM and Node.js
# ============================================================================
CURRENT_STEP=6

if [ $RESUME_STEP -le 6 ]; then
    log_step "Step 4.6: Installing NVM and Node.js ${NODE_VERSION}"
    log_progress "Installing Node.js via NVM"

    log_info "Installing NVM (Node Version Manager)..."
    export NVM_DIR="${FOS_USER_HOME}/.nvm"

    # Download and install NVM
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash || handle_error 6 "Failed to install NVM"

    # Load NVM
    export NVM_DIR="${FOS_USER_HOME}/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

    # Install Node.js LTS
    log_info "Installing Node.js ${NODE_VERSION} LTS..."
    nvm install ${NODE_VERSION} || handle_error 6 "Failed to install Node.js"
    nvm use ${NODE_VERSION}
    nvm alias default ${NODE_VERSION}

    # Verify installation
    if ! node --version; then
        handle_error 6 "Node.js installation verification failed"
    fi
    if ! npm --version; then
        handle_error 6 "NPM installation verification failed"
    fi

    log_success "Node.js $(node --version) installed successfully"
    log_success "NPM $(npm --version) installed successfully"

    save_state 6
else
    log_info "Skipping Step 4.6 (already completed)"
    # Still need to load NVM for subsequent steps
    export NVM_DIR="${FOS_USER_HOME}/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
fi

# ============================================================================
# STEP 5: Configure PHP 8.4
# ============================================================================
CURRENT_STEP=7

if [ $RESUME_STEP -le 7 ]; then
    log_step "Step 5: Configuring PHP ${PHP_VERSION}"
    log_progress "Configuring PHP-FPM"

    # Create logs directory
    sudo mkdir -p "${FOS_DIR}/logs"

    log_info "Configuring PHP-FPM pool..."
    sudo tee /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf > /dev/null <<EOF
[php84]
user = ${FOS_USER}
group = ${FOS_USER}
listen = 127.0.0.1:9002
listen.owner = ${FOS_USER}
listen.group = ${FOS_USER}
pm = ondemand
pm.max_children = 300
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 100
pm.process_idle_timeout = 3s
pm.max_requests = 500
security.limit_extensions = .php
php_admin_value[error_log] = ${FOS_DIR}/logs/php-fpm.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 1128M
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300

; Session security
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.cookie_samesite] = "Strict"
php_admin_value[session.use_strict_mode] = 1
php_admin_value[session.use_only_cookies] = 1
php_admin_value[session.cookie_secure] = 0

; Security settings
php_admin_value[expose_php] = Off
php_admin_value[display_errors] = Off
php_admin_value[log_errors] = On
php_admin_value[error_reporting] = E_ALL
EOF

    log_info "Configuring php.ini..."
    PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"

    sudo sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' "$PHP_INI"
    sudo sed -i 's/output_buffering = 4096/output_buffering = Off/g' "$PHP_INI"
    sudo sed -i 's/expose_php = On/expose_php = Off/g' "$PHP_INI"
    sudo sed -i 's/;date.timezone =/date.timezone = UTC/g' "$PHP_INI"
    sudo sed -i 's/display_errors = On/display_errors = Off/g' "$PHP_INI"
    sudo sed -i 's/error_reporting = .*/error_reporting = E_ALL/g' "$PHP_INI"
    sudo sed -i 's/;opcache.enable=1/opcache.enable=1/g' "$PHP_INI"
    sudo sed -i 's/;opcache.memory_consumption=128/opcache.memory_consumption=256/g' "$PHP_INI"
    sudo sed -i 's/;opcache.interned_strings_buffer=8/opcache.interned_strings_buffer=16/g' "$PHP_INI"
    sudo sed -i 's/;opcache.max_accelerated_files=10000/opcache.max_accelerated_files=20000/g' "$PHP_INI"
    sudo sed -i 's/;opcache.validate_timestamps=1/opcache.validate_timestamps=0/g' "$PHP_INI"

    save_state 7
else
    log_info "Skipping Step 5 (already completed)"
fi

# ============================================================================
# STEP 6: Configure System Users and Permissions
# ============================================================================
CURRENT_STEP=8

if [ $RESUME_STEP -le 8 ]; then
    log_step "Step 6: Configuring System Users and Permissions"
    log_progress "Setting up user permissions"

    # Create nginx user for system services (if needed for compatibility)
    if ! id "nginx" &>/dev/null; then
        sudo useradd -r -s /sbin/nologin nginx
        log_info "Created nginx system user"
    fi

    # Current user (FOS_USER) already exists - just ensure proper sudo access
    log_info "Configuring sudo access for ${FOS_USER}..."

    # Check if sudoers file already exists
    if [ ! -f "/etc/sudoers.d/${FOS_USER}" ]; then
        echo "${FOS_USER} ALL=(ALL) NOPASSWD: ALL" | sudo tee /etc/sudoers.d/${FOS_USER} > /dev/null
        sudo chmod 0440 /etc/sudoers.d/${FOS_USER}
        log_info "${FOS_USER} added to sudoers with NOPASSWD"
    else
        log_info "${FOS_USER} already has sudo configuration"
    fi

    # Restart PHP-FPM
    sudo systemctl restart php${PHP_VERSION}-fpm || handle_error 8 "Failed to restart PHP-FPM"
    sudo systemctl enable php${PHP_VERSION}-fpm

    save_state 8
else
    log_info "Skipping Step 6 (already completed)"
fi

# ============================================================================
# STEP 7: Install MariaDB
# ============================================================================
CURRENT_STEP=9

if [ $RESUME_STEP -le 9 ]; then
    log_step "Step 7: Installing MariaDB ${MARIADB_VERSION}"
    log_progress "Installing and configuring MariaDB"

    log_info "Adding MariaDB ${MARIADB_VERSION} repository for ${OS_TYPE}..."
    sudo curl -o /etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc 'https://mariadb.org/mariadb_release_signing_key.asc' || handle_error 9 "Failed to add MariaDB GPG key"

    if [ "$OS_TYPE" = "ubuntu" ]; then
        # Ubuntu repository
        echo "deb [arch=amd64,arm64] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/ubuntu ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
    else
        # Debian repository
        echo "deb [arch=amd64,arm64] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/debian ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
    fi

    sudo apt-get update -y || handle_error 9 "apt-get update failed"
    sudo apt-get install -y mariadb-server mariadb-client || handle_error 9 "Failed to install MariaDB"

    # Generate strong MySQL root password (only if not already set from resume)
    if [ -z "$SQL_PASSWD" ]; then
        log_info "Generating MySQL root password..."
        SQL_PASSWD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    else
        log_info "Using saved MySQL password from previous run"
    fi
    echo "$SQL_PASSWD" | sudo tee /root/MYSQL_ROOT_PASSWORD > /dev/null
    sudo chmod 600 /root/MYSQL_ROOT_PASSWORD

    # Secure MariaDB installation
    log_info "Configuring MariaDB..."
    sudo systemctl stop mariadb
    sudo systemctl start mariadb

    # Set root password and secure installation
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${SQL_PASSWD}';" || true
    sudo mysql -u root -p"${SQL_PASSWD}" -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
    sudo mysql -u root -p"${SQL_PASSWD}" -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" 2>/dev/null || true
    sudo mysql -u root -p"${SQL_PASSWD}" -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
    sudo mysql -u root -p"${SQL_PASSWD}" -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" 2>/dev/null || true
    sudo mysql -u root -p"${SQL_PASSWD}" -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Create FOS database
    log_info "Creating FOS database..."
    sudo mysql -u root -p"${SQL_PASSWD}" -e "CREATE DATABASE IF NOT EXISTS fos_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || handle_error 9 "Failed to create database"
    sudo mysql -u root -p"${SQL_PASSWD}" -e "GRANT ALL PRIVILEGES ON fos_streaming.* TO 'fos'@'localhost' IDENTIFIED BY '${SQL_PASSWD}';"
    sudo mysql -u root -p"${SQL_PASSWD}" -e "FLUSH PRIVILEGES;"

    # Configure MariaDB for performance
    log_info "Optimizing MariaDB configuration..."
    sudo tee /etc/mysql/mariadb.conf.d/99-fos.cnf > /dev/null <<'MARIADB_EOF'
[mysqld]
# FOS-Streaming optimizations
max_connections = 500
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
query_cache_type = 0
query_cache_size = 0

# Security
local_infile = 0
bind-address = 127.0.0.1

# Performance
max_allowed_packet = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
MARIADB_EOF

    sudo systemctl restart mariadb || handle_error 9 "Failed to restart MariaDB"
    sudo systemctl enable mariadb

    save_state 9
else
    log_info "Skipping Step 7 (already completed)"
fi

# ============================================================================
# STEP 8: Setup Nginx from fospackv69
# ============================================================================
CURRENT_STEP=10

if [ $RESUME_STEP -le 10 ]; then
    log_step "Step 8: Setting up Nginx with HTTP-FLV Module"
    log_progress "Configuring Nginx from fospackv69"

    # Check if fospackv69 exists in project directory
    FOSPACK_DIR="${FOS_DIR}/fospackv69"

    if [ ! -d "$FOSPACK_DIR" ]; then
        log_info "fospackv69 not found in project, cloning..."
        cd "${FOS_DIR}"
        git clone --recurse-submodules https://github.com/theraw/fospackv69.git || handle_error 10 "Failed to clone fospackv69"
    else
        log_info "fospackv69 found in project directory"
        # Update submodules if needed
        cd "$FOSPACK_DIR"
        git submodule update --init --recursive 2>/dev/null || true
    fi

    # Build nginx if not already built
    NGINX_BIN="${FOSPACK_DIR}/fos/nginx/sbin/nginx_fos"
    if [ ! -x "$NGINX_BIN" ]; then
        log_info "Building nginx (this will take several minutes)..."
        cd "${FOSPACK_DIR}/nginx-builder"

        # Try OS-specific build script first, then fall back to generic
        if [ "$OS_TYPE" = "ubuntu" ] && [ -f "build-ubuntu.sh" ]; then
            log_info "Using Ubuntu optimized build script..."
            sudo bash build-ubuntu.sh || handle_error 10 "Nginx build failed"
        elif [ -f "build-debian12.sh" ]; then
            log_info "Using Debian/Ubuntu compatible build script..."
            sudo bash build-debian12.sh || handle_error 10 "Nginx build failed"
        elif [ -f "build-for-project.sh" ]; then
            log_info "Using project build script..."
            sudo bash build-for-project.sh || handle_error 10 "Nginx build failed"
        elif [ -f "build.sh" ]; then
            log_warn "Using generic build script..."
            sudo bash build.sh || handle_error 10 "Nginx build failed"
        else
            log_warn "No build script found, checking for pre-built binary..."
        fi
    else
        log_info "Nginx binary already exists at ${NGINX_BIN}"
    fi

    # Create required directories
    log_info "Creating FOS directory structure..."
    sudo mkdir -p "${FOS_DIR}/logs"
    sudo mkdir -p "${FOS_DIR}/hl"
    sudo mkdir -p "${FOS_DIR}/cache"
    sudo mkdir -p "${FOS_DIR}/storage/framework/cache"
    sudo mkdir -p "${FOS_DIR}/storage/framework/sessions"
    sudo mkdir -p "${FOS_DIR}/storage/framework/views"
    sudo mkdir -p "${FOS_DIR}/storage/logs"

    save_state 10
else
    log_info "Skipping Step 8 (already completed)"
fi

# ============================================================================
# STEP 9: Verify Web Application Files
# ============================================================================
CURRENT_STEP=11

if [ $RESUME_STEP -le 11 ]; then
    log_step "Step 9: Verifying Web Application"
    log_progress "Checking web application files"

    # The web application IS the current project directory
    # No need to clone - just verify essential files exist
    cd "${FOS_DIR}"

    if [ ! -f "config.php" ]; then
        log_warn "config.php not found - checking if this is a fresh clone"
        if [ -f "config.php.example" ]; then
            cp config.php.example config.php
            log_info "Created config.php from example"
        fi
    fi

    if [ ! -f "composer.json" ]; then
        handle_error 11 "composer.json not found - this doesn't appear to be a valid FOS-Streaming installation"
    fi

    log_success "Web application files verified"

    save_state 11
else
    log_info "Skipping Step 9 (already completed)"
fi

# ============================================================================
# STEP 10: Install Composer Dependencies
# ============================================================================
CURRENT_STEP=12

if [ $RESUME_STEP -le 12 ]; then
    log_step "Step 10: Installing PHP Dependencies with Composer"
    log_progress "Installing Composer packages"

    cd "${FOS_DIR}"

    log_info "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction || handle_error 12 "Composer install failed"

    log_success "Composer dependencies installed successfully"

    save_state 12
else
    log_info "Skipping Step 10 (already completed)"
fi

# ============================================================================
# STEP 11: Setup Production Environment
# ============================================================================
CURRENT_STEP=13

if [ $RESUME_STEP -le 13 ]; then
    log_step "Step 11: Creating Production .env File"
    log_progress "Configuring environment variables"

    # Generate app key only if not already set
    if [ -z "$APP_KEY" ]; then
        log_info "Generating application encryption key..."
        APP_KEY="base64:$(openssl rand -base64 32)"
    else
        log_info "Using saved application key"
    fi

    # Detect FFmpeg paths (needed for .env)
    FFMPEG_BIN=$(which ffmpeg 2>/dev/null || echo "/usr/local/bin/ffmpeg")
    FFPROBE_BIN=$(which ffprobe 2>/dev/null || echo "/usr/local/bin/ffprobe")

    log_info "Creating production .env file..."
    tee "${FOS_DIR}/.env" > /dev/null <<ENV_EOF
# Application
APP_NAME="FOS Streaming v70"
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY}
APP_URL=https://${DOMAIN_NAME}
APP_DOMAIN=${DOMAIN_NAME}
APP_TIMEZONE=UTC

# Installation Info
FOS_DIR=${FOS_DIR}
FOS_USER=${FOS_USER}

# Laravel Sail
WWWUSER=$(id -u)
WWWGROUP=$(id -g)
APP_PORT=${WEB_PORT}
VITE_PORT=5173

# Streaming Ports
STREAMING_PORT=${STREAM_PORT}
RTMP_PORT=${RTMP_PORT}
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443

# Database (MariaDB)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=${SQL_PASSWD}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_PREFIX=

# Redis (if installed)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info

# Template Paths
VIEWS_PATH=views
CACHE_PATH=cache

# Streaming Configuration
WEB_IP=*
DOMAIN=${DOMAIN_NAME}
STREAMING_AUTH=true
FAIL2BAN_ENABLED=true
UFW_ENABLED=false

# FFmpeg
FFMPEG_PATH=${FFMPEG_BIN}
FFPROBE_PATH=${FFPROBE_BIN}

# HLS Output
HLS_PATH=${FOS_DIR}/hl
HLS_URL_PREFIX=https://${DOMAIN_NAME}/live

# Security Features
RATE_LIMIT_ENABLED=true
MAX_LOGIN_ATTEMPTS=5
LOGIN_TIMEOUT=900
SECURITY_LOGGING=true

# Session
SESSION_LIFETIME=120
CSRF_TOKEN_TIMEOUT=7200

# Admin Panel Security
ADMIN_PATH=${ADMIN_PATH}

# Server Ports (Cloudflare SSL Compatible)
WEB_PORT=${WEB_PORT}
HTTPS_PORT=443

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@fos-streaming.local"
MAIL_FROM_NAME="\${APP_NAME}"

# Mailpit Dashboard
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025

# Database Ports
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
ENV_EOF

    chmod 600 "${FOS_DIR}/.env"
    chown ${FOS_USER}:${FOS_USER} "${FOS_DIR}/.env"

    log_success "Production .env file created"

    save_state 13
else
    log_info "Skipping Step 11 (already completed)"
fi

# ============================================================================
# STEP 12: Install NPM Dependencies and Build Frontend
# ============================================================================
CURRENT_STEP=14

if [ $RESUME_STEP -le 14 ]; then
    log_step "Step 12: Installing NPM Dependencies and Building Frontend"
    log_progress "Building frontend assets"

    if [ -f "${FOS_DIR}/package.json" ]; then
        log_info "package.json found, installing NPM dependencies..."

        cd "${FOS_DIR}"

        # Load NVM for this shell
        export NVM_DIR="${FOS_USER_HOME}/.nvm"
        [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

        # Install dependencies
        npm install || handle_error 14 "npm install failed"

        # Update frontend config with admin path
        if [ -f "resources/js/config.js" ]; then
            log_info "Updating frontend config with admin path..."
            sed -i "s|export const ADMIN_PATH = '.*'|export const ADMIN_PATH = '${ADMIN_PATH}'|" resources/js/config.js
            log_info "Frontend config updated"
        fi

        # Build production assets
        if grep -q "\"build\":" package.json; then
            log_info "Running npm run build..."
            npm run build || handle_error 14 "npm build failed"
            log_success "Frontend assets built successfully"
        else
            log_warn "No build script found in package.json, skipping build step"
        fi
    else
        log_info "No package.json found, skipping NPM setup"
    fi

    save_state 14
else
    log_info "Skipping Step 12 (already completed)"
fi

# ============================================================================
# STEP 13: Configure Application
# ============================================================================
CURRENT_STEP=15

if [ $RESUME_STEP -le 15 ]; then
    log_step "Step 13: Configuring FOS-Streaming Application"
    log_progress "Setting up application configuration"

    cd "${FOS_DIR}"

    # Update database configuration in config.php if it exists and has placeholders
    if [ -f "config.php" ]; then
        log_info "Updating database configuration..."
        sed -i "s/'xxx'/'fos_streaming'/g" "config.php" 2>/dev/null || true
        sed -i "s/'zzz'/'${SQL_PASSWD}'/g" "config.php" 2>/dev/null || true
        sed -i "s/'ttt'/'fos'/g" "config.php" 2>/dev/null || true
    fi

    # Create required directories
    log_info "Creating application directories..."
    mkdir -p "${FOS_DIR}/config"
    mkdir -p "${FOS_DIR}/lib"
    mkdir -p "${FOS_DIR}/hl"
    chmod 777 "${FOS_DIR}/hl"
    mkdir -p "${FOS_DIR}/cache"
    chmod 777 "${FOS_DIR}/cache"
    mkdir -p "${FOS_DIR}/storage/framework/cache"
    mkdir -p "${FOS_DIR}/storage/framework/sessions"
    mkdir -p "${FOS_DIR}/storage/framework/views"
    mkdir -p "${FOS_DIR}/storage/logs"
    chmod -R 775 "${FOS_DIR}/storage"
    mkdir -p "${FOS_DIR}/logs"

    # Save port configuration
    log_info "Saving port configuration..."
    tee "${FOS_DIR}/config/ports.php" > /dev/null <<PORTS_EOF
<?php
/**
 * FOS-Streaming Port Configuration
 * Generated: $(date '+%Y-%m-%d %H:%M:%S')
 */

return [
    'web_port' => ${WEB_PORT},
    'stream_port' => ${STREAM_PORT},
    'rtmp_port' => ${RTMP_PORT},
];
PORTS_EOF

    chmod 644 "${FOS_DIR}/config/ports.php"

    # Create PHP symlink
    sudo mkdir -p "${FOS_DIR}/php/bin"
    sudo ln -sf /usr/bin/php "${FOS_DIR}/php/bin/php"

    # Set permissions - use current user, not hardcoded
    log_info "Setting permissions..."
    chown -R ${FOS_USER}:${FOS_USER} "${FOS_DIR}"

    # Ensure fospackv69 nginx has correct ownership
    if [ -d "${FOS_DIR}/fospackv69/fos/nginx" ]; then
        chown -R ${FOS_USER}:${FOS_USER} "${FOS_DIR}/fospackv69/fos/nginx"
    fi

    save_state 15
else
    log_info "Skipping Step 13 (already completed)"
fi

# ============================================================================
# STEP 14: Install FFmpeg
# ============================================================================
CURRENT_STEP=16

if [ $RESUME_STEP -le 16 ]; then
    log_step "Step 14: Installing FFmpeg"
    log_progress "Installing FFmpeg static build"

    # Check if FFmpeg already installed
    if command -v ffmpeg &> /dev/null; then
        FFMPEG_BIN=$(which ffmpeg)
        FFPROBE_BIN=$(which ffprobe)
        log_info "FFmpeg already installed at ${FFMPEG_BIN}"
    else
        log_info "Downloading latest FFmpeg static build..."
        wget -q "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz" -O /tmp/ffmpeg-release-amd64-static.tar.xz || handle_error 16 "Failed to download FFmpeg"

        log_info "Extracting FFmpeg..."
        tar -xJf /tmp/ffmpeg-release-amd64-static.tar.xz -C /tmp/ || handle_error 16 "Failed to extract FFmpeg"

        log_info "Installing FFmpeg binaries..."
        sudo cp /tmp/ffmpeg-*/ffmpeg /usr/local/bin/ffmpeg
        sudo cp /tmp/ffmpeg-*/ffprobe /usr/local/bin/ffprobe
        sudo chmod 755 /usr/local/bin/ffmpeg
        sudo chmod 755 /usr/local/bin/ffprobe

        rm -rf /tmp/ffmpeg-*

        FFMPEG_BIN="/usr/local/bin/ffmpeg"
        FFPROBE_BIN="/usr/local/bin/ffprobe"
    fi

    # Verify the binaries exist and are executable
    if [ ! -x "$FFMPEG_BIN" ]; then
        handle_error 16 "FFmpeg not found or not executable at: $FFMPEG_BIN"
    fi

    if [ ! -x "$FFPROBE_BIN" ]; then
        handle_error 16 "FFprobe not found or not executable at: $FFPROBE_BIN"
    fi

    log_success "FFmpeg detected at: ${FFMPEG_BIN}"
    log_success "FFprobe detected at: ${FFPROBE_BIN}"

    # Verify versions
    FFMPEG_VERSION=$($FFMPEG_BIN -version 2>/dev/null | head -n1 | awk '{print $3}')
    log_info "FFmpeg version: ${FFMPEG_VERSION}"

    # Add to sudoers for current user
    log_info "Configuring FFmpeg sudo access..."
    sudo rm -f /etc/sudoers.d/fos-ffmpeg 2>/dev/null || true
    echo "${FOS_USER} ALL = (root) NOPASSWD: ${FFMPEG_BIN}" | sudo tee /etc/sudoers.d/fos-ffmpeg > /dev/null
    echo "${FOS_USER} ALL = (root) NOPASSWD: ${FFPROBE_BIN}" | sudo tee -a /etc/sudoers.d/fos-ffmpeg > /dev/null
    sudo chmod 0440 /etc/sudoers.d/fos-ffmpeg

    save_state 16
else
    log_info "Skipping Step 14 (already completed)"
    # Still need to set FFmpeg paths
    FFMPEG_BIN=$(which ffmpeg 2>/dev/null || echo "/usr/local/bin/ffmpeg")
    FFPROBE_BIN=$(which ffprobe 2>/dev/null || echo "/usr/local/bin/ffprobe")
fi

# ============================================================================
# STEP 15: Install Streaming Security Tools (SRT, QUIC, V2Ray)
# ============================================================================
CURRENT_STEP=17

if [ $RESUME_STEP -le 17 ]; then
    log_step "Step 15: Installing Streaming Security Tools"
    log_progress "Installing security and streaming tools"

    log_info "Installing SRT (Secure Reliable Transport) libraries..."
    sudo apt-get install -y \
        libsrt-openssl-dev \
        srt-tools \
        pkg-config || log_warn "Some SRT packages may not be available on this system"

    log_info "Installing QUIC/HTTP3 libraries..."
    sudo apt-get install -y \
        libngtcp2-dev \
        libnghttp3-dev || log_warn "QUIC libraries may need manual installation"

    log_info "Installing additional security tools..."
    sudo apt-get install -y \
        redis-server \
        fail2ban \
        ufw \
        jq \
        unzip || log_warn "Some security tools may not be available"

    # Configure Redis for session management
    log_info "Configuring Redis..."
    sudo systemctl enable redis-server 2>/dev/null || true
    sudo systemctl start redis-server 2>/dev/null || true

    # Verify SRT installation
    if command -v srt-live-transmit &> /dev/null; then
        SRT_VERSION=$(srt-live-transmit -version 2>&1 | head -n1)
        log_success "SRT tools installed: ${SRT_VERSION}"
    else
        log_warn "SRT tools installation needs verification"
    fi

    # V2Ray installation (optional)
    V2RAY_INSTALL_SCRIPT="${FOS_DIR}/fospackv69/nginx-builder/install-v2ray.sh"
    if [ -f "${V2RAY_INSTALL_SCRIPT}" ]; then
        log_info "V2Ray installation script found at: ${V2RAY_INSTALL_SCRIPT}"
        log_info "Skipping automatic V2Ray installation - run manually if needed"
    fi

    save_state 17
else
    log_info "Skipping Step 15 (already completed)"
fi

# ============================================================================
# STEP 16: Configure Nginx and SSL Certificates
# ============================================================================
CURRENT_STEP=18

# Define nginx paths based on fospackv69 location
NGINX_DIR="${FOS_DIR}/fospackv69/fos/nginx"
NGINX_CONF="${NGINX_DIR}/conf/nginx.conf"
NGINX_BIN="${NGINX_DIR}/sbin/nginx_fos"

if [ $RESUME_STEP -le 18 ]; then
    log_step "Step 16: Configuring Nginx and SSL Certificates"
    log_progress "Setting up SSL and Nginx configuration"

    # Check if nginx directory exists
    if [ ! -d "${NGINX_DIR}" ]; then
        log_warn "Nginx directory not found at ${NGINX_DIR}"
        log_info "Skipping Nginx configuration - you may need to build it manually"
    else
        log_info "Generating self-signed certificate for ${DOMAIN_NAME}..."
        generate_self_signed_cert "${DOMAIN_NAME}" "${CERTS_DIR}"

        if [ -f "${NGINX_CONF}" ]; then
            log_info "Configuring nginx with domain: ${DOMAIN_NAME}, ports - Web: ${WEB_PORT}, Stream: ${STREAM_PORT}, RTMP: ${RTMP_PORT}..."

            # Update HTTPS ports in nginx config
            sudo sed -i "s/listen 8000;/listen ${STREAM_PORT} ssl http2;/g" "${NGINX_CONF}" 2>/dev/null || true
            sudo sed -i "s/listen \[::\]:8000;/listen [::]:${STREAM_PORT} ssl http2;/g" "${NGINX_CONF}" 2>/dev/null || true
            sudo sed -i "s/listen 7777;/listen ${WEB_PORT} ssl http2;/g" "${NGINX_CONF}" 2>/dev/null || true
            sudo sed -i "s/listen \[::\]:7777;/listen [::]:${WEB_PORT} ssl http2;/g" "${NGINX_CONF}" 2>/dev/null || true
            sudo sed -i "s/listen 1935;/listen ${RTMP_PORT};/g" "${NGINX_CONF}" 2>/dev/null || true

            # Set ownership
            sudo chown -R ${FOS_USER}:${FOS_USER} "${NGINX_DIR}"
        else
            log_warn "Nginx config not found at ${NGINX_CONF}"
        fi
    fi

    save_state 18
else
    log_info "Skipping Step 16 (already completed)"
fi

# ============================================================================
# STEP 17: Configure System Startup
# ============================================================================
CURRENT_STEP=19

if [ $RESUME_STEP -le 19 ]; then
    log_step "Step 17: Configuring System Startup"
    log_progress "Creating systemd services"

    # Only create service if nginx binary exists
    if [ -x "${NGINX_BIN}" ]; then
        sudo tee /etc/systemd/system/fos-nginx.service > /dev/null <<SERVICE_EOF
[Unit]
Description=FOS-Streaming Nginx Server
After=network.target

[Service]
Type=forking
User=${FOS_USER}
Group=${FOS_USER}
PIDFile=${NGINX_DIR}/pid/nginx.pid
ExecStartPre=${NGINX_BIN} -t -c ${NGINX_CONF}
ExecStart=${NGINX_BIN} -c ${NGINX_CONF}
ExecReload=/bin/kill -s HUP \$MAINPID
ExecStop=/bin/kill -s QUIT \$MAINPID
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
SERVICE_EOF

        sudo systemctl daemon-reload
        sudo systemctl enable fos-nginx 2>/dev/null || true
    else
        log_warn "Nginx binary not found, skipping service creation"
    fi

    sudo systemctl enable php${PHP_VERSION}-fpm 2>/dev/null || true

    log_info "Configuring system kernel parameters for high concurrency..."
    sudo tee /etc/sysctl.d/99-fos-streaming.conf > /dev/null <<'SYSCTL_EOF'
# FOS-Streaming Network Performance Tuning
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.ip_local_port_range = 1024 65535
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 15
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216

# File Descriptors
fs.file-max = 2000000
fs.nr_open = 2000000
SYSCTL_EOF
    sudo sysctl -p /etc/sysctl.d/99-fos-streaming.conf 2>/dev/null || true

    log_info "Configuring user limits for high concurrency..."
    sudo tee /etc/security/limits.d/99-fos-streaming.conf > /dev/null <<LIMITS_EOF
# FOS-Streaming User Limits
${FOS_USER} soft nofile 1000000
${FOS_USER} hard nofile 1000000
${FOS_USER} soft nproc 65535
${FOS_USER} hard nproc 65535
* soft nofile 65535
* hard nofile 65535
LIMITS_EOF

    save_state 19
else
    log_info "Skipping Step 17 (already completed)"
fi

# ============================================================================
# STEP 18: Initialize Database
# ============================================================================
CURRENT_STEP=20

if [ $RESUME_STEP -le 20 ]; then
    log_step "Step 18: Initializing Database"
    log_progress "Running database migrations and seeders via artisan"

    cd "${FOS_DIR}"

    if [ ! -f "artisan" ]; then
        handle_error 20 "Artisan CLI not found at ${FOS_DIR}/artisan"
    fi

    # Run database migrations
    log_info "Running database migrations..."
    if php artisan migrate --force; then
        log_success "Database migrations completed successfully"
    else
        handle_error 20 "Database migrations failed. Check your database credentials in .env"
    fi

    # Run database seeders
    log_info "Running database seeders..."
    if php artisan db:seed --force; then
        log_success "Database seeded successfully"
    else
        handle_error 20 "Database seeding failed"
    fi

    # Show migration status
    log_info "Migration status:"
    php artisan migrate:status --no-ansi 2>/dev/null || true

    log_success "Database initialized with migrations and seeders"

    save_state 20
else
    log_info "Skipping Step 18 (already completed)"
fi

# ============================================================================
# STEP 19: Setup Cron Job
# ============================================================================
CURRENT_STEP=21

if [ $RESUME_STEP -le 21 ]; then
    log_step "Step 19: Setting up Cron Job"
    log_progress "Configuring scheduled tasks"

    log_info "Adding cron job for stream monitoring..."
    CRON_CMD="*/2 * * * * /usr/bin/php ${FOS_DIR}/cron.php"
    (crontab -l 2>/dev/null | grep -v "cron.php"; echo "$CRON_CMD") | crontab -

    save_state 21
else
    log_info "Skipping Step 19 (already completed)"
fi

# ============================================================================
# STEP 20: Security Hardening
# ============================================================================
CURRENT_STEP=22

if [ $RESUME_STEP -le 22 ]; then
    log_step "Step 20: Applying Security Hardening"
    log_progress "Configuring firewall and permissions"

    if command -v ufw &> /dev/null; then
        log_info "Configuring firewall rules..."
        sudo ufw allow ${WEB_PORT}/tcp comment 'FOS Web Panel HTTPS' 2>/dev/null || true
        sudo ufw allow ${STREAM_PORT}/tcp comment 'FOS Streaming HTTPS' 2>/dev/null || true
        sudo ufw allow ${RTMP_PORT}/tcp comment 'FOS RTMP' 2>/dev/null || true
    fi

    # Set secure permissions on config files
    [ -f "${FOS_DIR}/config.php" ] && chmod 600 "${FOS_DIR}/config.php"
    [ -f "${FOS_DIR}/config/ports.php" ] && chmod 644 "${FOS_DIR}/config/ports.php"
    [ -f "${FOS_DIR}/.env" ] && chmod 600 "${FOS_DIR}/.env"
    [ -f "/root/MYSQL_ROOT_PASSWORD" ] && sudo chmod 600 /root/MYSQL_ROOT_PASSWORD

    save_state 22
else
    log_info "Skipping Step 20 (already completed)"
fi

# ============================================================================
# INSTALLATION COMPLETE
# ============================================================================

# Clear installation state on successful completion
clear_state

log_step "Installation Complete!"

# Get versions for display
COMPOSER_VER=$(composer --version 2>/dev/null | head -n1 | awk '{print $3}' || echo "not installed")
NODE_VER=$(node --version 2>/dev/null || echo "not installed")
NPM_VER=$(npm --version 2>/dev/null || echo "not installed")

cat <<COMPLETE_EOF

================================================================
  FOS-Streaming v70 Enhanced Installation Successful!
================================================================

  SECURE HTTPS INSTALLATION (Cloudflare Proxied)

  Domain:            ${DOMAIN_NAME}
  Subscriber Portal: https://${DOMAIN_NAME}/
  Admin Panel:       https://${DOMAIN_NAME}${ADMIN_PATH}

  Direct Access (Bypass Cloudflare if needed):
    Web:       https://${PUBLIC_IP}:${WEB_PORT}
    Streaming: https://${PUBLIC_IP}:${STREAM_PORT}
    RTMP:      rtmp://${PUBLIC_IP}:${RTMP_PORT}

  Default Admin Credentials:
    Username:     admin
    Password:     admin

  SECURITY IMPORTANT:
    1. Change the default admin password immediately!
    2. Your admin path: ${ADMIN_PATH}
    3. All ports are Cloudflare SSL compatible

================================================================
  Installed Components:
================================================================

  PHP ${PHP_VERSION} with Laravel components
  Composer ${COMPOSER_VER}
  Node.js ${NODE_VER}
  NPM ${NPM_VER}
  MariaDB ${MARIADB_VERSION}
  FFmpeg ${FFMPEG_VERSION:-latest}
  Self-signed SSL certificates
  High-concurrency kernel optimizations

================================================================
  Installation Details:
================================================================

  Operating System:  ${OS_TYPE^} ${OS_VERSION} (${OS_CODENAME})
  Installation User: ${FOS_USER}
  Project Directory: ${FOS_DIR}
  User Home:         ${FOS_USER_HOME}

================================================================
  Database Credentials:
================================================================

  MySQL Root Password: /root/MYSQL_ROOT_PASSWORD
  Database: fos_streaming
  DB User:  fos
  DB Pass:  (same as MySQL root, also in .env)

================================================================
  Environment Files:
================================================================

  Production .env:    ${FOS_DIR}/.env
  Ports config:       ${FOS_DIR}/config/ports.php

================================================================
  Post-Installation Steps:
================================================================

  1. Login to web panel: https://${PUBLIC_IP}:${WEB_PORT}
     (Accept self-signed certificate warning)

  2. Go to Settings and update 'Web ip' to: ${PUBLIC_IP}

  3. Change default admin password

  4. Configure your first stream

  5. (Optional) Setup Let's Encrypt SSL

================================================================
  Service Management:
================================================================

  # Admin Panel Services
  systemctl status fos-nginx
  systemctl status php${PHP_VERSION}-fpm
  systemctl status mariadb

  # View logs
  journalctl -u fos-nginx -f
  tail -f ${FOS_DIR}/logs/*.log

================================================================
  NVM Usage (for ${FOS_USER}):
================================================================

  To use NVM in new shells, add to ~/.bashrc:
    export NVM_DIR="\$HOME/.nvm"
    [ -s "\$NVM_DIR/nvm.sh" ] && . "\$NVM_DIR/nvm.sh"

  Commands:
    nvm use ${NODE_VERSION}
    nvm ls
    node --version
    npm --version

================================================================

COMPLETE_EOF

log_success "Installation script finished successfully!"
log_info "Project directory: ${FOS_DIR}"
log_info "Run 'npm run dev' to start the development server"
log_info "Enjoy your FOS-Streaming installation!"
