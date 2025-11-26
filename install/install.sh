#!/usr/bin/env bash
################################################################################
# FOS-Streaming v70 - Unified Installation Script
#
# This is the official installer for FOS-Streaming v70. It combines all
# previous installation scripts into a single, comprehensive installer.
#
# Features:
#   - Bootstrap system: Handles fresh OS installations (installs sudo if missing)
#   - Robust package installation: One-by-one with error recovery
#   - Comprehensive logging: All steps logged to install/install.log
#   - PHP 8.4 with all modern extensions
#   - MariaDB 11.4 (latest stable) with UTF8MB4 support
#   - Nginx 1.26.x with HTTP-FLV, HTTP/2, HTTP/3 support
#   - FFmpeg with low-latency streaming optimizations
#   - NVM + Node.js 20 LTS for Vue.js frontend builds
#   - Composer 2.x for Laravel components
#   - Laravel Eloquent ORM integration
#   - Vue.js 3 + Vite 5 admin panel
#   - Enhanced security configurations
#   - Automated setup and deployment
#   - Environment-based configuration (.env)
#
# Network Environment Support:
#   - WSL (Windows Subsystem for Linux): Full support for local development
#   - Docker/LXC containers: Container-aware configuration
#   - Local/LAN deployments: Supports private IP ranges and .local domains
#   - NAT/Router: Detects NAT and provides port forwarding guidance
#   - Public servers: Standard production deployment with domain DNS
#
# Supported Address Formats:
#   - Standard domains: stream.example.com, myserver.net
#   - Local domains: fos.local, mystream.local (requires hosts file)
#   - IP addresses: 192.168.1.100, 10.0.0.5, 172.16.0.10
#   - Loopback: localhost, 127.0.0.1
#
# Repository: https://github.com/CristianCasapu/FOS-Streaming-Reborn
# Date: 2025-11-26
# Version: 70.0.2
# Supported OS: Debian 10/11/12, Ubuntu 20.04/22.04/24.04 LTS (any minor version)
# Run as: Normal user OR root (script will adapt)
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
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# =============================================================================
# Dynamic Configuration - Detect from environment
# =============================================================================
PHP_VERSION="8.4"
MARIADB_VERSION="11.4"
NODE_VERSION="24"  # LTS version

# Detect current user (may be root during bootstrap)
ORIGINAL_USER="${SUDO_USER:-$(whoami)}"
FOS_USER=""
FOS_USER_HOME=""

# Detect project directory (where this script is located)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FOS_DIR="$(dirname "$SCRIPT_DIR")"  # Parent of install/ directory

# Installation log file (non-sensitive information only)
INSTALL_LOG="${SCRIPT_DIR}/install.log"

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
TOTAL_STEPS=23
declare -A INSTALL_STATE

# =============================================================================
# Logging Functions - Enhanced with File Logging
# =============================================================================

# Initialize log file
init_log() {
    # Create install directory if it doesn't exist
    mkdir -p "$(dirname "$INSTALL_LOG")" 2>/dev/null || true

    # Initialize log file with header
    cat > "$INSTALL_LOG" <<EOF
================================================================================
FOS-Streaming v70 Installation Log
Started: $(date '+%Y-%m-%d %H:%M:%S')
================================================================================

System Information:
- Hostname: $(hostname 2>/dev/null || echo "unknown")
- Kernel: $(uname -r 2>/dev/null || echo "unknown")
- Architecture: $(uname -m 2>/dev/null || echo "unknown")

================================================================================
Installation Steps:
================================================================================

EOF
    chmod 644 "$INSTALL_LOG" 2>/dev/null || true
}

# Log to file (sanitized - no sensitive info)
log_to_file() {
    local level="$1"
    local message="$2"

    # Sanitize message - remove potential passwords and sensitive data
    local sanitized_msg=$(echo "$message" | sed -E \
        -e 's/(password|passwd|pwd|secret|key|token)[=:][^ ]*/\1=***REDACTED***/gi' \
        -e 's/-p[^ ]+/-p***REDACTED***/g' \
        -e "s/'[^']{8,}'/'***REDACTED***'/g")

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $sanitized_msg" >> "$INSTALL_LOG" 2>/dev/null || true
}

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
    log_to_file "INFO" "$1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    log_to_file "WARN" "$1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    log_to_file "ERROR" "$1"
}

log_step() {
    echo -e "\n${BLUE}===${NC} $1 ${BLUE}===${NC}\n"
    log_to_file "STEP" "=== $1 ==="
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    log_to_file "SUCCESS" "$1"
}

log_progress() {
    echo -e "${CYAN}[STEP ${CURRENT_STEP}/${TOTAL_STEPS}]${NC} $1"
    log_to_file "PROGRESS" "Step ${CURRENT_STEP}/${TOTAL_STEPS}: $1"
}

log_cmd() {
    local cmd="$1"
    # Sanitize command before logging
    local sanitized_cmd=$(echo "$cmd" | sed -E \
        -e 's/(password|passwd|pwd|secret|key|token)[=:][^ ]*/\1=***REDACTED***/gi' \
        -e 's/-p[^ ]+/-p***REDACTED***/g')
    log_to_file "CMD" "Executing: $sanitized_cmd"
}

log_bootstrap() {
    echo -e "${MAGENTA}[BOOTSTRAP]${NC} $1"
    log_to_file "BOOTSTRAP" "$1"
}

# =============================================================================
# Bootstrap Functions - Run before anything else
# =============================================================================

# Check if a command exists
command_exists() {
    command -v "$1" &>/dev/null
}

# Install a package using available package manager (works as root or with sudo)
bootstrap_install_package() {
    local pkg="$1"
    local pkg_manager=""

    # Detect package manager
    if command_exists apt-get; then
        pkg_manager="apt-get"
    elif command_exists apt; then
        pkg_manager="apt"
    else
        log_error "No supported package manager found (apt/apt-get)"
        return 1
    fi

    log_bootstrap "Installing $pkg..."
    log_cmd "$pkg_manager install -y $pkg"

    if [ "$EUID" -eq 0 ]; then
        # Running as root
        DEBIAN_FRONTEND=noninteractive $pkg_manager install -y "$pkg" 2>&1 | tee -a "$INSTALL_LOG"
    else
        # Running as user with sudo
        DEBIAN_FRONTEND=noninteractive sudo $pkg_manager install -y "$pkg" 2>&1 | tee -a "$INSTALL_LOG"
    fi

    return ${PIPESTATUS[0]}
}

# Update package lists
bootstrap_update_packages() {
    local pkg_manager=""

    if command_exists apt-get; then
        pkg_manager="apt-get"
    elif command_exists apt; then
        pkg_manager="apt"
    else
        log_error "No supported package manager found"
        return 1
    fi

    log_bootstrap "Updating package lists..."
    log_cmd "$pkg_manager update"

    if [ "$EUID" -eq 0 ]; then
        $pkg_manager update -y 2>&1 | tee -a "$INSTALL_LOG"
    else
        sudo $pkg_manager update -y 2>&1 | tee -a "$INSTALL_LOG"
    fi

    return ${PIPESTATUS[0]}
}

# Bootstrap: Ensure essential tools are available
run_bootstrap() {
    log_step "Bootstrap Phase: Ensuring Essential Prerequisites"

    # Initialize logging
    init_log
    log_bootstrap "Starting bootstrap phase..."

    # -------------------------------------------------------------------------
    # Step 1: Install lsb-release if not available
    # -------------------------------------------------------------------------
    if ! command_exists lsb_release; then
        log_bootstrap "lsb-release not found, installing..."

        # First, try to update package lists
        bootstrap_update_packages || {
            log_warn "Failed to update package lists, trying to continue..."
        }

        # Try different package names
        if ! bootstrap_install_package "lsb-release"; then
            log_warn "lsb-release failed, trying lsb-core..."
            bootstrap_install_package "lsb-core" || {
                log_warn "Could not install lsb-release, will use /etc/os-release instead"
            }
        fi
    else
        log_bootstrap "lsb-release is available"
    fi

    # -------------------------------------------------------------------------
    # Step 2: Install sudo if not available
    # -------------------------------------------------------------------------
    if ! command_exists sudo; then
        log_bootstrap "sudo not found, installing..."

        if [ "$EUID" -ne 0 ]; then
            log_error "sudo is not installed and you are not root."
            log_error "Please run this script as root first to install sudo:"
            log_error "  su -c './install/install.sh'"
            exit 1
        fi

        bootstrap_update_packages || true
        bootstrap_install_package "sudo" || {
            log_error "Failed to install sudo. Cannot continue."
            exit 1
        }
        log_success "sudo installed successfully"
    else
        log_bootstrap "sudo is available"
    fi

    # -------------------------------------------------------------------------
    # Step 3: Determine the installation user
    # -------------------------------------------------------------------------
    if [ "$EUID" -eq 0 ]; then
        # Running as root - need to determine target user
        if [ -n "$SUDO_USER" ] && [ "$SUDO_USER" != "root" ]; then
            FOS_USER="$SUDO_USER"
        else
            # Check if there's a non-root user in the system
            local potential_user=$(getent passwd | awk -F: '$3 >= 1000 && $3 < 65534 && $7 !~ /nologin|false/ {print $1; exit}')
            if [ -n "$potential_user" ]; then
                log_warn "Running as root. Will set up for user: $potential_user"
                FOS_USER="$potential_user"
            else
                log_error "Running as root with no regular user detected."
                log_info "Please create a user first and run this script as that user."
                log_info "  adduser myuser"
                log_info "  su - myuser"
                log_info "  ./install/install.sh"
                exit 1
            fi
        fi
    else
        FOS_USER="$(whoami)"
    fi

    FOS_USER_HOME="$(eval echo ~$FOS_USER)"
    log_bootstrap "Installation user: $FOS_USER (home: $FOS_USER_HOME)"

    # -------------------------------------------------------------------------
    # Step 4: Set up sudoers for the installation user (EARLY - before anything else)
    # -------------------------------------------------------------------------
    log_bootstrap "Configuring sudo access for ${FOS_USER}..."

    local sudoers_file="/etc/sudoers.d/${FOS_USER}"
    local sudoers_content="${FOS_USER} ALL=(ALL) NOPASSWD: ALL"

    if [ "$EUID" -eq 0 ]; then
        # Running as root
        if [ ! -f "$sudoers_file" ] || ! grep -q "NOPASSWD: ALL" "$sudoers_file" 2>/dev/null; then
            echo "$sudoers_content" > "$sudoers_file"
            chmod 0440 "$sudoers_file"
            log_success "${FOS_USER} added to sudoers with NOPASSWD"
        else
            log_bootstrap "${FOS_USER} already has sudo NOPASSWD access"
        fi
    else
        # Running as user - check if we already have sudo access
        if sudo -n true 2>/dev/null; then
            # We have sudo, ensure the file exists
            if [ ! -f "$sudoers_file" ]; then
                echo "$sudoers_content" | sudo tee "$sudoers_file" > /dev/null
                sudo chmod 0440 "$sudoers_file"
                log_success "${FOS_USER} added to sudoers with NOPASSWD"
            else
                log_bootstrap "${FOS_USER} already has sudo configuration"
            fi
        else
            log_error "Cannot configure sudoers - no sudo access"
            log_error "Please run: su -c 'echo \"${FOS_USER} ALL=(ALL) NOPASSWD: ALL\" > /etc/sudoers.d/${FOS_USER}'"
            exit 1
        fi
    fi

    # -------------------------------------------------------------------------
    # Step 5: Install essential bootstrap packages
    # -------------------------------------------------------------------------
    log_bootstrap "Installing essential packages..."

    local essential_packages=(
        "curl"
        "wget"
        "gnupg"
        "ca-certificates"
        "apt-transport-https"
        "software-properties-common"
    )

    bootstrap_update_packages || true

    for pkg in "${essential_packages[@]}"; do
        if ! dpkg -l "$pkg" 2>/dev/null | grep -q "^ii"; then
            log_bootstrap "Installing $pkg..."
            bootstrap_install_package "$pkg" || {
                log_warn "Failed to install $pkg, continuing..."
            }
        else
            log_bootstrap "$pkg is already installed"
        fi
    done

    # -------------------------------------------------------------------------
    # Step 6: If running as root, switch to target user for remaining installation
    # -------------------------------------------------------------------------
    if [ "$EUID" -eq 0 ] && [ "$FOS_USER" != "root" ]; then
        log_bootstrap "Switching to user ${FOS_USER} for remaining installation..."

        # Re-execute the script as the target user
        cd "${FOS_DIR}"
        exec sudo -u "$FOS_USER" -H bash "${SCRIPT_DIR}/install.sh" "$@"
        exit 0
    fi

    log_success "Bootstrap phase completed successfully"
    log_to_file "INFO" "Bootstrap phase completed"
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
SAVED_REDIS_PASSWORD=${REDIS_PASSWORD}
SAVED_APP_KEY=${APP_KEY}
SAVED_PUBLIC_IP=${PUBLIC_IP}
SAVED_NETWORK_ENV=${NETWORK_ENV}
SAVED_LOCAL_IP=${LOCAL_IP}
SAVED_IS_LOCAL_DEPLOYMENT=${IS_LOCAL_DEPLOYMENT}
EOF
    chmod 600 "$STATE_FILE"
    log_to_file "STATE" "Saved installation state at step ${step}"
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
        REDIS_PASSWORD="${SAVED_REDIS_PASSWORD}"
        APP_KEY="${SAVED_APP_KEY}"
        PUBLIC_IP="${SAVED_PUBLIC_IP}"
        NETWORK_ENV="${SAVED_NETWORK_ENV}"
        LOCAL_IP="${SAVED_LOCAL_IP}"
        IS_LOCAL_DEPLOYMENT="${SAVED_IS_LOCAL_DEPLOYMENT}"

        return 0
    fi
    return 1
}

clear_state() {
    rm -f "$STATE_FILE" "$STATE_LOCK"
}

# =============================================================================
# Package Installation Functions - One by One with Error Recovery
# =============================================================================

# Install a single package with retry
install_package() {
    local pkg="$1"
    local max_retries="${2:-3}"
    local retry_count=0

    # Check if already installed
    if dpkg -l "$pkg" 2>/dev/null | grep -q "^ii"; then
        log_info "  ✓ $pkg (already installed)"
        return 0
    fi

    while [ $retry_count -lt $max_retries ]; do
        log_cmd "apt-get install -y $pkg"

        if sudo DEBIAN_FRONTEND=noninteractive apt-get install -y "$pkg" 2>&1 | tee -a "$INSTALL_LOG"; then
            log_info "  ✓ $pkg (installed)"
            return 0
        fi

        retry_count=$((retry_count + 1))
        if [ $retry_count -lt $max_retries ]; then
            log_warn "  ⚠ $pkg failed, retrying ($retry_count/$max_retries)..."
            sleep 2
        fi
    done

    log_error "  ✗ $pkg (failed after $max_retries attempts)"
    return 1
}

# Install multiple packages one by one
install_packages_individually() {
    local packages=("$@")
    local failed_packages=()
    local installed_count=0
    local total_count=${#packages[@]}

    log_info "Installing $total_count packages individually..."
    log_to_file "INFO" "Installing packages: ${packages[*]}"

    for pkg in "${packages[@]}"; do
        if install_package "$pkg"; then
            installed_count=$((installed_count + 1))
        else
            failed_packages+=("$pkg")
        fi
    done

    log_info "Installed $installed_count/$total_count packages"

    if [ ${#failed_packages[@]} -gt 0 ]; then
        log_warn "Failed to install: ${failed_packages[*]}"
        log_to_file "WARN" "Failed packages: ${failed_packages[*]}"
        return 1
    fi

    return 0
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
# Network Environment Detection Functions
# =============================================================================

# Detect if running in WSL (Windows Subsystem for Linux)
is_wsl() {
    if grep -qiE '(microsoft|wsl)' /proc/version 2>/dev/null; then
        return 0
    fi
    if [ -f /proc/sys/fs/binfmt_misc/WSLInterop ]; then
        return 0
    fi
    if [ -d /mnt/c/Windows ]; then
        return 0
    fi
    return 1
}

# Detect if running in a container (Docker, LXC, etc.)
is_container() {
    if [ -f /.dockerenv ]; then
        return 0
    fi
    if grep -qE '(docker|lxc|containerd|kubepods)' /proc/1/cgroup 2>/dev/null; then
        return 0
    fi
    return 1
}

# Check if an IP address is a local/private address
is_private_ip() {
    local ip="$1"
    # RFC 1918 private ranges: 10.x.x.x, 172.16-31.x.x, 192.168.x.x
    # Also: 127.x.x.x (loopback), 169.254.x.x (link-local)
    if [[ "$ip" =~ ^10\. ]] || \
       [[ "$ip" =~ ^172\.(1[6-9]|2[0-9]|3[0-1])\. ]] || \
       [[ "$ip" =~ ^192\.168\. ]] || \
       [[ "$ip" =~ ^127\. ]] || \
       [[ "$ip" =~ ^169\.254\. ]]; then
        return 0
    fi
    return 1
}

# Get the local/LAN IP address
get_local_ip() {
    # Try to get the primary local IP (prefer non-loopback)
    local ip=""

    # Method 1: ip route
    ip=$(ip route get 8.8.8.8 2>/dev/null | grep -oP 'src \K[0-9.]+' | head -1)
    if [ -n "$ip" ] && [ "$ip" != "127.0.0.1" ]; then
        echo "$ip"
        return 0
    fi

    # Method 2: hostname -I
    ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    if [ -n "$ip" ] && [ "$ip" != "127.0.0.1" ]; then
        echo "$ip"
        return 0
    fi

    # Method 3: ifconfig fallback
    ip=$(ifconfig 2>/dev/null | grep -oP 'inet \K[0-9.]+' | grep -v '127.0.0.1' | head -1)
    if [ -n "$ip" ]; then
        echo "$ip"
        return 0
    fi

    # Fallback to loopback
    echo "127.0.0.1"
}

# Get the default gateway (router) IP
get_gateway_ip() {
    local gateway=""
    gateway=$(ip route 2>/dev/null | grep default | awk '{print $3}' | head -1)
    if [ -n "$gateway" ]; then
        echo "$gateway"
        return 0
    fi
    echo ""
}

# Detect if behind NAT (local IP differs from public IP)
is_behind_nat() {
    local local_ip=$(get_local_ip)
    local public_ip=$(curl -s --connect-timeout 3 https://api.ipify.org 2>/dev/null || \
                      curl -s --connect-timeout 3 https://ifconfig.me 2>/dev/null || \
                      curl -s --connect-timeout 3 https://icanhazip.com 2>/dev/null)

    if [ -z "$public_ip" ]; then
        # Cannot determine public IP - assume behind NAT if local IP is private
        is_private_ip "$local_ip"
        return $?
    fi

    if [ "$local_ip" != "$public_ip" ]; then
        return 0  # Behind NAT
    fi
    return 1  # Direct public IP
}

# Comprehensive network environment detection
# Returns: wsl, container, local, nat, public
detect_network_environment() {
    # Check for WSL first
    if is_wsl; then
        echo "wsl"
        return 0
    fi

    # Check for container
    if is_container; then
        echo "container"
        return 0
    fi

    local local_ip=$(get_local_ip)

    # Check if only loopback is available (no network)
    if [ "$local_ip" = "127.0.0.1" ]; then
        echo "local"
        return 0
    fi

    # Check if we have a private IP (behind NAT/router)
    if is_private_ip "$local_ip"; then
        if is_behind_nat; then
            echo "nat"
        else
            echo "local"
        fi
        return 0
    fi

    # Public IP directly assigned
    echo "public"
}

# Display network environment info to user
display_network_info() {
    local env_type=$(detect_network_environment)
    local local_ip=$(get_local_ip)
    local gateway=$(get_gateway_ip)

    echo ""
    log_step "Network Environment Detection"

    case "$env_type" in
        wsl)
            log_info "Environment: Windows Subsystem for Linux (WSL)"
            log_info "Local IP: ${local_ip}"
            echo ""
            log_warn "WSL DEPLOYMENT DETECTED"
            log_info "For WSL/local development, you can use:"
            log_info "  - localhost or 127.0.0.1"
            log_info "  - Your local IP: ${local_ip}"
            log_info "  - A .local domain (e.g., fos.local)"
            echo ""
            log_warn "WSL ports are accessible from Windows at localhost:PORT"
            log_info "For LAN access, you may need to configure Windows firewall"
            ;;
        container)
            log_info "Environment: Container (Docker/LXC)"
            log_info "Local IP: ${local_ip}"
            echo ""
            log_warn "CONTAINER DEPLOYMENT DETECTED"
            log_info "Make sure to map the required ports when running the container"
            log_info "Port mapping example: -p 8000:8000 -p 8080:8080 -p 1935:1935"
            ;;
        local)
            log_info "Environment: Local Network Only"
            log_info "Local IP: ${local_ip}"
            echo ""
            log_info "This server appears to have local network access only."
            log_info "You can use local domains or IP addresses."
            ;;
        nat)
            log_info "Environment: Behind NAT/Router"
            log_info "Local IP: ${local_ip}"
            [ -n "$gateway" ] && log_info "Gateway: ${gateway}"

            # Try to get public IP
            local public_ip=$(curl -s --connect-timeout 3 https://api.ipify.org 2>/dev/null)
            [ -n "$public_ip" ] && log_info "Public IP: ${public_ip}"

            echo ""
            log_warn "NAT/ROUTER DETECTED"
            log_info "Your server is behind a router. For external access:"
            log_info "  1. Configure port forwarding on your router"
            log_info "  2. Forward these ports to ${local_ip}:"
            log_info "     - Web Port (HTTP): 8000 (or your chosen port)"
            log_info "     - Streaming Port: 8080"
            log_info "     - RTMP Port: 1935"
            echo ""
            log_info "For local/LAN access only, use your local IP: ${local_ip}"
            ;;
        public)
            log_info "Environment: Public Server"
            log_info "IP: ${local_ip}"
            echo ""
            log_info "Your server has a public IP address."
            log_info "This is ideal for production deployments."
            ;;
    esac

    echo ""

    # Return environment type for further use
    NETWORK_ENV="$env_type"
    LOCAL_IP="$local_ip"
    GATEWAY_IP="$gateway"
}

# Validate domain/IP for the detected environment
# Allows: standard domains, .local domains, localhost, IPv4 addresses
validate_domain_or_ip() {
    local input="$1"

    # Standard domain (subdomain.domain.tld)
    if [[ "$input" =~ ^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$ ]]; then
        return 0
    fi

    # .local domain (fos.local, stream.local, etc.)
    if [[ "$input" =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.local$ ]]; then
        return 0
    fi

    # localhost
    if [ "$input" = "localhost" ]; then
        return 0
    fi

    # IPv4 address
    if [[ "$input" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
        # Validate each octet is 0-255
        local IFS='.'
        read -ra octets <<< "$input"
        for octet in "${octets[@]}"; do
            if [ "$octet" -gt 255 ] 2>/dev/null; then
                return 1
            fi
        done
        return 0
    fi

    return 1
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
    log_cmd "$*"

    if ! "$@" 2>&1 | tee -a "$INSTALL_LOG"; then
        if read_confirm "Command failed. Retry?"; then
            if ! "$@" 2>&1 | tee -a "$INSTALL_LOG"; then
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

# =============================================================================
# OS Detection - Enhanced for all Debian/Ubuntu versions
# =============================================================================
OS_TYPE=""
OS_VERSION=""
OS_VERSION_MAJOR=""
OS_CODENAME=""

detect_os() {
    log_step "Detecting Operating System"

    # Primary: Use /etc/os-release (most reliable)
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_TYPE="$ID"
        OS_VERSION="$VERSION_ID"
        OS_CODENAME="$VERSION_CODENAME"
    # Fallback: Use lsb_release if available
    elif command_exists lsb_release; then
        OS_TYPE=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
        OS_VERSION=$(lsb_release -sr)
        OS_CODENAME=$(lsb_release -sc)
    # Last resort: Parse /etc/debian_version
    elif [ -f /etc/debian_version ]; then
        OS_TYPE="debian"
        OS_VERSION=$(cat /etc/debian_version)
        # Map Debian version to codename
        case "$OS_VERSION" in
            10*) OS_CODENAME="buster" ;;
            11*) OS_CODENAME="bullseye" ;;
            12*) OS_CODENAME="bookworm" ;;
            13*) OS_CODENAME="trixie" ;;
            *) OS_CODENAME="unknown" ;;
        esac
    else
        OS_TYPE="unknown"
        OS_VERSION="unknown"
        OS_CODENAME="unknown"
    fi

    # Extract major version number
    OS_VERSION_MAJOR=$(echo "$OS_VERSION" | cut -d. -f1 | tr -dc '0-9')

    log_info "Detected OS: ${OS_TYPE} ${OS_VERSION} (${OS_CODENAME})"
    log_info "OS Major Version: ${OS_VERSION_MAJOR}"
    log_to_file "INFO" "OS Detection: ${OS_TYPE} ${OS_VERSION} (${OS_CODENAME}) Major: ${OS_VERSION_MAJOR}"
}

# =============================================================================
# Repository Setup - Based on OS Version
# =============================================================================

setup_repositories() {
    log_step "Setting Up Package Repositories"

    case "${OS_TYPE}" in
        debian)
            setup_debian_repositories
            ;;
        ubuntu)
            setup_ubuntu_repositories
            ;;
        *)
            log_warn "Unknown OS type: ${OS_TYPE}. Attempting generic Debian-based setup..."
            setup_debian_repositories
            ;;
    esac
}

setup_debian_repositories() {
    log_info "Setting up Debian repositories for ${OS_CODENAME}..."

    # Ensure /etc/apt/sources.list.d exists
    sudo mkdir -p /etc/apt/sources.list.d

    # Add contrib and non-free if not present (needed for some packages)
    if [ -f /etc/apt/sources.list ]; then
        if ! grep -q "contrib" /etc/apt/sources.list 2>/dev/null; then
            log_info "Adding contrib and non-free repositories..."
            sudo sed -i 's/main$/main contrib non-free non-free-firmware/g' /etc/apt/sources.list 2>/dev/null || true
        fi
    fi

    # PHP Repository (Sury)
    log_info "Adding PHP repository (Sury)..."
    if [ ! -f /etc/apt/sources.list.d/php-sury.list ]; then
        sudo curl -sSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php-sury.gpg 2>/dev/null || {
            # Alternative method using apt-key (deprecated but fallback)
            curl -sSL https://packages.sury.org/php/apt.gpg | sudo apt-key add - 2>/dev/null || true
        }
        echo "deb https://packages.sury.org/php/ ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/php-sury.list
    fi

    # MariaDB Repository
    log_info "Adding MariaDB repository..."
    if [ ! -f /etc/apt/sources.list.d/mariadb.list ]; then
        sudo curl -o /etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc 'https://mariadb.org/mariadb_release_signing_key.asc' 2>/dev/null || true
        echo "deb [arch=amd64,arm64] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/debian ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
    fi

    # Update package lists
    log_info "Updating package lists..."
    sudo apt-get update -y 2>&1 | tee -a "$INSTALL_LOG"
}

setup_ubuntu_repositories() {
    log_info "Setting up Ubuntu repositories for ${OS_CODENAME}..."

    # Ensure universe and multiverse are enabled
    log_info "Enabling universe and multiverse repositories..."
    sudo add-apt-repository -y universe 2>/dev/null || true
    sudo add-apt-repository -y multiverse 2>/dev/null || true

    # PHP Repository (Ondrej PPA)
    log_info "Adding PHP repository (Ondrej PPA)..."
    if ! ls /etc/apt/sources.list.d/*ondrej* 2>/dev/null | grep -q php; then
        sudo add-apt-repository -y ppa:ondrej/php 2>&1 | tee -a "$INSTALL_LOG" || {
            log_warn "PPA add failed, trying manual method..."
            # Manual fallback for older systems
            sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C 2>/dev/null || true
            echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/ondrej-php.list
        }
    fi

    # MariaDB Repository
    log_info "Adding MariaDB repository..."
    if [ ! -f /etc/apt/sources.list.d/mariadb.list ]; then
        sudo curl -o /etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc 'https://mariadb.org/mariadb_release_signing_key.asc' 2>/dev/null || true
        echo "deb [arch=amd64,arm64] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/ubuntu ${OS_CODENAME} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
    fi

    # Update package lists
    log_info "Updating package lists..."
    sudo apt-get update -y 2>&1 | tee -a "$INSTALL_LOG"
}

# =============================================================================
# NVM and Node.js Installation - Enhanced
# =============================================================================

install_nvm_nodejs() {
    log_step "Installing NVM and Node.js ${NODE_VERSION}"

    export NVM_DIR="${FOS_USER_HOME}/.nvm"

    # Install NVM
    log_info "Installing NVM (Node Version Manager)..."
    log_cmd "curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash"

    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash 2>&1 | tee -a "$INSTALL_LOG" || {
        log_error "Failed to install NVM"
        return 1
    }

    # Load NVM
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

    # Add NVM to shell profile if not already present
    local shell_profile=""
    if [ -f "${FOS_USER_HOME}/.bashrc" ]; then
        shell_profile="${FOS_USER_HOME}/.bashrc"
    elif [ -f "${FOS_USER_HOME}/.bash_profile" ]; then
        shell_profile="${FOS_USER_HOME}/.bash_profile"
    elif [ -f "${FOS_USER_HOME}/.profile" ]; then
        shell_profile="${FOS_USER_HOME}/.profile"
    fi

    if [ -n "$shell_profile" ]; then
        if ! grep -q "NVM_DIR" "$shell_profile" 2>/dev/null; then
            log_info "Adding NVM to ${shell_profile}..."
            cat >> "$shell_profile" <<'NVMRC'

# NVM (Node Version Manager)
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"
NVMRC
        fi
    fi

    # Install Node.js
    log_info "Installing Node.js ${NODE_VERSION} LTS..."
    log_cmd "nvm install ${NODE_VERSION}"

    nvm install ${NODE_VERSION} 2>&1 | tee -a "$INSTALL_LOG" || {
        log_error "Failed to install Node.js"
        return 1
    }

    nvm use ${NODE_VERSION}
    nvm alias default ${NODE_VERSION}

    # Verify installation
    if ! node --version; then
        log_error "Node.js installation verification failed"
        return 1
    fi
    if ! npm --version; then
        log_error "NPM installation verification failed"
        return 1
    fi

    log_success "Node.js $(node --version) installed successfully"
    log_success "NPM $(npm --version) installed successfully"
    log_to_file "SUCCESS" "Node.js $(node --version), NPM $(npm --version) installed"

    return 0
}

# =============================================================================
# Composer Installation - Enhanced
# =============================================================================

install_composer() {
    log_step "Installing Composer"

    # Check if already installed
    if command_exists composer; then
        local current_version=$(composer --version 2>/dev/null | head -n1 | awk '{print $3}')
        log_info "Composer already installed: ${current_version}"
        return 0
    fi

    log_info "Downloading and installing Composer..."

    cd /tmp

    # Download installer
    log_cmd "curl -sS https://getcomposer.org/installer -o composer-setup.php"
    curl -sS https://getcomposer.org/installer -o composer-setup.php 2>&1 | tee -a "$INSTALL_LOG" || {
        log_error "Failed to download Composer installer"
        return 1
    }

    # Verify installer (optional but recommended)
    local expected_sig=$(curl -sS https://composer.github.io/installer.sig 2>/dev/null)
    local actual_sig=$(php -r "echo hash_file('sha384', 'composer-setup.php');" 2>/dev/null)

    if [ "$expected_sig" != "$actual_sig" ]; then
        log_warn "Composer installer signature mismatch, proceeding anyway..."
    fi

    # Install Composer
    log_cmd "php composer-setup.php --install-dir=/usr/local/bin --filename=composer"
    sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>&1 | tee -a "$INSTALL_LOG" || {
        log_error "Failed to install Composer"
        rm -f composer-setup.php
        return 1
    }

    rm -f composer-setup.php

    # Verify installation
    if ! composer --version; then
        log_error "Composer installation verification failed"
        return 1
    fi

    # Add to PATH if not already (should be in /usr/local/bin which is usually in PATH)
    if [ -n "$shell_profile" ] && [ -f "$shell_profile" ]; then
        if ! grep -q "/usr/local/bin" "$shell_profile" 2>/dev/null; then
            echo 'export PATH="/usr/local/bin:$PATH"' >> "$shell_profile"
        fi
    fi

    log_success "Composer installed successfully: $(composer --version 2>/dev/null | head -n1)"
    log_to_file "SUCCESS" "Composer installed: $(composer --version 2>/dev/null | head -n1)"

    return 0
}

# ============================================================================
# Initial Checks - Run Bootstrap First
# ============================================================================

# Run bootstrap phase
run_bootstrap

# After bootstrap, verify we're running as the correct user
if [ "$EUID" -eq 0 ]; then
    log_error "After bootstrap, script should be running as ${FOS_USER}, not root"
    exit 1
fi

# Verify FOS_USER is set correctly
if [ -z "$FOS_USER" ]; then
    FOS_USER="$(whoami)"
    FOS_USER_HOME="$(eval echo ~$FOS_USER)"
fi

# Verify sudo access
log_info "Verifying sudo access for ${FOS_USER}..."
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

# Validate we're in a valid project directory
if [ ! -f "${FOS_DIR}/config.php" ] && [ ! -f "${FOS_DIR}/composer.json" ]; then
    log_error "This script must be run from within the FOS-Streaming project directory."
    log_error "Expected to find config.php or composer.json in: ${FOS_DIR}"
    exit 1
fi

# =============================================================================
# OS Detection
# =============================================================================
detect_os

# Validate supported OS
case "${OS_TYPE}" in
    debian)
        case "$OS_VERSION_MAJOR" in
            10|11|12|13)
                log_info "Debian ${OS_VERSION} detected - supported"
                ;;
            *)
                log_warn "This script is optimized for Debian 10-13. Detected: Debian ${OS_VERSION}"
                if ! read_confirm "Continue anyway? (may require manual adjustments)"; then
                    exit 1
                fi
                ;;
        esac
        ;;
    ubuntu)
        case "$OS_VERSION_MAJOR" in
            20|22|24)
                log_info "Ubuntu ${OS_VERSION} detected - fully supported"
                ;;
            *)
                log_warn "This script is optimized for Ubuntu 20.x/22.x/24.x. Detected: Ubuntu ${OS_VERSION}"
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
log_info "Installation log: ${INSTALL_LOG}"
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
    [ -z "$WEB_PORT" ] && { log_error "Unable to find available Cloudflare SSL port for web service"; exit 1; }

    # Select Streaming Port (different from web)
    STREAM_PORT=""
    for port in "${CF_SSL_PORTS[@]}"; do
        if [ "$port" != "$WEB_PORT" ] && port_available "$port"; then
            STREAM_PORT=$port
            log_info "Selected streaming port: ${STREAM_PORT} (HTTPS, Cloudflare compatible)"
            break
        fi
    done
    [ -z "$STREAM_PORT" ] && { log_error "Unable to find available Cloudflare SSL port for streaming service"; exit 1; }

    # Select RTMP Port (from Cloudflare SSL ports, different from web and streaming)
    RTMP_PORT=""
    for port in "${CF_SSL_PORTS[@]}"; do
        if [ "$port" != "$WEB_PORT" ] && [ "$port" != "$STREAM_PORT" ] && port_available "$port"; then
            RTMP_PORT=$port
            log_info "Selected RTMP port: ${RTMP_PORT} (SSL, Cloudflare compatible)"
            break
        fi
    done
    [ -z "$RTMP_PORT" ] && { log_error "Unable to find available Cloudflare SSL port for RTMP service"; exit 1; }

    log_info "Port allocation complete (all Cloudflare SSL compatible):"
    log_info "  Web (HTTPS):       ${WEB_PORT}"
    log_info "  Streaming (HTTPS): ${STREAM_PORT}"
    log_info "  RTMP (SSL):        ${RTMP_PORT}"

    log_to_file "INFO" "Ports selected: Web=${WEB_PORT}, Stream=${STREAM_PORT}, RTMP=${RTMP_PORT}"
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

    # -------------------------------------------------------------------------
    # Network Environment Detection
    # -------------------------------------------------------------------------
    # Detect and display network environment to help user make informed decisions
    display_network_info

    # -------------------------------------------------------------------------
    # Domain/Address Configuration
    # -------------------------------------------------------------------------
    log_step "Domain/Address Configuration"

    # Provide guidance based on detected environment
    echo ""
    case "$NETWORK_ENV" in
        wsl|container|local)
            log_info "For local/development deployments, you can use:"
            log_info "  - A standard domain (e.g., stream.example.com)"
            log_info "  - A .local domain (e.g., fos.local)"
            log_info "  - localhost"
            log_info "  - An IP address (e.g., ${LOCAL_IP})"
            echo ""
            log_warn "Note: .local domains require hosts file configuration"
            log_info "      Add '${LOCAL_IP} your-domain.local' to /etc/hosts"
            ;;
        nat)
            log_info "For deployments behind NAT/router:"
            log_info "  - Use a standard domain for public access (requires DNS + port forwarding)"
            log_info "  - Use your local IP (${LOCAL_IP}) for LAN-only access"
            log_info "  - Use a .local domain for development"
            echo ""
            log_warn "For public access: Configure port forwarding on your router"
            log_info "and point your domain's DNS to your public IP"
            ;;
        public)
            log_info "For production deployments:"
            log_info "  - Use a fully qualified domain name (e.g., stream.example.com)"
            log_info "  - Configure your DNS to point to this server (${LOCAL_IP})"
            log_info "  - Recommended: Use Cloudflare for SSL/TLS and DDoS protection"
            ;;
    esac
    echo ""

    # Only prompt if not already set from resume
    if [ -z "$DOMAIN_NAME" ]; then
        while true; do
            echo ""
            read -p "Enter your domain name or IP address: " DOMAIN_NAME

            # Validate input
            if [ -z "$DOMAIN_NAME" ]; then
                log_error "This field is required. Please enter a value."
                continue
            fi

            if ! validate_domain_or_ip "$DOMAIN_NAME"; then
                log_error "Invalid format. Accepted formats:"
                log_error "  - Domain: stream.example.com, example.com"
                log_error "  - Local domain: fos.local, mystream.local"
                log_error "  - localhost"
                log_error "  - IP address: 192.168.1.100, 10.0.0.1, etc."
                DOMAIN_NAME=""
                continue
            fi

            log_info "Domain/Address set to: ${DOMAIN_NAME}"

            # Determine appropriate IP for this configuration
            if [ "$DOMAIN_NAME" = "localhost" ] || [[ "$DOMAIN_NAME" =~ ^127\. ]]; then
                PUBLIC_IP="127.0.0.1"
                log_info "Using loopback address"
                IS_LOCAL_DEPLOYMENT=1
            elif [[ "$DOMAIN_NAME" =~ \.local$ ]]; then
                PUBLIC_IP="$LOCAL_IP"
                IS_LOCAL_DEPLOYMENT=1
                log_info "Using local IP: ${PUBLIC_IP}"
                log_warn "Remember to add '${PUBLIC_IP} ${DOMAIN_NAME}' to your hosts file"
            elif [[ "$DOMAIN_NAME" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
                # Using an IP address directly
                PUBLIC_IP="$DOMAIN_NAME"
                IS_LOCAL_DEPLOYMENT=$(is_private_ip "$DOMAIN_NAME" && echo 1 || echo 0)
                log_info "Using IP address directly"
            else
                # Standard domain - try to get public IP
                PUBLIC_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || \
                           curl -s --connect-timeout 3 https://ifconfig.me 2>/dev/null || \
                           echo "$LOCAL_IP")
                IS_LOCAL_DEPLOYMENT=0

                if [ "$NETWORK_ENV" = "nat" ]; then
                    log_info "Public IP (via NAT): ${PUBLIC_IP}"
                    log_warn "Make sure your domain ${DOMAIN_NAME} points to ${PUBLIC_IP}"
                    log_warn "AND configure port forwarding to ${LOCAL_IP} on your router"
                elif [ "$NETWORK_ENV" = "public" ]; then
                    log_info "Server IP: ${PUBLIC_IP}"
                    log_warn "Make sure your domain ${DOMAIN_NAME} points to this IP"
                else
                    log_info "Detected IP: ${PUBLIC_IP}"
                fi
            fi

            echo ""
            if read_confirm "Is this configuration correct?"; then
                break
            fi
            # Reset to prompt again
            DOMAIN_NAME=""
        done
    else
        log_info "Using saved domain: ${DOMAIN_NAME}"

        # Determine IP for saved domain
        if [ -z "$PUBLIC_IP" ]; then
            if [ "$DOMAIN_NAME" = "localhost" ] || [[ "$DOMAIN_NAME" =~ ^127\. ]]; then
                PUBLIC_IP="127.0.0.1"
                IS_LOCAL_DEPLOYMENT=1
            elif [[ "$DOMAIN_NAME" =~ \.local$ ]]; then
                PUBLIC_IP="$LOCAL_IP"
                IS_LOCAL_DEPLOYMENT=1
            elif [[ "$DOMAIN_NAME" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
                PUBLIC_IP="$DOMAIN_NAME"
                IS_LOCAL_DEPLOYMENT=$(is_private_ip "$DOMAIN_NAME" && echo 1 || echo 0)
            else
                PUBLIC_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || echo "$LOCAL_IP")
                IS_LOCAL_DEPLOYMENT=0
            fi
        fi
        log_info "Server IP: ${PUBLIC_IP}"
    fi

    # Export for use by other parts of installer
    export NETWORK_ENV LOCAL_IP PUBLIC_IP IS_LOCAL_DEPLOYMENT

    # Save state after successful configuration
    save_state 0
else
    log_info "Skipping Step 0 (already completed)"
fi

# ============================================================================
# STEP 1: Setup Repositories
# ============================================================================
CURRENT_STEP=1

if [ $RESUME_STEP -le 1 ]; then
    log_step "Step 1: Setting Up Package Repositories"
    log_progress "Configuring package repositories for ${OS_TYPE^} ${OS_VERSION}"

    setup_repositories

    save_state 1
else
    log_info "Skipping Step 1 (already completed)"
fi

# ============================================================================
# STEP 2: System Update
# ============================================================================
CURRENT_STEP=2

if [ $RESUME_STEP -le 2 ]; then
    log_step "Step 2: Updating System"
    log_progress "Updating system packages"

    log_cmd "apt-get update -y"
    sudo apt-get update -y 2>&1 | tee -a "$INSTALL_LOG" || handle_error 2 "apt-get update failed"

    log_cmd "apt-get upgrade -y"
    sudo apt-get upgrade -y 2>&1 | tee -a "$INSTALL_LOG" || handle_error 2 "apt-get upgrade failed"

    log_cmd "apt-get dist-upgrade -y"
    sudo apt-get dist-upgrade -y 2>&1 | tee -a "$INSTALL_LOG" || log_warn "dist-upgrade had issues, continuing..."

    sudo apt-get autoremove -y 2>&1 | tee -a "$INSTALL_LOG" || true

    save_state 2
else
    log_info "Skipping Step 2 (already completed)"
fi

# ============================================================================
# STEP 3: Install Build Dependencies (One by One)
# ============================================================================
CURRENT_STEP=3

if [ $RESUME_STEP -le 3 ]; then
    log_step "Step 3: Installing Build Dependencies"
    log_progress "Installing build tools and utilities (one by one)"

    BUILD_DEPS=(
        "build-essential"
        "libssl-dev"
        "libpcre3"
        "libpcre3-dev"
        "zlib1g-dev"
        "curl"
        "nano"
        "wget"
        "zip"
        "unzip"
        "git"
        "lsof"
        "iftop"
        "htop"
        "vim"
        "ca-certificates"
        "apt-transport-https"
        "gnupg2"
        "software-properties-common"
        "dirmngr"
        "imagemagick"
        "webp"
        "icoutils"
        "openssl"
    )

    install_packages_individually "${BUILD_DEPS[@]}" || {
        log_warn "Some build dependencies failed to install, continuing..."
    }

    save_state 3
else
    log_info "Skipping Step 3 (already completed)"
fi

# ============================================================================
# STEP 4: Install Library Dependencies (One by One)
# ============================================================================
CURRENT_STEP=4

if [ $RESUME_STEP -le 4 ]; then
    log_step "Step 4: Installing Library Dependencies"
    log_progress "Installing development libraries (one by one)"

    LIB_DEPS=(
        "libxml2-dev"
        "libbz2-dev"
        "libcurl4-openssl-dev"
        "libxslt1-dev"
        "libpq-dev"
        "libsqlite3-dev"
        "libgd-dev"
        "libgeoip-dev"
        "libjpeg-dev"
        "libpng-dev"
        "libfreetype6-dev"
        "libwebp-dev"
        "libxpm-dev"
        "libtidy-dev"
        "libzip-dev"
        "libonig-dev"
        "libreadline-dev"
        "libedit-dev"
        "libsodium-dev"
    )

    install_packages_individually "${LIB_DEPS[@]}" || {
        log_warn "Some library dependencies failed to install, continuing..."
    }

    sudo apt-get autoremove -y || true

    save_state 4
else
    log_info "Skipping Step 4 (already completed)"
fi

# ============================================================================
# STEP 5: Setup PHP 8.4
# ============================================================================
CURRENT_STEP=5

if [ $RESUME_STEP -le 5 ]; then
    log_step "Step 5: Setting up PHP ${PHP_VERSION}"
    log_progress "Installing PHP ${PHP_VERSION} packages (one by one)"

    PHP_PACKAGES=(
        "php${PHP_VERSION}"
        "php${PHP_VERSION}-cli"
        "php${PHP_VERSION}-fpm"
        "php${PHP_VERSION}-common"
        "php${PHP_VERSION}-mysql"
        "php${PHP_VERSION}-curl"
        "php${PHP_VERSION}-gd"
        "php${PHP_VERSION}-mbstring"
        "php${PHP_VERSION}-xml"
        "php${PHP_VERSION}-zip"
        "php${PHP_VERSION}-bcmath"
        "php${PHP_VERSION}-intl"
        "php${PHP_VERSION}-opcache"
        "php${PHP_VERSION}-readline"
        "php${PHP_VERSION}-bz2"
        "php${PHP_VERSION}-soap"
        "php${PHP_VERSION}-xsl"
        "php${PHP_VERSION}-redis"
        "php${PHP_VERSION}-imagick"
    )

    install_packages_individually "${PHP_PACKAGES[@]}" || {
        handle_error 5 "Critical PHP packages failed to install"
    }

    save_state 5
else
    log_info "Skipping Step 5 (already completed)"
fi

# ============================================================================
# STEP 6: Install Composer
# ============================================================================
CURRENT_STEP=6

if [ $RESUME_STEP -le 6 ]; then
    log_step "Step 6: Installing Composer"
    log_progress "Installing Composer package manager"

    install_composer || handle_error 6 "Composer installation failed"

    save_state 6
else
    log_info "Skipping Step 6 (already completed)"
fi

# ============================================================================
# STEP 7: Install NVM and Node.js
# ============================================================================
CURRENT_STEP=7

if [ $RESUME_STEP -le 7 ]; then
    log_step "Step 7: Installing NVM and Node.js ${NODE_VERSION}"
    log_progress "Installing Node.js via NVM"

    install_nvm_nodejs || handle_error 7 "NVM/Node.js installation failed"

    save_state 7
else
    log_info "Skipping Step 7 (already completed)"
    # Still need to load NVM for subsequent steps
    export NVM_DIR="${FOS_USER_HOME}/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
fi

# ============================================================================
# STEP 8: Configure PHP 8.4
# ============================================================================
CURRENT_STEP=8

if [ $RESUME_STEP -le 8 ]; then
    log_step "Step 8: Configuring PHP ${PHP_VERSION}"
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

    save_state 8
else
    log_info "Skipping Step 8 (already completed)"
fi

# ============================================================================
# STEP 9: Configure System Users and Permissions
# ============================================================================
CURRENT_STEP=9

if [ $RESUME_STEP -le 9 ]; then
    log_step "Step 9: Configuring System Users and Permissions"
    log_progress "Setting up user permissions"

    # Create nginx user for system services (if needed for compatibility)
    if ! id "nginx" &>/dev/null; then
        sudo useradd -r -s /sbin/nologin nginx
        log_info "Created nginx system user"
    fi

    # Sudoers already configured in bootstrap, verify it's still there
    if [ ! -f "/etc/sudoers.d/${FOS_USER}" ]; then
        echo "${FOS_USER} ALL=(ALL) NOPASSWD: ALL" | sudo tee /etc/sudoers.d/${FOS_USER} > /dev/null
        sudo chmod 0440 /etc/sudoers.d/${FOS_USER}
        log_info "${FOS_USER} added to sudoers with NOPASSWD"
    else
        log_info "${FOS_USER} already has sudo configuration"
    fi

    # Restart PHP-FPM
    sudo systemctl restart php${PHP_VERSION}-fpm || handle_error 9 "Failed to restart PHP-FPM"
    sudo systemctl enable php${PHP_VERSION}-fpm

    save_state 9
else
    log_info "Skipping Step 9 (already completed)"
fi

# ============================================================================
# STEP 10: Install MariaDB
# ============================================================================
CURRENT_STEP=10

if [ $RESUME_STEP -le 10 ]; then
    log_step "Step 10: Installing MariaDB ${MARIADB_VERSION}"
    log_progress "Installing and configuring MariaDB"

    # Install MariaDB packages individually
    MARIADB_PACKAGES=(
        "mariadb-server"
        "mariadb-client"
    )

    install_packages_individually "${MARIADB_PACKAGES[@]}" || handle_error 10 "Failed to install MariaDB"

    # Add mariadb to PATH for root and current user
    log_info "Adding MariaDB to PATH..."
    MARIADB_BIN_PATH="/usr/bin"

    # Ensure mariadb is accessible (create symlinks if needed)
    if [ -x "/usr/bin/mariadb" ]; then
        log_info "MariaDB client found at /usr/bin/mariadb"
    elif [ -x "/usr/local/bin/mariadb" ]; then
        MARIADB_BIN_PATH="/usr/local/bin"
        log_info "MariaDB client found at /usr/local/bin/mariadb"
    fi

    # Add to current user's profile if not already present
    if [ -f "${FOS_USER_HOME}/.bashrc" ]; then
        if ! grep -q "MARIADB_BIN" "${FOS_USER_HOME}/.bashrc" 2>/dev/null; then
            cat >> "${FOS_USER_HOME}/.bashrc" <<'MARIADB_PATH_EOF'

# MariaDB PATH
export PATH="/usr/bin:$PATH"
alias mysql='mariadb'
alias mysqldump='mariadb-dump'
alias mysqlcheck='mariadb-check'
alias mysqladmin='mariadb-admin'
MARIADB_PATH_EOF
            log_info "Added MariaDB aliases to ${FOS_USER_HOME}/.bashrc"
        fi
    fi

    # Add to root's profile for sudo access
    if [ -f "/root/.bashrc" ]; then
        if ! sudo grep -q "MARIADB_BIN" /root/.bashrc 2>/dev/null; then
            sudo tee -a /root/.bashrc > /dev/null <<'MARIADB_ROOT_PATH_EOF'

# MariaDB PATH
export PATH="/usr/bin:$PATH"
alias mysql='mariadb'
alias mysqldump='mariadb-dump'
alias mysqlcheck='mariadb-check'
alias mysqladmin='mariadb-admin'
MARIADB_ROOT_PATH_EOF
            log_info "Added MariaDB aliases to /root/.bashrc"
        fi
    fi

    # Generate strong password for FOS application user (only if not already set from resume)
    if [ -z "$SQL_PASSWD" ]; then
        log_info "Generating MariaDB password for FOS application user..."
        SQL_PASSWD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    else
        log_info "Using saved MariaDB password from previous run"
    fi
    # Save password for reference (used by fos application user, not root)
    echo "$SQL_PASSWD" | sudo tee /root/MARIADB_FOS_PASSWORD > /dev/null
    sudo chmod 600 /root/MARIADB_FOS_PASSWORD

    # Start MariaDB
    log_info "Starting MariaDB service..."
    sudo systemctl stop mariadb 2>/dev/null || true
    sudo systemctl start mariadb || handle_error 10 "Failed to start MariaDB"

    # Secure MariaDB installation using unix_socket authentication for root
    # This allows passwordless access via sudo (more secure than password auth)
    log_info "Securing MariaDB installation..."

    # Configure root to use unix_socket authentication (passwordless via sudo)
    sudo mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket;" 2>/dev/null || true

    # Remove anonymous users
    log_info "Removing anonymous users..."
    sudo mariadb -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true

    # Remove remote root login
    log_info "Disabling remote root login..."
    sudo mariadb -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" 2>/dev/null || true

    # Remove test database
    log_info "Removing test database..."
    sudo mariadb -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
    sudo mariadb -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" 2>/dev/null || true

    # Flush privileges
    sudo mariadb -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Create FOS database and application user
    log_info "Creating FOS database..."
    sudo mariadb -e "CREATE DATABASE IF NOT EXISTS fos_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || handle_error 10 "Failed to create database"

    log_info "Creating FOS application user..."
    # Create user with password authentication (for application connections)
    sudo mariadb -e "CREATE USER IF NOT EXISTS 'fos'@'localhost' IDENTIFIED BY '${SQL_PASSWD}';"
    sudo mariadb -e "GRANT ALL PRIVILEGES ON fos_streaming.* TO 'fos'@'localhost';"
    sudo mariadb -e "FLUSH PRIVILEGES;"

    log_success "FOS database and user created successfully"

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

# Security
local_infile = 0
bind-address = 127.0.0.1

# Performance
max_allowed_packet = 64M
tmp_table_size = 64M
max_heap_table_size = 64M

[client]
# Default to utf8mb4
default-character-set = utf8mb4

[mariadb]
# MariaDB specific settings
MARIADB_EOF

    sudo systemctl restart mariadb || handle_error 10 "Failed to restart MariaDB"
    sudo systemctl enable mariadb

    # Verify MariaDB is accessible
    log_info "Verifying MariaDB installation..."
    if sudo mariadb -e "SELECT VERSION();" > /dev/null 2>&1; then
        MARIADB_VER=$(sudo mariadb -N -e "SELECT VERSION();")
        log_success "MariaDB ${MARIADB_VER} is running and accessible"
    else
        handle_error 10 "MariaDB verification failed"
    fi

    save_state 10
else
    log_info "Skipping Step 10 (already completed)"
fi

# ============================================================================
# STEP 11: Setup Nginx from fospackv69
# ============================================================================
CURRENT_STEP=11

if [ $RESUME_STEP -le 11 ]; then
    log_step "Step 11: Setting up Nginx with HTTP-FLV Module"
    log_progress "Configuring Nginx from fospackv69"

    # Check if fospackv69 exists in project directory
    FOSPACK_DIR="${FOS_DIR}/fospackv69"

    if [ ! -d "$FOSPACK_DIR" ]; then
        log_info "fospackv69 not found in project, cloning..."
        cd "${FOS_DIR}"
        git clone --recurse-submodules https://github.com/theraw/fospackv69.git 2>&1 | tee -a "$INSTALL_LOG" || handle_error 11 "Failed to clone fospackv69"
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
            sudo bash build-ubuntu.sh 2>&1 | tee -a "$INSTALL_LOG" || handle_error 11 "Nginx build failed"
        elif [ -f "build-debian12.sh" ]; then
            log_info "Using Debian/Ubuntu compatible build script..."
            sudo bash build-debian12.sh 2>&1 | tee -a "$INSTALL_LOG" || handle_error 11 "Nginx build failed"
        elif [ -f "build-for-project.sh" ]; then
            log_info "Using project build script..."
            sudo bash build-for-project.sh 2>&1 | tee -a "$INSTALL_LOG" || handle_error 11 "Nginx build failed"
        elif [ -f "build.sh" ]; then
            log_warn "Using generic build script..."
            sudo bash build.sh 2>&1 | tee -a "$INSTALL_LOG" || handle_error 11 "Nginx build failed"
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

    save_state 11
else
    log_info "Skipping Step 11 (already completed)"
fi

# ============================================================================
# STEP 12: Verify Web Application Files
# ============================================================================
CURRENT_STEP=12

if [ $RESUME_STEP -le 12 ]; then
    log_step "Step 12: Verifying Web Application"
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
        handle_error 12 "composer.json not found - this doesn't appear to be a valid FOS-Streaming installation"
    fi

    log_success "Web application files verified"

    save_state 12
else
    log_info "Skipping Step 12 (already completed)"
fi

# ============================================================================
# STEP 13: Install Composer Dependencies
# ============================================================================
CURRENT_STEP=13

if [ $RESUME_STEP -le 13 ]; then
    log_step "Step 13: Installing PHP Dependencies with Composer"
    log_progress "Installing Composer packages"

    cd "${FOS_DIR}"

    # Copy .env.example to .env if it doesn't exist (needed for some Laravel operations)
    if [ ! -f ".env" ] && [ -f ".env.example" ]; then
        log_info "Creating .env from .env.example..."
        cp .env.example .env
    fi

    log_info "Installing Composer dependencies..."
    log_cmd "composer install --no-dev --optimize-autoloader --no-interaction"
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$INSTALL_LOG" || handle_error 13 "Composer install failed"

    # Regenerate optimized autoloader
    log_info "Optimizing autoloader..."
    log_cmd "composer dump-autoload --optimize --no-dev"
    composer dump-autoload --optimize --no-dev 2>&1 | tee -a "$INSTALL_LOG" || log_warn "Autoloader optimization had warnings"

    log_success "Composer dependencies installed and autoloader optimized"

    save_state 13
else
    log_info "Skipping Step 13 (already completed)"
fi

# ============================================================================
# STEP 14: Setup Production Environment
# ============================================================================
CURRENT_STEP=14

if [ $RESUME_STEP -le 14 ]; then
    log_step "Step 14: Creating Production .env File"
    log_progress "Configuring environment variables"

    # Generate app key only if not already set
    if [ -z "$APP_KEY" ]; then
        log_info "Generating application encryption key..."
        APP_KEY="base64:$(openssl rand -base64 32)"
    else
        log_info "Using saved application key"
    fi

    # Generate Redis password (will be used later when Redis is installed)
    if [ -z "$REDIS_PASSWORD" ]; then
        log_info "Generating Redis password..."
        REDIS_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    else
        log_info "Using saved Redis password"
    fi

    # Detect FFmpeg paths (may already be installed, or will be installed in Step 17)
    FFMPEG_BIN=$(which ffmpeg 2>/dev/null)
    FFPROBE_BIN=$(which ffprobe 2>/dev/null)
    # Check common paths if 'which' fails
    if [ -z "$FFMPEG_BIN" ] || [ ! -x "$FFMPEG_BIN" ]; then
        for path in /usr/bin/ffmpeg /usr/local/bin/ffmpeg /opt/ffmpeg/bin/ffmpeg; do
            if [ -x "$path" ]; then FFMPEG_BIN="$path"; break; fi
        done
    fi
    if [ -z "$FFPROBE_BIN" ] || [ ! -x "$FFPROBE_BIN" ]; then
        for path in /usr/bin/ffprobe /usr/local/bin/ffprobe /opt/ffmpeg/bin/ffprobe; do
            if [ -x "$path" ]; then FFPROBE_BIN="$path"; break; fi
        done
    fi
    # Default fallback if not found (will be installed later)
    FFMPEG_BIN="${FFMPEG_BIN:-/usr/bin/ffmpeg}"
    FFPROBE_BIN="${FFPROBE_BIN:-/usr/bin/ffprobe}"
    log_info "FFmpeg path for .env: ${FFMPEG_BIN}"

    # Get server IP for configuration
    if [ -z "$PUBLIC_IP" ]; then
        PUBLIC_IP=$(curl -s --connect-timeout 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
    fi

    log_info "Creating production .env file from template..."

    # Create comprehensive production .env
    tee "${FOS_DIR}/.env" > /dev/null <<ENV_EOF
################################################################################
# FOS-Streaming v70 Production Environment Configuration
# Generated: $(date '+%Y-%m-%d %H:%M:%S')
# WARNING: This file contains sensitive credentials. Keep it secure!
################################################################################

# =============================================================================
# Application Settings
# =============================================================================
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
PUBLIC_IP=${PUBLIC_IP}

# =============================================================================
# Server Ports (Cloudflare SSL Compatible)
# =============================================================================
WEB_PORT=${WEB_PORT}
STREAMING_PORT=${STREAM_PORT}
RTMP_PORT=${RTMP_PORT}
HTTPS_PORT=443
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443

# Laravel Sail (Development)
WWWUSER=$(id -u)
WWWGROUP=$(id -g)
APP_PORT=${WEB_PORT}
VITE_PORT=5173

# =============================================================================
# Database Configuration (MariaDB)
# =============================================================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=${SQL_PASSWD}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_PREFIX=

# =============================================================================
# Redis Configuration (Production Cache/Session/Queue)
# =============================================================================
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
REDIS_CLIENT=phpredis
REDIS_DB=0
REDIS_CACHE_DB=1

# =============================================================================
# Cache, Session & Queue (Using Redis for Production)
# =============================================================================
CACHE_DRIVER=redis
CACHE_PREFIX=fos_cache_
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
BROADCAST_DRIVER=redis

# =============================================================================
# Logging Configuration
# =============================================================================
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null
LOG_DAILY_DAYS=14

# =============================================================================
# Template & Cache Paths
# =============================================================================
VIEWS_PATH=views
CACHE_PATH=cache

# =============================================================================
# Streaming Configuration
# =============================================================================
WEB_IP=*
DOMAIN=${DOMAIN_NAME}
STREAMING_AUTH=true

# FFmpeg Configuration
FFMPEG_PATH=${FFMPEG_BIN}
FFPROBE_PATH=${FFPROBE_BIN}
FFMPEG_THREADS=0

# HLS Output
HLS_PATH=${FOS_DIR}/hl
HLS_URL_PREFIX=https://${DOMAIN_NAME}/live
HLS_SEGMENT_TIME=4
HLS_LIST_SIZE=5

# =============================================================================
# Security Settings
# =============================================================================
RATE_LIMIT_ENABLED=true
MAX_LOGIN_ATTEMPTS=5
LOGIN_TIMEOUT=900
SECURITY_LOGGING=true
FAIL2BAN_ENABLED=true
UFW_ENABLED=false

# Session Security
CSRF_TOKEN_TIMEOUT=7200
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Admin Panel Security (IMPORTANT: Keep this path secret)
ADMIN_PATH=${ADMIN_PATH}

# =============================================================================
# Mail Configuration (Configure for production)
# =============================================================================
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@${DOMAIN_NAME}"
MAIL_FROM_NAME="\${APP_NAME}"

# =============================================================================
# Development Tools (Disabled in Production)
# =============================================================================
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379

# =============================================================================
# Telescope & Debugging (Disabled in Production)
# =============================================================================
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false
ENV_EOF

    chmod 600 "${FOS_DIR}/.env"
    chown ${FOS_USER}:${FOS_USER} "${FOS_DIR}/.env"

    log_success "Production .env file created with Redis configuration"
    log_info "Redis, Cache, Session, and Queue configured to use Redis"

    save_state 14
else
    log_info "Skipping Step 14 (already completed)"
    # Load Redis password if resuming
    if [ -z "$REDIS_PASSWORD" ] && [ -f /root/REDIS_PASSWORD ]; then
        REDIS_PASSWORD=$(sudo cat /root/REDIS_PASSWORD 2>/dev/null || echo "")
    fi
fi

# ============================================================================
# STEP 15: Install NPM Dependencies and Build Frontend
# ============================================================================
CURRENT_STEP=15

if [ $RESUME_STEP -le 15 ]; then
    log_step "Step 15: Installing NPM Dependencies and Building Frontend"
    log_progress "Building frontend assets"

    if [ -f "${FOS_DIR}/package.json" ]; then
        log_info "package.json found, installing NPM dependencies..."

        cd "${FOS_DIR}"

        # Load NVM for this shell
        export NVM_DIR="${FOS_USER_HOME}/.nvm"
        [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

        # Install PM2 globally (required for background workers)
        log_info "Installing PM2 globally..."
        PM2_INSTALLED=false

        # Try normal npm global install first
        log_cmd "npm install -g pm2"
        if npm install -g pm2 2>&1 | tee -a "$INSTALL_LOG"; then
            PM2_INSTALLED=true
            log_success "PM2 installed globally via npm"
        else
            log_warn "npm global install failed, trying with sudo..."

            # Try with sudo
            log_cmd "sudo npm install -g pm2"
            if sudo npm install -g pm2 2>&1 | tee -a "$INSTALL_LOG"; then
                PM2_INSTALLED=true
                log_success "PM2 installed globally via sudo npm"
            else
                log_warn "sudo npm global install failed, trying to fix npm permissions..."

                # Try fixing npm global directory permissions
                NPM_PREFIX=$(npm config get prefix 2>/dev/null || echo "/usr/local")
                if [ -d "$NPM_PREFIX/lib/node_modules" ]; then
                    log_info "Fixing npm global directory permissions..."
                    sudo chown -R ${FOS_USER}:${FOS_USER} "$NPM_PREFIX/lib/node_modules" 2>/dev/null || true
                    sudo chown -R ${FOS_USER}:${FOS_USER} "$NPM_PREFIX/bin" 2>/dev/null || true

                    # Retry after fixing permissions
                    if npm install -g pm2 2>&1 | tee -a "$INSTALL_LOG"; then
                        PM2_INSTALLED=true
                        log_success "PM2 installed after fixing permissions"
                    fi
                fi

                # Last resort: use npx or local install
                if [ "$PM2_INSTALLED" = false ]; then
                    log_warn "Global PM2 install failed, will use local pm2 from node_modules"
                    log_info "PM2 will be available via: npx pm2 or ./node_modules/.bin/pm2"
                fi
            fi
        fi

        # Verify PM2 is accessible
        if command -v pm2 &> /dev/null; then
            PM2_VER=$(pm2 --version 2>/dev/null || echo "unknown")
            log_success "PM2 version ${PM2_VER} is accessible"
        elif [ -f "./node_modules/.bin/pm2" ]; then
            log_info "PM2 will be available locally after npm install"
        fi

        # Install project dependencies
        log_info "Installing NPM dependencies..."
        log_cmd "npm install"
        npm install 2>&1 | tee -a "$INSTALL_LOG" || handle_error 15 "npm install failed"

        # Update frontend config with admin path
        if [ -f "resources/js/config.js" ]; then
            log_info "Updating frontend config with admin path..."
            sed -i "s|export const ADMIN_PATH = '.*'|export const ADMIN_PATH = '${ADMIN_PATH}'|" resources/js/config.js
            log_info "Frontend config updated"
        fi

        # Build production assets
        if grep -q "\"build\":" package.json; then
            log_info "Running npm run build..."
            log_cmd "npm run build"
            npm run build 2>&1 | tee -a "$INSTALL_LOG" || handle_error 15 "npm build failed"
            log_success "Frontend assets built successfully"
        else
            log_warn "No build script found in package.json, skipping build step"
        fi

        # Clear application caches after build
        log_info "Clearing application caches..."

        # Clear Blade/template cache
        if [ -d "${FOS_DIR}/cache" ]; then
            rm -f "${FOS_DIR}/cache"/*.php 2>/dev/null || true
            log_info "Template cache cleared"
        fi

        # Clear Laravel storage caches
        if [ -d "${FOS_DIR}/storage/framework/cache" ]; then
            rm -rf "${FOS_DIR}/storage/framework/cache/data"/* 2>/dev/null || true
        fi
        if [ -d "${FOS_DIR}/storage/framework/views" ]; then
            rm -f "${FOS_DIR}/storage/framework/views"/*.php 2>/dev/null || true
        fi

        log_success "Application caches cleared"
    else
        log_info "No package.json found, skipping NPM setup"
    fi

    save_state 15
else
    log_info "Skipping Step 15 (already completed)"
fi

# ============================================================================
# STEP 16: Configure Application
# ============================================================================
CURRENT_STEP=16

if [ $RESUME_STEP -le 16 ]; then
    log_step "Step 16: Configuring FOS-Streaming Application"
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

    save_state 16
else
    log_info "Skipping Step 16 (already completed)"
fi

# ============================================================================
# STEP 17: Install FFmpeg with Streaming Optimizations
# ============================================================================
CURRENT_STEP=17

if [ $RESUME_STEP -le 17 ]; then
    log_step "Step 17: Installing FFmpeg with Streaming Optimizations"
    log_progress "Installing FFmpeg for low-latency streaming"

    # -------------------------------------------------------------------------
    # FFmpeg Detection Function
    # -------------------------------------------------------------------------
    detect_ffmpeg_path() {
        # Try 'which' first
        local detected=$(which ffmpeg 2>/dev/null)
        if [ -n "$detected" ] && [ -x "$detected" ]; then
            echo "$detected"
            return 0
        fi
        # Check common paths
        for path in /usr/bin/ffmpeg /usr/local/bin/ffmpeg /opt/ffmpeg/bin/ffmpeg /snap/bin/ffmpeg; do
            if [ -x "$path" ]; then
                echo "$path"
                return 0
            fi
        done
        # Fallback
        echo "/usr/bin/ffmpeg"
    }

    detect_ffprobe_path() {
        # Try 'which' first
        local detected=$(which ffprobe 2>/dev/null)
        if [ -n "$detected" ] && [ -x "$detected" ]; then
            echo "$detected"
            return 0
        fi
        # Check common paths
        for path in /usr/bin/ffprobe /usr/local/bin/ffprobe /opt/ffmpeg/bin/ffprobe /snap/bin/ffprobe; do
            if [ -x "$path" ]; then
                echo "$path"
                return 0
            fi
        done
        # Fallback
        echo "/usr/bin/ffprobe"
    }

    # -------------------------------------------------------------------------
    # Install FFmpeg from apt (includes all codecs and hardware acceleration)
    # -------------------------------------------------------------------------
    log_info "Installing FFmpeg and multimedia packages..."

    FFMPEG_PACKAGES=(
        "ffmpeg"
        "libavcodec-extra"
        "libavformat-dev"
        "libavutil-dev"
        "libswscale-dev"
        "libswresample-dev"
        "libavfilter-dev"
        "libavdevice-dev"
        "libpostproc-dev"
        "libx264-dev"
        "libx265-dev"
        "libvpx-dev"
        "libfdk-aac-dev"
        "libmp3lame-dev"
        "libopus-dev"
        "libvorbis-dev"
        "libtheora-dev"
        "libaom-dev"
        "libwebp-dev"
        "libsrt-dev"
        "vainfo"
        "mesa-va-drivers"
    )

    # Install FFmpeg packages (some may not be available on all systems)
    for pkg in "${FFMPEG_PACKAGES[@]}"; do
        install_package_with_retry "$pkg" || {
            log_warn "Package $pkg not available, skipping..."
        }
    done

    # -------------------------------------------------------------------------
    # Detect FFmpeg paths after installation
    # -------------------------------------------------------------------------
    log_info "Detecting FFmpeg installation paths..."
    FFMPEG_BIN=$(detect_ffmpeg_path)
    FFPROBE_BIN=$(detect_ffprobe_path)

    # Verify FFmpeg installation
    if [ ! -x "$FFMPEG_BIN" ]; then
        handle_error 17 "FFmpeg not found - installation failed"
    fi

    if [ ! -x "$FFPROBE_BIN" ]; then
        handle_error 17 "FFprobe not found - installation failed"
    fi

    FFMPEG_VERSION=$($FFMPEG_BIN -version 2>/dev/null | head -n1 | awk '{print $3}')
    log_success "FFmpeg ${FFMPEG_VERSION} detected at: ${FFMPEG_BIN}"
    log_success "FFprobe detected at: ${FFPROBE_BIN}"

    # Export for use in later steps
    export FFMPEG_BIN
    export FFPROBE_BIN

    # -------------------------------------------------------------------------
    # System-Level Low Latency Optimizations
    # -------------------------------------------------------------------------
    log_info "Applying low latency streaming optimizations..."

    # Increase file descriptor limits for streaming
    sudo tee /etc/security/limits.d/fos-streaming.conf > /dev/null <<LIMITS_EOF
# FOS-Streaming Low Latency Optimizations
# Increase file descriptor limits for high-concurrency streaming

${FOS_USER}     soft    nofile          65535
${FOS_USER}     hard    nofile          65535
${FOS_USER}     soft    nproc           65535
${FOS_USER}     hard    nproc           65535
root            soft    nofile          65535
root            hard    nofile          65535
*               soft    memlock         unlimited
*               hard    memlock         unlimited
LIMITS_EOF

    # Kernel parameters for low latency networking and streaming
    sudo tee /etc/sysctl.d/99-fos-streaming.conf > /dev/null <<SYSCTL_EOF
# FOS-Streaming Low Latency Kernel Parameters
# Optimized for live video streaming

# Network buffer sizes (increase for high throughput)
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.core.rmem_default = 1048576
net.core.wmem_default = 1048576
net.ipv4.tcp_rmem = 4096 1048576 16777216
net.ipv4.tcp_wmem = 4096 1048576 16777216

# Reduce TCP latency
net.ipv4.tcp_low_latency = 1
net.ipv4.tcp_fastopen = 3
net.ipv4.tcp_timestamps = 1
net.ipv4.tcp_sack = 1
net.ipv4.tcp_window_scaling = 1

# Connection handling
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_max_tw_buckets = 2000000
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 10

# Keep-alive for long-running stream connections
net.ipv4.tcp_keepalive_time = 60
net.ipv4.tcp_keepalive_intvl = 10
net.ipv4.tcp_keepalive_probes = 6

# UDP optimizations for SRT/RTP streaming
net.ipv4.udp_mem = 65536 131072 262144
net.ipv4.udp_rmem_min = 8192
net.ipv4.udp_wmem_min = 8192

# Memory management for streaming buffers
vm.swappiness = 10
vm.dirty_ratio = 40
vm.dirty_background_ratio = 10

# File system
fs.file-max = 2097152
fs.inotify.max_user_watches = 524288
fs.inotify.max_user_instances = 1024
SYSCTL_EOF

    # Apply sysctl settings
    sudo sysctl -p /etc/sysctl.d/99-fos-streaming.conf 2>&1 | tee -a "$INSTALL_LOG" || {
        log_warn "Some sysctl settings may not be available on this kernel"
    }

    # -------------------------------------------------------------------------
    # Create FFmpeg wrapper script for consistent low-latency settings
    # -------------------------------------------------------------------------
    log_info "Creating FFmpeg streaming wrapper..."

    sudo tee /usr/local/bin/ffmpeg-stream > /dev/null <<'FFMPEG_WRAPPER_EOF'
#!/usr/bin/env bash
# FFmpeg wrapper with low-latency streaming defaults
# Used by FOS-Streaming for optimized stream processing

# Default low-latency options (can be overridden by command line)
FFMPEG_LOW_LATENCY_OPTS=(
    -fflags "+nobuffer+flush_packets"
    -flags "low_delay"
    -strict "experimental"
    -avioflags "direct"
    -probesize "32"
    -analyzeduration "0"
    -sync "ext"
)

# Prepend low-latency options, but allow overrides
exec /usr/bin/ffmpeg "${FFMPEG_LOW_LATENCY_OPTS[@]}" "$@"
FFMPEG_WRAPPER_EOF

    sudo chmod 755 /usr/local/bin/ffmpeg-stream
    log_info "FFmpeg streaming wrapper created at /usr/local/bin/ffmpeg-stream"

    # -------------------------------------------------------------------------
    # Configure sudoers for FFmpeg (no password required for streaming)
    # -------------------------------------------------------------------------
    log_info "Configuring FFmpeg sudo access..."
    sudo rm -f /etc/sudoers.d/fos-ffmpeg 2>/dev/null || true

    sudo tee /etc/sudoers.d/fos-ffmpeg > /dev/null <<SUDOERS_EOF
# FOS-Streaming FFmpeg sudo access
${FOS_USER} ALL = (root) NOPASSWD: ${FFMPEG_BIN}
${FOS_USER} ALL = (root) NOPASSWD: ${FFPROBE_BIN}
${FOS_USER} ALL = (root) NOPASSWD: /usr/local/bin/ffmpeg-stream
SUDOERS_EOF

    sudo chmod 0440 /etc/sudoers.d/fos-ffmpeg

    # -------------------------------------------------------------------------
    # Check for hardware acceleration support
    # -------------------------------------------------------------------------
    log_info "Checking hardware acceleration support..."

    HW_ACCEL_AVAILABLE=""

    # Check for VAAPI (Intel/AMD)
    if command -v vainfo &> /dev/null; then
        if vainfo 2>/dev/null | grep -q "vainfo"; then
            HW_ACCEL_AVAILABLE="vaapi"
            log_info "VAAPI hardware acceleration available"
        fi
    fi

    # Check for NVIDIA NVENC
    if command -v nvidia-smi &> /dev/null; then
        if nvidia-smi 2>/dev/null | grep -q "NVIDIA"; then
            HW_ACCEL_AVAILABLE="${HW_ACCEL_AVAILABLE} nvenc"
            log_info "NVIDIA NVENC hardware acceleration available"
        fi
    fi

    # Check FFmpeg hardware encoders
    FFMPEG_ENCODERS=$($FFMPEG_BIN -encoders 2>/dev/null || echo "")
    if echo "$FFMPEG_ENCODERS" | grep -q "h264_vaapi"; then
        log_info "h264_vaapi encoder available"
    fi
    if echo "$FFMPEG_ENCODERS" | grep -q "h264_nvenc"; then
        log_info "h264_nvenc encoder available"
    fi
    if echo "$FFMPEG_ENCODERS" | grep -q "hevc_vaapi"; then
        log_info "hevc_vaapi encoder available"
    fi
    if echo "$FFMPEG_ENCODERS" | grep -q "hevc_nvenc"; then
        log_info "hevc_nvenc encoder available"
    fi

    if [ -z "$HW_ACCEL_AVAILABLE" ]; then
        log_info "No hardware acceleration detected - using software encoding"
    else
        log_success "Hardware acceleration available: ${HW_ACCEL_AVAILABLE}"
    fi

    save_state 17
else
    log_info "Skipping Step 17 (already completed)"
    # Detect FFmpeg paths (may have been installed previously)
    FFMPEG_BIN=$(which ffmpeg 2>/dev/null || echo "/usr/bin/ffmpeg")
    FFPROBE_BIN=$(which ffprobe 2>/dev/null || echo "/usr/bin/ffprobe")
    # Verify they exist
    for path in /usr/bin/ffmpeg /usr/local/bin/ffmpeg /opt/ffmpeg/bin/ffmpeg; do
        if [ -x "$path" ]; then FFMPEG_BIN="$path"; break; fi
    done
    for path in /usr/bin/ffprobe /usr/local/bin/ffprobe /opt/ffmpeg/bin/ffprobe; do
        if [ -x "$path" ]; then FFPROBE_BIN="$path"; break; fi
    done
    export FFMPEG_BIN FFPROBE_BIN
    log_info "Using FFmpeg at: ${FFMPEG_BIN}"
fi

# ============================================================================
# STEP 18: Install Redis and Streaming Security Tools
# ============================================================================
CURRENT_STEP=18

if [ $RESUME_STEP -le 18 ]; then
    log_step "Step 18: Installing Redis and Security Tools"
    log_progress "Installing Redis, security and streaming tools"

    SECURITY_PACKAGES=(
        "redis-server"
        "redis-tools"
        "libsrt-openssl-dev"
        "srt-tools"
        "pkg-config"
        "libngtcp2-dev"
        "libnghttp3-dev"
        "fail2ban"
        "ufw"
        "jq"
    )

    install_packages_individually "${SECURITY_PACKAGES[@]}" || {
        log_warn "Some security packages may not be available on this system"
    }

    # -------------------------------------------------------------------------
    # Redis Configuration for Production
    # -------------------------------------------------------------------------
    log_info "Configuring Redis for production..."

    # Generate Redis password if not already set
    if [ -z "$REDIS_PASSWORD" ]; then
        log_info "Generating Redis password..."
        REDIS_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    fi

    # Save Redis password securely
    echo "$REDIS_PASSWORD" | sudo tee /root/REDIS_PASSWORD > /dev/null
    sudo chmod 600 /root/REDIS_PASSWORD
    log_info "Redis password saved to /root/REDIS_PASSWORD"

    # Backup original Redis config
    if [ -f /etc/redis/redis.conf ]; then
        sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.backup 2>/dev/null || true
    fi

    # Create FOS Redis configuration
    log_info "Creating Redis configuration..."
    sudo tee /etc/redis/redis.conf > /dev/null <<REDIS_CONF_EOF
# FOS-Streaming Redis Configuration
# Generated: $(date '+%Y-%m-%d %H:%M:%S')

# Network
bind 127.0.0.1 ::1
port 6379
protected-mode yes
tcp-backlog 511
timeout 0
tcp-keepalive 300

# Security
requirepass ${REDIS_PASSWORD}

# General
daemonize yes
supervised systemd
pidfile /run/redis/redis-server.pid
loglevel notice
logfile /var/log/redis/redis-server.log
databases 16

# Snapshotting (Persistence)
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# Append Only Mode (AOF) for better durability
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Memory Management
maxmemory 256mb
maxmemory-policy allkeys-lru

# Lazy Freeing
lazyfree-lazy-eviction no
lazyfree-lazy-expire no
lazyfree-lazy-server-del no
replica-lazy-flush no

# Security - Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG ""
rename-command SHUTDOWN SHUTDOWN_REDIS_FOS

# Slow Log
slowlog-log-slower-than 10000
slowlog-max-len 128

# Client Output Buffer Limits
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60
REDIS_CONF_EOF

    # Create Redis directories
    sudo mkdir -p /var/lib/redis /var/log/redis /run/redis
    sudo chown redis:redis /var/lib/redis /var/log/redis /run/redis
    sudo chmod 750 /var/lib/redis /var/log/redis

    # Restart Redis with new configuration
    log_info "Restarting Redis service..."
    sudo systemctl stop redis-server 2>/dev/null || true
    sudo systemctl start redis-server || {
        log_warn "Redis failed to start with new config, trying default..."
        sudo cp /etc/redis/redis.conf.backup /etc/redis/redis.conf 2>/dev/null || true
        sudo systemctl start redis-server || log_error "Redis failed to start"
    }
    sudo systemctl enable redis-server

    # Verify Redis is running and accessible
    log_info "Verifying Redis installation..."
    sleep 2
    if redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q "PONG"; then
        REDIS_VERSION=$(redis-cli -a "${REDIS_PASSWORD}" INFO server 2>/dev/null | grep redis_version | cut -d: -f2 | tr -d '\r')
        log_success "Redis ${REDIS_VERSION} is running with password authentication"
    else
        # Try without password (in case config didn't apply)
        if redis-cli ping 2>/dev/null | grep -q "PONG"; then
            log_warn "Redis is running but without password authentication"
            REDIS_PASSWORD="null"
        else
            log_error "Redis is not responding"
        fi
    fi

    # -------------------------------------------------------------------------
    # SRT Tools Verification
    # -------------------------------------------------------------------------
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

    save_state 18
else
    log_info "Skipping Step 18 (already completed)"
    # Load Redis password from file if resuming
    if [ -f /root/REDIS_PASSWORD ]; then
        REDIS_PASSWORD=$(sudo cat /root/REDIS_PASSWORD 2>/dev/null || echo "null")
    fi
fi

# ============================================================================
# STEP 19: Configure Nginx and SSL Certificates
# ============================================================================
CURRENT_STEP=19

# Define nginx paths based on fospackv69 location
NGINX_DIR="${FOS_DIR}/fospackv69/fos/nginx"
NGINX_CONF="${NGINX_DIR}/conf/nginx.conf"
NGINX_BIN="${NGINX_DIR}/sbin/nginx_fos"

if [ $RESUME_STEP -le 19 ]; then
    log_step "Step 19: Configuring Nginx and SSL Certificates"
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

    save_state 19
else
    log_info "Skipping Step 19 (already completed)"
fi

# ============================================================================
# STEP 20: Configure System Startup
# ============================================================================
CURRENT_STEP=20

if [ $RESUME_STEP -le 20 ]; then
    log_step "Step 20: Configuring System Startup"
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

    save_state 20
else
    log_info "Skipping Step 20 (already completed)"
fi

# ============================================================================
# STEP 21: Initialize Database
# ============================================================================
CURRENT_STEP=21

if [ $RESUME_STEP -le 21 ]; then
    log_step "Step 21: Initializing Database"
    log_progress "Running database migrations and seeders via artisan"

    cd "${FOS_DIR}"

    if [ ! -f "artisan" ]; then
        handle_error 21 "Artisan CLI not found at ${FOS_DIR}/artisan"
    fi

    # Run database migrations
    log_info "Running database migrations..."
    log_cmd "php artisan migrate --force"
    if php artisan migrate --force 2>&1 | tee -a "$INSTALL_LOG"; then
        log_success "Database migrations completed successfully"
    else
        handle_error 21 "Database migrations failed. Check your database credentials in .env"
    fi

    # Run database seeders
    log_info "Running database seeders..."
    log_cmd "php artisan db:seed --force"
    if php artisan db:seed --force 2>&1 | tee -a "$INSTALL_LOG"; then
        log_success "Database seeded successfully"
    else
        handle_error 21 "Database seeding failed"
    fi

    # Show migration status
    log_info "Migration status:"
    php artisan migrate:status --no-ansi 2>/dev/null || true

    # Verify database deployment
    log_info "Verifying database deployment..."

    # Check critical tables exist
    TABLES_CHECK=$(sudo mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'fos_streaming';" 2>/dev/null || echo "0")
    if [ "$TABLES_CHECK" -gt 0 ]; then
        log_success "Database has ${TABLES_CHECK} tables"
    else
        handle_error 21 "Database verification failed - no tables found"
    fi

    # Check admin user was seeded
    ADMIN_CHECK=$(sudo mariadb -N -e "SELECT COUNT(*) FROM fos_streaming.staff WHERE username = 'admin';" 2>/dev/null || echo "0")
    if [ "$ADMIN_CHECK" -gt 0 ]; then
        log_success "Admin user seeded successfully"
    else
        log_warn "Admin user not found - you may need to create one manually"
    fi

    # Check packages were seeded
    PACKAGES_CHECK=$(sudo mariadb -N -e "SELECT COUNT(*) FROM fos_streaming.packages;" 2>/dev/null || echo "0")
    if [ "$PACKAGES_CHECK" -gt 0 ]; then
        log_success "Packages seeded: ${PACKAGES_CHECK} packages"
    else
        log_warn "No packages found - seeding may have failed"
    fi

    # Check settings were seeded
    SETTINGS_CHECK=$(sudo mariadb -N -e "SELECT COUNT(*) FROM fos_streaming.settings;" 2>/dev/null || echo "0")
    if [ "$SETTINGS_CHECK" -gt 0 ]; then
        log_success "Settings seeded: ${SETTINGS_CHECK} settings"
    else
        log_warn "No settings found - seeding may have failed"
    fi

    # Check PM2 workers were seeded
    PM2_CHECK=$(sudo mariadb -N -e "SELECT COUNT(*) FROM fos_streaming.pm2_workers;" 2>/dev/null || echo "0")
    if [ "$PM2_CHECK" -gt 0 ]; then
        log_success "PM2 workers seeded: ${PM2_CHECK} workers"
    else
        log_warn "No PM2 workers found - seeding may have failed"
    fi

    log_success "Database initialized and verified"

    save_state 21
else
    log_info "Skipping Step 21 (already completed)"
fi

# ============================================================================
# STEP 22: Setup Cron Job and Security Hardening
# ============================================================================
CURRENT_STEP=22

if [ $RESUME_STEP -le 22 ]; then
    log_step "Step 22: Setting up Cron Job and Security Hardening"
    log_progress "Configuring scheduled tasks and security"

    log_info "Adding cron job for stream monitoring..."
    CRON_CMD="*/2 * * * * /usr/bin/php ${FOS_DIR}/cron.php"
    (crontab -l 2>/dev/null | grep -v "cron.php"; echo "$CRON_CMD") | crontab -

    # -------------------------------------------------------------------------
    # Firewall Configuration (UFW)
    # -------------------------------------------------------------------------
    # Check if UFW is applicable for this environment
    SKIP_UFW=false
    UFW_REASON=""

    if is_wsl; then
        SKIP_UFW=true
        UFW_REASON="WSL environments are protected by Windows Firewall"
    elif is_container; then
        SKIP_UFW=true
        UFW_REASON="Container environments typically manage firewall at host level"
    elif [ ! -f /proc/sys/net/ipv4/ip_forward ]; then
        SKIP_UFW=true
        UFW_REASON="System does not support IP forwarding (virtualized or restricted environment)"
    fi

    if [ "$SKIP_UFW" = true ]; then
        log_warn "Skipping UFW firewall configuration: ${UFW_REASON}"
        echo ""
        log_info "Manual firewall configuration may be required:"
        log_info "  - Web Port: ${WEB_PORT}/tcp"
        log_info "  - Streaming Port: ${STREAM_PORT}/tcp"
        log_info "  - RTMP Port: ${RTMP_PORT}/tcp"

        if is_wsl; then
            echo ""
            log_info "For WSL, configure Windows Firewall if external access is needed:"
            log_info "  1. Open 'Windows Defender Firewall with Advanced Security'"
            log_info "  2. Create Inbound Rules for ports: ${WEB_PORT}, ${STREAM_PORT}, ${RTMP_PORT}"
            log_info "  3. Or use PowerShell (Admin): New-NetFirewallRule -DisplayName 'FOS-Streaming' -Direction Inbound -LocalPort ${WEB_PORT},${STREAM_PORT},${RTMP_PORT} -Protocol TCP -Action Allow"
        fi
    elif command -v ufw &> /dev/null; then
        log_info "Configuring UFW firewall rules..."
        echo ""

        # For local deployments, ask if user wants UFW
        if [ "${IS_LOCAL_DEPLOYMENT:-0}" = "1" ] || [ "$NETWORK_ENV" = "local" ]; then
            log_warn "Local deployment detected. UFW may not be necessary."
            log_info "UFW is recommended for production servers but optional for local development."
            echo ""
            if ! read_confirm "Do you want to configure UFW firewall?" "n"; then
                log_info "Skipping UFW configuration"
                SKIP_UFW=true
            fi
        fi

        if [ "$SKIP_UFW" != true ]; then
            # Detect SSH port from sshd_config (may not be 22)
            SSH_PORT=$(grep -E "^Port\s+" /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}' | head -1)
            if [ -z "$SSH_PORT" ]; then
                # Check for Port in included configs
                SSH_PORT=$(grep -rh "^Port\s+" /etc/ssh/sshd_config.d/ 2>/dev/null | awk '{print $2}' | head -1)
            fi
            # Default to 22 if not found
            SSH_PORT="${SSH_PORT:-22}"
            log_info "Detected SSH port: ${SSH_PORT}"

            # Set default policies: deny incoming, allow outgoing
            log_info "Setting default firewall policies..."
            sudo ufw default deny incoming 2>/dev/null || true
            sudo ufw default allow outgoing 2>/dev/null || true

            # CRITICAL: Allow SSH first to prevent lockout
            log_warn "Allowing SSH access (port ${SSH_PORT}) to prevent lockout..."
            sudo ufw allow ${SSH_PORT}/tcp comment 'SSH Access' 2>/dev/null || true

            # FOS Streaming ports
            log_info "Allowing FOS-Streaming ports..."
            sudo ufw allow ${WEB_PORT}/tcp comment 'FOS Web Panel' 2>/dev/null || true
            sudo ufw allow ${STREAM_PORT}/tcp comment 'FOS Streaming' 2>/dev/null || true
            sudo ufw allow ${RTMP_PORT}/tcp comment 'FOS RTMP Ingest' 2>/dev/null || true

            # Standard web ports
            sudo ufw allow 80/tcp comment 'HTTP' 2>/dev/null || true
            sudo ufw allow 443/tcp comment 'HTTPS' 2>/dev/null || true

            # Cloudflare-compatible HTTPS ports (only for public deployments)
            if [ "${IS_LOCAL_DEPLOYMENT:-0}" != "1" ] && [ "$NETWORK_ENV" != "local" ]; then
                log_info "Allowing Cloudflare HTTPS alternate ports..."
                sudo ufw allow 2053/tcp comment 'Cloudflare HTTPS Alt' 2>/dev/null || true
                sudo ufw allow 2083/tcp comment 'Cloudflare HTTPS Alt' 2>/dev/null || true
                sudo ufw allow 2087/tcp comment 'Cloudflare HTTPS Alt' 2>/dev/null || true
                sudo ufw allow 2096/tcp comment 'Cloudflare HTTPS Alt' 2>/dev/null || true
                sudo ufw allow 8443/tcp comment 'Cloudflare HTTPS Alt' 2>/dev/null || true
            fi

            # Enable UFW (non-interactive)
            log_info "Enabling UFW firewall..."
            echo "y" | sudo ufw enable 2>/dev/null || true

            # Show status
            echo ""
            log_info "UFW Firewall Status:"
            sudo ufw status verbose 2>&1 | tee -a "$INSTALL_LOG" || true

            echo ""
            log_success "Firewall enabled - Default: DENY incoming, ALLOW outgoing"
            log_success "Allowed ports: SSH:${SSH_PORT}, HTTP:80, HTTPS:443, Web:${WEB_PORT}, Stream:${STREAM_PORT}, RTMP:${RTMP_PORT}"
        fi
    else
        log_warn "UFW is not installed. Manual firewall configuration may be required."
        log_info "Required ports: Web:${WEB_PORT}, Stream:${STREAM_PORT}, RTMP:${RTMP_PORT}"
    fi

    # Set secure permissions on config files
    [ -f "${FOS_DIR}/config.php" ] && chmod 600 "${FOS_DIR}/config.php"
    [ -f "${FOS_DIR}/config/ports.php" ] && chmod 644 "${FOS_DIR}/config/ports.php"
    [ -f "${FOS_DIR}/.env" ] && chmod 600 "${FOS_DIR}/.env"
    [ -f "/root/MARIADB_FOS_PASSWORD" ] && sudo chmod 600 /root/MARIADB_FOS_PASSWORD

    save_state 22
else
    log_info "Skipping Step 22 (already completed)"
fi

# ============================================================================
# STEP 23: Start PM2 Background Workers
# ============================================================================
CURRENT_STEP=23

if [ $RESUME_STEP -le 23 ]; then
    log_step "Step 23: Starting PM2 Background Workers"
    log_progress "Initializing background workers"

    cd "${FOS_DIR}"

    # Load NVM for this shell
    export NVM_DIR="${FOS_USER_HOME}/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

    # Determine PM2 command (global or local)
    PM2_CMD=""
    if command -v pm2 &> /dev/null; then
        PM2_CMD="pm2"
        log_info "Using global PM2"
    elif [ -x "./node_modules/.bin/pm2" ]; then
        PM2_CMD="./node_modules/.bin/pm2"
        log_info "Using local PM2 from node_modules"
    elif command -v npx &> /dev/null; then
        PM2_CMD="npx pm2"
        log_info "Using PM2 via npx"
    else
        log_error "PM2 not found - cannot start workers"
        log_info "Try running: npm install -g pm2"
        PM2_CMD=""
    fi

    # Check if ecosystem.config.js exists
    if [ -f "ecosystem.config.js" ] && [ -n "$PM2_CMD" ]; then
        log_info "Found ecosystem.config.js, starting PM2 workers..."

        # Stop any existing PM2 processes
        $PM2_CMD delete all 2>/dev/null || true

        # Start PM2 workers
        log_cmd "npm run pm2:start"
        if npm run pm2:start 2>&1 | tee -a "$INSTALL_LOG"; then
            log_success "PM2 workers started successfully"

            # Save PM2 configuration for system startup
            log_info "Saving PM2 configuration for system startup..."
            $PM2_CMD save 2>&1 | tee -a "$INSTALL_LOG" || log_warn "Could not save PM2 config"

            # Setup PM2 startup script (only works with global pm2)
            if [ "$PM2_CMD" = "pm2" ]; then
                log_info "Setting up PM2 startup script..."
                # Generate the startup command
                STARTUP_CMD=$($PM2_CMD startup 2>&1 | grep "sudo env" || true)
                if [ -n "$STARTUP_CMD" ]; then
                    log_info "Running PM2 startup command..."
                    eval "$STARTUP_CMD" 2>&1 | tee -a "$INSTALL_LOG" || log_warn "PM2 startup setup may need manual configuration"
                else
                    $PM2_CMD startup 2>&1 | tee -a "$INSTALL_LOG" || log_warn "PM2 startup setup may need manual configuration"
                fi
            else
                log_warn "PM2 startup scripts require global PM2 installation"
                log_info "For auto-start on boot, install PM2 globally: sudo npm install -g pm2"
            fi

            # Show PM2 status
            log_info "PM2 Worker Status:"
            $PM2_CMD status 2>&1 || true
        else
            log_warn "PM2 workers failed to start - you can start them manually later with: npm run pm2:start"
        fi
    elif [ ! -f "ecosystem.config.js" ]; then
        log_warn "ecosystem.config.js not found - PM2 workers not configured"
        log_info "You can generate it from the admin panel: Settings → PM2 Manager"
    fi

    # Verify workers are running
    if [ -n "$PM2_CMD" ]; then
        PM2_RUNNING=$($PM2_CMD list 2>/dev/null | grep -c "online" || echo "0")
        if [ "$PM2_RUNNING" -gt 0 ]; then
            log_success "${PM2_RUNNING} PM2 workers are running"
        else
            log_warn "No PM2 workers are currently running"
        fi
    fi

    save_state 23
else
    log_info "Skipping Step 23 (already completed)"
fi

# ============================================================================
# INSTALLATION COMPLETE
# ============================================================================

# Clear installation state on successful completion
clear_state

# Finalize log
cat >> "$INSTALL_LOG" <<EOF

================================================================================
Installation Completed Successfully
Finished: $(date '+%Y-%m-%d %H:%M:%S')
================================================================================

Summary:
- PHP Version: ${PHP_VERSION}
- MariaDB Version: ${MARIADB_VERSION}
- Node.js Version: ${NODE_VERSION}
- OS: ${OS_TYPE} ${OS_VERSION} (${OS_CODENAME})
- Network Environment: ${NETWORK_ENV:-detected}
- Installation User: ${FOS_USER}
- Project Directory: ${FOS_DIR}
- Domain/Address: ${DOMAIN_NAME}
- Ports: Web=${WEB_PORT}, Stream=${STREAM_PORT}, RTMP=${RTMP_PORT}
- Local Deployment: ${IS_LOCAL_DEPLOYMENT:-0}

Note: Sensitive information (passwords, keys) has been redacted from this log.
EOF

log_step "Installation Complete!"

# Get versions for display
COMPOSER_VER=$(composer --version 2>/dev/null | head -n1 | awk '{print $3}' || echo "not installed")
NODE_VER=$(node --version 2>/dev/null || echo "not installed")
NPM_VER=$(npm --version 2>/dev/null || echo "not installed")

# Determine protocol based on deployment type
if [ "${IS_LOCAL_DEPLOYMENT:-0}" = "1" ]; then
    ACCESS_PROTOCOL="http"
    RTMP_PROTOCOL="rtmp"
else
    ACCESS_PROTOCOL="https"
    RTMP_PROTOCOL="rtmp"
fi

# Generate access URLs
if [ "$DOMAIN_NAME" = "localhost" ] || [[ "$DOMAIN_NAME" =~ ^127\. ]]; then
    SUBSCRIBER_URL="${ACCESS_PROTOCOL}://localhost:${WEB_PORT}/"
    ADMIN_URL="${ACCESS_PROTOCOL}://localhost:${WEB_PORT}${ADMIN_PATH}"
    DIRECT_WEB="${ACCESS_PROTOCOL}://localhost:${WEB_PORT}"
    DIRECT_STREAM="${ACCESS_PROTOCOL}://localhost:${STREAM_PORT}"
    RTMP_URL="${RTMP_PROTOCOL}://localhost:${RTMP_PORT}"
elif [[ "$DOMAIN_NAME" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    SUBSCRIBER_URL="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}/"
    ADMIN_URL="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}${ADMIN_PATH}"
    DIRECT_WEB="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}"
    DIRECT_STREAM="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${STREAM_PORT}"
    RTMP_URL="${RTMP_PROTOCOL}://${DOMAIN_NAME}:${RTMP_PORT}"
elif [[ "$DOMAIN_NAME" =~ \.local$ ]]; then
    SUBSCRIBER_URL="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}/"
    ADMIN_URL="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}${ADMIN_PATH}"
    DIRECT_WEB="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${WEB_PORT}"
    DIRECT_STREAM="${ACCESS_PROTOCOL}://${DOMAIN_NAME}:${STREAM_PORT}"
    RTMP_URL="${RTMP_PROTOCOL}://${DOMAIN_NAME}:${RTMP_PORT}"
else
    # Public domain - use https without ports (Cloudflare)
    SUBSCRIBER_URL="https://${DOMAIN_NAME}/"
    ADMIN_URL="https://${DOMAIN_NAME}${ADMIN_PATH}"
    DIRECT_WEB="https://${PUBLIC_IP}:${WEB_PORT}"
    DIRECT_STREAM="https://${PUBLIC_IP}:${STREAM_PORT}"
    RTMP_URL="rtmp://${PUBLIC_IP}:${RTMP_PORT}"
fi

cat <<COMPLETE_EOF

================================================================
  FOS-Streaming v70 Enhanced Installation Successful!
================================================================
COMPLETE_EOF

# Show network-environment-specific header
case "${NETWORK_ENV:-unknown}" in
    wsl)
        cat <<WSL_EOF

  DEPLOYMENT TYPE: WSL (Windows Subsystem for Linux)

  This is a local development environment. Access FOS-Streaming
  from your Windows browser using the URLs below.

WSL_EOF
        ;;
    container)
        cat <<CONTAINER_EOF

  DEPLOYMENT TYPE: Container (Docker/LXC)

  Make sure the following ports are mapped to the host:
    - ${WEB_PORT}:${WEB_PORT}
    - ${STREAM_PORT}:${STREAM_PORT}
    - ${RTMP_PORT}:${RTMP_PORT}

CONTAINER_EOF
        ;;
    local)
        cat <<LOCAL_EOF

  DEPLOYMENT TYPE: Local Network

  This server is accessible only from your local network.
  Use the local IP or configured .local domain to access.

LOCAL_EOF
        ;;
    nat)
        cat <<NAT_EOF

  DEPLOYMENT TYPE: Behind NAT/Router

  For LAN access, use your local IP: ${LOCAL_IP}
  For external access, configure port forwarding on your router
  to forward ports ${WEB_PORT}, ${STREAM_PORT}, ${RTMP_PORT} to ${LOCAL_IP}

NAT_EOF
        ;;
    public)
        cat <<PUBLIC_EOF

  DEPLOYMENT TYPE: Public Server (Cloudflare Recommended)

  Your server has a public IP and is ready for production.
  For enhanced security, use Cloudflare proxy with SSL/TLS.

PUBLIC_EOF
        ;;
esac

cat <<ACCESS_EOF
================================================================
  Access URLs:
================================================================

  Subscriber Portal: ${SUBSCRIBER_URL}
  Admin Panel:       ${ADMIN_URL}

  Direct Access:
    Web:       ${DIRECT_WEB}
    Streaming: ${DIRECT_STREAM}
    RTMP:      ${RTMP_URL}

  Default Admin Credentials:
    Username:     admin
    Password:     admin

  SECURITY IMPORTANT:
    1. Change the default admin password immediately!
    2. Your admin path: ${ADMIN_PATH}
ACCESS_EOF

# Show additional notes based on deployment type
if [ "${IS_LOCAL_DEPLOYMENT:-0}" = "1" ]; then
    if [[ "$DOMAIN_NAME" =~ \.local$ ]]; then
        cat <<HOSTS_EOF

  NOTE: For .local domain to work, add to your hosts file:
    ${LOCAL_IP} ${DOMAIN_NAME}

  On Linux/macOS: sudo nano /etc/hosts
  On Windows: C:\\Windows\\System32\\drivers\\etc\\hosts
HOSTS_EOF
    fi
else
    cat <<CLOUDFLARE_EOF

  CLOUDFLARE SETUP:
    1. Add DNS record for ${DOMAIN_NAME} → ${PUBLIC_IP}
    2. Enable Cloudflare proxy (orange cloud)
    3. SSL/TLS mode: Full (strict)
    4. All ports are Cloudflare SSL compatible
CLOUDFLARE_EOF
fi

cat <<COMPLETE_EOF

================================================================
  Installed Components:
================================================================

  PHP ${PHP_VERSION} with Laravel components
  Composer ${COMPOSER_VER}
  Node.js ${NODE_VER}
  NPM ${NPM_VER}
  MariaDB ${MARIADB_VERSION}
  Redis (with password authentication)
  FFmpeg ${FFMPEG_VERSION:-latest}
  Self-signed SSL certificates
  High-concurrency kernel optimizations

================================================================
  Installation Details:
================================================================

  Operating System:  ${OS_TYPE^} ${OS_VERSION} (${OS_CODENAME})
  Network Env:       ${NETWORK_ENV:-detected}
  Installation User: ${FOS_USER}
  Project Directory: ${FOS_DIR}
  User Home:         ${FOS_USER_HOME}
  Installation Log:  ${INSTALL_LOG}

================================================================
  Database Credentials:
================================================================

  MariaDB root: Uses unix_socket auth (passwordless via sudo)
                Access: sudo mariadb

  Application User:
    Database: fos_streaming
    DB User:  fos
    DB Pass:  /root/MARIADB_FOS_PASSWORD (also in .env)

================================================================
  Redis Configuration:
================================================================

  Redis Host:     127.0.0.1:6379
  Redis Password: /root/REDIS_PASSWORD (also in .env)
  Used for:       Cache, Sessions, Queue, Broadcast

  Test connection:
    redis-cli -a "\$(cat /root/REDIS_PASSWORD)" ping

================================================================
  Environment Files:
================================================================

  Production .env:    ${FOS_DIR}/.env
  Ports config:       ${FOS_DIR}/config/ports.php

================================================================
  Post-Installation Steps:
================================================================

  1. Access web panel: ${ADMIN_URL}
     (Accept self-signed certificate warning if using HTTPS)

  2. Login with default credentials (admin/admin)

  3. Go to Settings and verify 'Web IP' is correct

  4. Change default admin password immediately!

  5. Configure your first stream

  6. (Production) Setup Let's Encrypt SSL or Cloudflare proxy

================================================================
  Service Management:
================================================================

  # Core Services
  systemctl status fos-nginx
  systemctl status php${PHP_VERSION}-fpm
  systemctl status mariadb
  systemctl status redis-server

  # PM2 Background Workers
  npm run pm2:status     # View worker status
  npm run pm2:logs       # View worker logs
  npm run pm2:restart    # Restart all workers
  npm run pm2:stop       # Stop all workers
  npm run pm2:start      # Start all workers

  # View logs
  journalctl -u fos-nginx -f
  journalctl -u redis-server -f
  tail -f ${FOS_DIR}/logs/*.log
  pm2 logs               # PM2 worker logs

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
log_info "Installation log saved to: ${INSTALL_LOG}"
log_info "Project directory: ${FOS_DIR}"
log_info "Run 'npm run dev' to start the development server"
log_info "Enjoy your FOS-Streaming installation!"
