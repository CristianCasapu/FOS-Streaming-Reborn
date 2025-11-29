#!/usr/bin/env bash
################################################################################
# FOS-Streaming v70 - Unified Installation Script
#
# This is the official installer for FOS-Streaming v70. It combines all
# previous installation scripts into a single, comprehensive installer.
#
# Features:
#   - Remote installation: Run directly from URL, clones repo automatically
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
# Date: 2025-11-27
# Version: 70.0.3
# Supported OS: Debian 10/11/12, Ubuntu 20.04/22.04/24.04 LTS (any minor version)
# Run as: Normal user OR root (script will adapt)
#
# Usage (Remote Installation - Recommended):
#   curl -fsSL https://raw.githubusercontent.com/CristianCasapu/FOS-Streaming-Reborn/develop/install/install.sh | bash
#   curl -fsSL https://raw.githubusercontent.com/CristianCasapu/FOS-Streaming-Reborn/develop/install/install.sh | bash -s -- master
#
# Usage (Local Installation):
#   chmod +x install/install.sh
#   ./install/install.sh
#
# Arguments:
#   $1 - Branch to install (default: develop). Options: develop, master
################################################################################

# Don't exit on error - we handle errors gracefully
set +e

# =============================================================================
# Early Locale Fix - Prevent perl/apt warnings on minimal systems
# =============================================================================
# Set safe locale defaults immediately to avoid perl warnings during bootstrap
# This is a temporary fix; proper locale generation happens in bootstrap phase
if [ -z "$LC_ALL" ] || ! locale 2>/dev/null | grep -q "LC_ALL"; then
    export LANG="${LANG:-C.UTF-8}"
    export LC_ALL="${LC_ALL:-C.UTF-8}"
    export LANGUAGE="${LANGUAGE:-en_US:en}"
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# =============================================================================
# Remote Installation Handler
# =============================================================================
# Detect if running from curl/wget pipe (no BASH_SOURCE means piped)
# or if FOS_DIR doesn't exist (script downloaded but repo not cloned)

REPO_URL="https://github.com/CristianCasapu/FOS-Streaming-Reborn.git"
INSTALL_BRANCH="${1:-develop}"  # Default to develop branch
PROJECT_NAME="FOS-Streaming"

# Function to handle remote installation
handle_remote_install() {
    echo -e "${CYAN}================================================================${NC}"
    echo -e "${CYAN}  FOS-Streaming Remote Installation${NC}"
    echo -e "${CYAN}================================================================${NC}"
    echo ""
    echo -e "  Branch: ${GREEN}${INSTALL_BRANCH}${NC}"
    echo ""

    # Determine target user and home directory
    local target_user=""
    local target_home=""
    local need_user_creation=false

    if [ "$EUID" -eq 0 ]; then
        # Running as root
        if [ -n "$SUDO_USER" ] && [ "$SUDO_USER" != "root" ]; then
            target_user="$SUDO_USER"
            target_home=$(eval echo ~$SUDO_USER)
        else
            # Check for existing non-root user
            target_user=$(getent passwd | awk -F: '$3 >= 1000 && $3 < 65534 && $7 !~ /nologin|false/ {print $1; exit}')
            if [ -n "$target_user" ]; then
                target_home=$(eval echo ~$target_user)
            else
                # No user exists - will create one during bootstrap
                # Clone to /opt temporarily, installer will move it later
                need_user_creation=true
                target_home="/opt"
                echo -e "${YELLOW}[INFO]${NC} No regular user found. User will be created during installation."
            fi
        fi
    else
        target_user="$(whoami)"
        target_home="$HOME"
    fi

    local fos_dir="${target_home}/${PROJECT_NAME}"

    echo -e "  Target directory: ${CYAN}${fos_dir}${NC}"
    if [ "$need_user_creation" = true ]; then
        echo -e "  ${YELLOW}(Will be moved to user's home after user creation)${NC}"
    fi
    echo ""

    # Check if git is available, install if not
    if ! command -v git &>/dev/null; then
        echo -e "${YELLOW}[INFO]${NC} Git not found, installing..."

        # Update package lists
        if [ "$EUID" -eq 0 ]; then
            apt-get update -y >/dev/null 2>&1 || true
            apt-get install -y git >/dev/null 2>&1 || {
                echo -e "${RED}[ERROR]${NC} Failed to install git"
                exit 1
            }
        else
            sudo apt-get update -y >/dev/null 2>&1 || true
            sudo apt-get install -y git >/dev/null 2>&1 || {
                echo -e "${RED}[ERROR]${NC} Failed to install git"
                exit 1
            }
        fi
        echo -e "${GREEN}[SUCCESS]${NC} Git installed"
    fi

    # Check if directory already exists
    if [ -d "$fos_dir" ]; then
        echo -e "${YELLOW}[WARN]${NC} Directory ${fos_dir} already exists"
        echo -e "  Checking if it's a valid FOS-Streaming installation..."

        if [ -f "${fos_dir}/install/install.sh" ]; then
            echo -e "${GREEN}[INFO]${NC} Found existing installation, updating..."
            cd "$fos_dir"

            # Fetch and checkout the requested branch
            git fetch origin >/dev/null 2>&1 || true
            git checkout "$INSTALL_BRANCH" >/dev/null 2>&1 || {
                echo -e "${RED}[ERROR]${NC} Failed to checkout branch ${INSTALL_BRANCH}"
                exit 1
            }
            git pull origin "$INSTALL_BRANCH" >/dev/null 2>&1 || true

            echo -e "${GREEN}[SUCCESS]${NC} Repository updated"
        else
            echo -e "${RED}[ERROR]${NC} Directory exists but is not a valid FOS-Streaming installation"
            echo -e "  Please remove or rename: ${fos_dir}"
            exit 1
        fi
    else
        # Clone the repository
        echo -e "${GREEN}[INFO]${NC} Cloning repository..."

        # Create parent directory if needed and set permissions
        if [ "$EUID" -eq 0 ] && [ -n "$target_user" ]; then
            # Running as root, clone then chown
            git clone --branch "$INSTALL_BRANCH" --single-branch "$REPO_URL" "$fos_dir" || {
                echo -e "${RED}[ERROR]${NC} Failed to clone repository"
                exit 1
            }
            chown -R "${target_user}:${target_user}" "$fos_dir"
        else
            git clone --branch "$INSTALL_BRANCH" --single-branch "$REPO_URL" "$fos_dir" || {
                echo -e "${RED}[ERROR]${NC} Failed to clone repository"
                exit 1
            }
        fi

        echo -e "${GREEN}[SUCCESS]${NC} Repository cloned to ${fos_dir}"
    fi

    # Now execute the local install script
    echo ""
    echo -e "${GREEN}[INFO]${NC} Starting installation from cloned repository..."
    echo ""

    # Change to the project directory and run the local installer
    cd "$fos_dir"

    # Execute the local install script
    if [ "$EUID" -eq 0 ]; then
        # Pass the branch as argument and mark as local install
        exec bash "${fos_dir}/install/install.sh" --local "$INSTALL_BRANCH"
    else
        exec bash "${fos_dir}/install/install.sh" --local "$INSTALL_BRANCH"
    fi
}

# Check if this is a remote installation (piped from curl/wget)
# BASH_SOURCE is empty or different when piped
IS_REMOTE_INSTALL=false

if [ -z "${BASH_SOURCE[0]}" ] || [ "${BASH_SOURCE[0]}" = "bash" ]; then
    # Script is being piped (curl | bash)
    IS_REMOTE_INSTALL=true
elif [ ! -f "${BASH_SOURCE[0]}" ]; then
    # Script file doesn't exist locally
    IS_REMOTE_INSTALL=true
else
    # Check if we're in a valid FOS directory structure
    SCRIPT_DIR_CHECK="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd)"
    FOS_DIR_CHECK="$(dirname "$SCRIPT_DIR_CHECK" 2>/dev/null)"

    if [ ! -f "${FOS_DIR_CHECK}/config.php" ] && [ ! -f "${FOS_DIR_CHECK}/composer.json" ]; then
        # Not in a valid FOS directory, treat as remote install
        IS_REMOTE_INSTALL=true
    fi
fi

# Handle --local flag (set by remote installer after cloning)
if [ "$1" = "--local" ]; then
    IS_REMOTE_INSTALL=false
    INSTALL_BRANCH="${2:-develop}"
    shift 2 2>/dev/null || shift 1 2>/dev/null || true
fi

# If remote install, clone repo and re-execute
if [ "$IS_REMOTE_INSTALL" = true ]; then
    handle_remote_install
    exit 0
fi

# =============================================================================
# Dynamic Configuration - Detect from environment
# =============================================================================
PHP_VERSION="8.4"
MARIADB_VERSION="12.2.1"
NODE_VERSION="24"  # LTS version

# Directory hierarchy: HOME_DIR > FOS_DIR > SCRIPT_DIR
# These are populated during bootstrap
USER=""
HOME_DIR=""
# Retrieve created user password from env (passed when switching from root to user)
CREATED_USER_PASSWORD="${FOS_CREATED_USER_PASSWORD:-}"

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

# Generate a strong random password
generate_password() {
    local length="${1:-24}"
    # Use /dev/urandom for cryptographically secure random data
    # Include uppercase, lowercase, digits, and safe special chars
    tr -dc 'A-Za-z0-9!@#$%^&*()_+=' < /dev/urandom | head -c "$length"
}

# Create a new system user with generated password
# Returns: 0 on success, 1 on failure
# Sets: USER, HOME_DIR, CREATED_USER_PASSWORD
create_system_user() {
    local username="$1"

    # Validate username
    if [ -z "$username" ]; then
        log_error "Username cannot be empty"
        return 1
    fi

    # Check if username is valid (alphanumeric, lowercase, starts with letter)
    if ! echo "$username" | grep -qE '^[a-z][a-z0-9_-]{0,31}$'; then
        log_error "Invalid username. Must start with a letter, contain only lowercase letters, numbers, underscores, and hyphens (max 32 chars)"
        return 1
    fi

    # Check if user already exists
    if id "$username" &>/dev/null; then
        log_error "User '$username' already exists"
        return 1
    fi

    # Generate strong password
    CREATED_USER_PASSWORD=$(generate_password 24)

    log_bootstrap "Creating user '$username'..."

    # Create user with home directory
    useradd -m -s /bin/bash "$username" || {
        log_error "Failed to create user '$username'"
        return 1
    }

    # Set password
    echo "${username}:${CREATED_USER_PASSWORD}" | chpasswd || {
        log_error "Failed to set password for '$username'"
        userdel -r "$username" 2>/dev/null
        return 1
    }

    # Set global variables
    USER="$username"
    HOME_DIR="/home/$username"

    log_success "User '$username' created with home directory: $HOME_DIR"

    # Save password securely for later display (root-only readable)
    echo "$CREATED_USER_PASSWORD" > "/root/.fos_user_password_${username}"
    chmod 600 "/root/.fos_user_password_${username}"

    return 0
}

# Prompt user for username input
prompt_for_username() {
    local default_user="fosadmin"
    local username=""

    echo ""
    echo -e "${CYAN}================================================================${NC}"
    echo -e "${CYAN}  User Creation Required${NC}"
    echo -e "${CYAN}================================================================${NC}"
    echo ""
    echo "No regular user found on this system."
    echo "FOS-Streaming needs a non-root user to run securely."
    echo ""
    echo -e "Enter username for FOS-Streaming (default: ${GREEN}${default_user}${NC}): "
    read -r username

    # Use default if empty
    if [ -z "$username" ]; then
        username="$default_user"
    fi

    echo "$username"
}

# Generate installation credentials report as markdown file
generate_credentials_report() {
    local report_file="${SCRIPT_DIR}/CREDENTIALS.md"
    local db_password=""
    local redis_password=""
    local install_date=$(date '+%Y-%m-%d %H:%M:%S')

    # Read passwords from secure files
    if [ -f /root/MARIADB_FOS_PASSWORD ]; then
        db_password=$(sudo cat /root/MARIADB_FOS_PASSWORD 2>/dev/null || echo "See /root/MARIADB_FOS_PASSWORD")
    else
        db_password="${SQL_PASSWD:-Not available}"
    fi

    if [ -f /root/REDIS_PASSWORD ]; then
        redis_password=$(sudo cat /root/REDIS_PASSWORD 2>/dev/null || echo "See /root/REDIS_PASSWORD")
    else
        redis_password="${REDIS_PASSWORD:-Not available}"
    fi

    cat > "$report_file" <<CREDENTIALS_EOF
# FOS-Streaming Installation Credentials

> **⚠️ SECURITY WARNING**: This file contains sensitive credentials.
> Store securely and delete after transferring to a password manager.
> Generated: ${install_date}

---

## Installation Details

| Setting | Value |
|---------|-------|
| Operating System | ${OS_TYPE^} ${OS_VERSION} (${OS_CODENAME}) |
| Installation User | ${USER} |
| User Home | ${HOME_DIR} |
| Project Directory | ${FOS_DIR} |
| Network Environment | ${NETWORK_ENV:-detected} |
| Public IP | ${PUBLIC_IP:-N/A} |
| Domain | ${DOMAIN_NAME:-N/A} |

---

## Web Panel Access

| Setting | Value |
|---------|-------|
| URL | ${ADMIN_URL:-https://${DOMAIN_NAME}:${WEB_PORT}/admin} |
| Default Username | \`admin\` |
| Default Password | \`admin\` |

> **🔐 Change the default admin password immediately after first login!**

---

## System User Credentials
CREDENTIALS_EOF

    # Add system user section if a new user was created
    if [ -n "$CREATED_USER_PASSWORD" ]; then
        cat >> "$report_file" <<SYSUSER_EOF

A new system user was created during installation:

| Setting | Value |
|---------|-------|
| Username | \`${USER}\` |
| Password | \`${CREATED_USER_PASSWORD}\` |
| Home Directory | ${HOME_DIR} |

**SSH Access:**
\`\`\`bash
ssh ${USER}@${PUBLIC_IP:-<server-ip>}
\`\`\`

SYSUSER_EOF
    else
        cat >> "$report_file" <<EXISTINGUSER_EOF

Using existing system user: \`${USER}\`

EXISTINGUSER_EOF
    fi

    cat >> "$report_file" <<DB_EOF
---

## Database Credentials (MariaDB)

| Setting | Value |
|---------|-------|
| Host | \`localhost\` |
| Database | \`fos_streaming\` |
| Username | \`fos\` |
| Password | \`${db_password}\` |

**Root Access** (uses unix_socket authentication):
\`\`\`bash
sudo mariadb
\`\`\`

**Application User Access:**
\`\`\`bash
mariadb -u fos -p'${db_password}' fos_streaming
\`\`\`

---

## Redis Credentials

| Setting | Value |
|---------|-------|
| Host | \`127.0.0.1\` |
| Port | \`6379\` |
| Password | \`${redis_password}\` |

**Test Connection:**
\`\`\`bash
redis-cli -a '${redis_password}' ping
\`\`\`

---

## Service Ports

| Service | Port |
|---------|------|
| Web Panel (HTTPS) | ${WEB_PORT} |
| Streaming | ${STREAM_PORT} |
| RTMP | ${RTMP_PORT} |

---

## Configuration Files

| File | Purpose |
|------|---------|
| \`${FOS_DIR}/.env\` | Environment configuration (contains all credentials) |
| \`${FOS_DIR}/config/ports.php\` | Port configuration |
| \`/root/MARIADB_FOS_PASSWORD\` | Database password backup |
| \`/root/REDIS_PASSWORD\` | Redis password backup |

---

## Service Management

\`\`\`bash
# Core Services
systemctl status fos-nginx
systemctl status php${PHP_VERSION}-fpm
systemctl status mariadb
systemctl status redis-server

# PM2 Background Workers
npm run pm2:status
npm run pm2:logs
npm run pm2:restart
\`\`\`

---

## Post-Installation Checklist

- [ ] Access web panel and verify it loads
- [ ] Change default admin password (admin/admin)
- [ ] Verify 'Web IP' setting is correct
- [ ] Configure your first stream
- [ ] Set up SSL certificate (Let's Encrypt or Cloudflare)
- [ ] **Delete this file after saving credentials securely**

---

*Generated by FOS-Streaming Installer v70*
DB_EOF

    # Set secure permissions (readable only by owner)
    chmod 600 "$report_file"
    chown ${USER}:${USER} "$report_file" 2>/dev/null || true

    log_success "Credentials report saved to: ${report_file}"
    echo "$report_file"
}

# Bootstrap: Ensure essential tools are available
run_bootstrap() {
    log_step "Bootstrap Phase: Ensuring Essential Prerequisites"

    # Initialize logging
    init_log
    log_bootstrap "Starting bootstrap phase..."

    # -------------------------------------------------------------------------
    # Step 0: Fix locale settings (common issue on fresh minimal installs)
    # -------------------------------------------------------------------------
    log_bootstrap "Checking locale settings..."

    # Check if locale is broken (common on minimal/container installs)
    if ! locale 2>/dev/null | grep -q "LC_ALL" || locale 2>&1 | grep -qi "cannot set"; then
        log_bootstrap "Fixing locale settings..."

        # Set temporary locale to avoid perl warnings during bootstrap
        export LANG="C.UTF-8"
        export LC_ALL="C.UTF-8"
        export LANGUAGE="en_US:en"

        # Install locales package if we can
        if [ "$EUID" -eq 0 ]; then
            # Try to install locales package
            apt-get update -y >/dev/null 2>&1 || true
            apt-get install -y locales >/dev/null 2>&1 || true

            # Generate en_US.UTF-8 locale if locales is installed
            if command -v locale-gen &>/dev/null; then
                # Ensure en_US.UTF-8 is in locale.gen
                if [ -f /etc/locale.gen ]; then
                    sed -i 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen 2>/dev/null || true
                fi
                locale-gen en_US.UTF-8 >/dev/null 2>&1 || true
                update-locale LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8 >/dev/null 2>&1 || true
            fi

            # Also try dpkg-reconfigure for Debian systems
            if command -v dpkg-reconfigure &>/dev/null; then
                echo "en_US.UTF-8 UTF-8" > /etc/locale.gen 2>/dev/null || true
                dpkg-reconfigure --frontend=noninteractive locales >/dev/null 2>&1 || true
            fi
        else
            # Not root, just set environment variables
            log_bootstrap "Not root, setting locale environment variables only"
        fi

        # Set proper locale for this session
        export LANG="en_US.UTF-8"
        export LC_ALL="en_US.UTF-8"
        export LANGUAGE="en_US:en"

        log_success "Locale configured"
    else
        log_bootstrap "Locale settings OK"
    fi

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
    # Step 3: Determine or create the installation user
    # -------------------------------------------------------------------------
    if [ "$EUID" -eq 0 ]; then
        # Running as root - need to determine target user
        if [ -n "$SUDO_USER" ] && [ "$SUDO_USER" != "root" ]; then
            USER="$SUDO_USER"
            HOME_DIR="$(eval echo ~$USER)"
        else
            # Check if there's a non-root user in the system
            local potential_user=$(getent passwd | awk -F: '$3 >= 1000 && $3 < 65534 && $7 !~ /nologin|false/ {print $1; exit}')
            if [ -n "$potential_user" ]; then
                log_warn "Running as root. Will set up for user: $potential_user"
                USER="$potential_user"
                HOME_DIR="$(eval echo ~$USER)"
            else
                # No regular user exists - create one
                log_bootstrap "No regular user found on system. Creating one..."

                # Prompt for username
                local new_username
                new_username=$(prompt_for_username)

                # Create the user
                if create_system_user "$new_username"; then
                    log_success "User '$USER' created successfully"

                    # Check if project was cloned to /opt (remote install with no user)
                    # and move it to the new user's home directory
                    if [ -d "/opt/${PROJECT_NAME}" ] && [ ! -d "${HOME_DIR}/${PROJECT_NAME}" ]; then
                        log_bootstrap "Moving project from /opt to user's home directory..."
                        mv "/opt/${PROJECT_NAME}" "${HOME_DIR}/${PROJECT_NAME}"
                        chown -R "${USER}:${USER}" "${HOME_DIR}/${PROJECT_NAME}"

                        # Update FOS_DIR and SCRIPT_DIR to new location
                        FOS_DIR="${HOME_DIR}/${PROJECT_NAME}"
                        SCRIPT_DIR="${FOS_DIR}/install"
                        INSTALL_LOG="${SCRIPT_DIR}/install.log"
                        PORTS_CONFIG="${FOS_DIR}/config/ports.php"
                        CERTS_DIR="${FOS_DIR}/fospackv69/fos/nginx/conf/certs"
                        STATE_FILE="${FOS_DIR}/.fos-install-state"
                        STATE_LOCK="${FOS_DIR}/.fos-install-lock"

                        log_success "Project moved to ${FOS_DIR}"
                    fi

                    echo ""
                    echo -e "${GREEN}================================================================${NC}"
                    echo -e "${GREEN}  User Created Successfully${NC}"
                    echo -e "${GREEN}================================================================${NC}"
                    echo ""
                    echo -e "  Username: ${CYAN}${USER}${NC}"
                    echo -e "  Password: ${YELLOW}(will be shown at the end of installation)${NC}"
                    echo -e "  Home:     ${CYAN}${HOME_DIR}${NC}"
                    echo -e "  Project:  ${CYAN}${FOS_DIR}${NC}"
                    echo ""
                    echo -e "${YELLOW}  IMPORTANT: Save the password when shown at the end!${NC}"
                    echo ""
                    echo -e "${GREEN}================================================================${NC}"
                    echo ""
                    sleep 3
                else
                    log_error "Failed to create user. Cannot continue."
                    exit 1
                fi
            fi
        fi
    else
        USER="$(whoami)"
        HOME_DIR="$(eval echo ~$USER)"
    fi

    log_bootstrap "Installation user: $USER (home: $HOME_DIR)"

    # -------------------------------------------------------------------------
    # Step 4: Set up sudoers for the installation user (EARLY - before anything else)
    # -------------------------------------------------------------------------
    log_bootstrap "Configuring sudo access for ${USER}..."

    local sudoers_file="/etc/sudoers.d/${USER}"
    local sudoers_content="${USER} ALL=(ALL) NOPASSWD: ALL"

    if [ "$EUID" -eq 0 ]; then
        # Running as root
        if [ ! -f "$sudoers_file" ] || ! grep -q "NOPASSWD: ALL" "$sudoers_file" 2>/dev/null; then
            echo "$sudoers_content" > "$sudoers_file"
            chmod 0440 "$sudoers_file"
            log_success "${USER} added to sudoers with NOPASSWD"
        else
            log_bootstrap "${USER} already has sudo NOPASSWD access"
        fi
    else
        # Running as user - check if we already have sudo access
        if sudo -n true 2>/dev/null; then
            # We have sudo, ensure the file exists
            if [ ! -f "$sudoers_file" ]; then
                echo "$sudoers_content" | sudo tee "$sudoers_file" > /dev/null
                sudo chmod 0440 "$sudoers_file"
                log_success "${USER} added to sudoers with NOPASSWD"
            else
                log_bootstrap "${USER} already has sudo configuration"
            fi
        else
            log_error "Cannot configure sudoers - no sudo access"
            log_error "Please run: su -c 'echo \"${USER} ALL=(ALL) NOPASSWD: ALL\" > /etc/sudoers.d/${USER}'"
            exit 1
        fi
    fi

    # -------------------------------------------------------------------------
    # Step 5: Install essential bootstrap packages
    # -------------------------------------------------------------------------
    log_bootstrap "Installing essential packages..."

    # Note: whoami/whereami are commands from coreutils, not separate packages
    local essential_packages=(
        "curl"
        "wget"
        "gnupg"
        "git"
        "zip"
        "unzip"
        "tar"
        "gzip"
        "bzip2"
        "coreutils"
        "lsb-release"
        "ca-certificates"
        "apt-transport-https"
    )

    # Optional packages that may not exist on all systems (e.g., minimal installs)
    local optional_packages=(
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

    # Install optional packages silently
    for pkg in "${optional_packages[@]}"; do
        if ! dpkg -l "$pkg" 2>/dev/null | grep -q "^ii"; then
            log_bootstrap "Installing optional: $pkg..."
            bootstrap_install_package "$pkg" 2>/dev/null || true
        fi
    done

    # -------------------------------------------------------------------------
    # Step 6: If running as root, switch to target user for remaining installation
    # -------------------------------------------------------------------------
    if [ "$EUID" -eq 0 ] && [ "$USER" != "root" ]; then
        log_bootstrap "Switching to user ${USER} for remaining installation..."

        # Re-execute the script as the target user
        # Pass the created user password via environment variable if set
        cd "${FOS_DIR}"
        if [ -n "$CREATED_USER_PASSWORD" ]; then
            exec sudo -u "$USER" -H FOS_CREATED_USER_PASSWORD="$CREATED_USER_PASSWORD" bash "${SCRIPT_DIR}/install.sh" "$@"
        else
            exec sudo -u "$USER" -H bash "${SCRIPT_DIR}/install.sh" "$@"
        fi
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
    local attempts=0
    local max_attempts=3

    # Check if running non-interactively (stdin not a terminal)
    if [ ! -t 0 ]; then
        if [ -n "$default" ]; then
            log_info "Non-interactive mode: using default '${default}' for '${prompt}'"
            value="$default"
            # Validate default
            if [ -n "$validation" ] && [[ ! "$value" =~ $validation ]]; then
                log_warn "Default value '$value' does not match validation pattern"
            fi
            eval "$var_name=\"\$value\""
            return 0
        else
            log_error "Non-interactive mode: no default for required field '${prompt}'"
            log_error "Please run the installer interactively or set environment variables"
            return 1
        fi
    fi

    while true; do
        if [ -n "$default" ]; then
            read -p "${prompt} [${default}]: " value
            value="${value:-$default}"
        else
            read -p "${prompt}: " value
        fi

        # Check if empty (and no default)
        if [ -z "$value" ] && [ -z "$default" ]; then
            ((attempts++))
            if [ $attempts -ge $max_attempts ]; then
                log_error "Too many empty attempts. Aborting."
                return 1
            fi
            log_error "This field is required. Please enter a value."
            continue
        fi

        # Validate against regex if provided
        if [ -n "$validation" ]; then
            if [[ ! "$value" =~ $validation ]]; then
                ((attempts++))
                if [ $attempts -ge $max_attempts ]; then
                    log_error "Too many invalid attempts. Aborting."
                    return 1
                fi
                log_error "$error_msg"
                continue
            fi
        fi

        # Set the variable
        eval "$var_name=\"\$value\""
        break
    done
}

# Read yes/no with default
# Usage: read_confirm "prompt" "default_yes_or_no"
read_confirm() {
    local prompt="$1"
    local default="$2"
    local response
    local attempts=0
    local max_attempts=3

    # Check if running non-interactively (stdin not a terminal)
    if [ ! -t 0 ]; then
        if [ "$default" = "y" ]; then
            log_info "Non-interactive mode: using 'yes' for '${prompt}'"
            return 0
        else
            log_info "Non-interactive mode: using 'no' for '${prompt}'"
            return 1
        fi
    fi

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
            *)
                ((attempts++))
                if [ $attempts -ge $max_attempts ]; then
                    log_error "Too many invalid attempts. Using default."
                    if [ "$default" = "y" ]; then
                        return 0
                    else
                        return 1
                    fi
                fi
                log_error "Please answer 'y' or 'n'"
                ;;
        esac
    done
}

# Read password with confirmation
# Usage: read_password "prompt" "variable_name"
read_password() {
    local prompt="$1"
    local var_name="$2"
    local pass1 pass2
    local attempts=0
    local max_attempts=3

    # Check if running non-interactively (stdin not a terminal)
    if [ ! -t 0 ]; then
        log_info "Non-interactive mode: generating random password for '${prompt}'"
        local generated_pass
        generated_pass=$(generate_password 24)
        eval "$var_name=\"\$generated_pass\""
        return 0
    fi

    while true; do
        read -s -p "${prompt}: " pass1
        echo

        if [ -z "$pass1" ]; then
            ((attempts++))
            if [ $attempts -ge $max_attempts ]; then
                log_error "Too many empty attempts. Generating random password."
                pass1=$(generate_password 24)
                eval "$var_name=\"\$pass1\""
                return 0
            fi
            log_error "Password cannot be empty. Please try again."
            continue
        fi

        if [ ${#pass1} -lt 8 ]; then
            ((attempts++))
            if [ $attempts -ge $max_attempts ]; then
                log_error "Too many invalid attempts. Generating random password."
                pass1=$(generate_password 24)
                eval "$var_name=\"\$pass1\""
                return 0
            fi
            log_error "Password must be at least 8 characters. Please try again."
            continue
        fi

        read -s -p "Confirm password: " pass2
        echo

        if [ "$pass1" != "$pass2" ]; then
            ((attempts++))
            if [ $attempts -ge $max_attempts ]; then
                log_error "Too many mismatches. Generating random password."
                pass1=$(generate_password 24)
                eval "$var_name=\"\$pass1\""
                return 0
            fi
            log_error "Passwords do not match. Please try again."
            continue
        fi

        eval "$var_name=\"\$pass1\""
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

# Check if systemd is available and running
has_systemd() {
    # Check if systemd is PID 1
    if [ -d /run/systemd/system ]; then
        return 0
    fi
    # Alternative check
    if command -v systemctl &>/dev/null && systemctl is-system-running &>/dev/null; then
        return 0
    fi
    return 1
}

# =============================================================================
# Service Management Functions (handles systemd and non-systemd environments)
# =============================================================================

# Restart a service (works in both systemd and non-systemd environments)
# Usage: service_restart "service_name"
service_restart() {
    local service="$1"

    if has_systemd; then
        sudo systemctl restart "$service" 2>/dev/null
        return $?
    else
        # Non-systemd environment (WSL, container without systemd)
        log_info "Non-systemd environment: attempting direct service restart for $service"

        # Try service command first
        if sudo service "$service" restart 2>/dev/null; then
            return 0
        fi

        # Try init.d script
        if [ -x "/etc/init.d/$service" ]; then
            sudo /etc/init.d/"$service" restart 2>/dev/null
            return $?
        fi

        # For PHP-FPM, try direct binary control
        if [[ "$service" =~ php.*fpm ]]; then
            local php_version="${service//[^0-9.]/}"
            php_version="${php_version:0:3}"  # e.g., "8.4"

            # Kill existing and restart
            sudo pkill -9 -f "php-fpm.*${php_version}" 2>/dev/null || true
            sleep 1

            # Try to start PHP-FPM directly
            local fpm_bin="/usr/sbin/php-fpm${php_version}"
            if [ -x "$fpm_bin" ]; then
                sudo "$fpm_bin" --daemonize 2>/dev/null
                return $?
            fi
        fi

        # For nginx, try direct control
        if [[ "$service" =~ nginx ]]; then
            sudo pkill -9 nginx 2>/dev/null || true
            sleep 1
            if [ -x "${FOS_DIR}/fospackv69/fos/nginx/sbin/nginx_fos" ]; then
                sudo "${FOS_DIR}/fospackv69/fos/nginx/sbin/nginx_fos" 2>/dev/null
                return $?
            elif [ -x "/usr/sbin/nginx" ]; then
                sudo /usr/sbin/nginx 2>/dev/null
                return $?
            fi
        fi

        log_warn "Could not restart $service in non-systemd environment"
        return 0  # Don't fail - service may work differently in this environment
    fi
}

# Enable a service to start on boot
# Usage: service_enable "service_name"
service_enable() {
    local service="$1"

    if has_systemd; then
        sudo systemctl enable "$service" 2>/dev/null
        return $?
    else
        log_info "Non-systemd environment: skipping enable for $service (no systemd)"
        # In non-systemd environments, services are managed differently
        # For WSL, services typically need to be started manually or via .bashrc/.profile
        return 0
    fi
}

# Stop a service
# Usage: service_stop "service_name"
service_stop() {
    local service="$1"

    if has_systemd; then
        sudo systemctl stop "$service" 2>/dev/null
        return $?
    else
        if sudo service "$service" stop 2>/dev/null; then
            return 0
        fi
        if [ -x "/etc/init.d/$service" ]; then
            sudo /etc/init.d/"$service" stop 2>/dev/null
            return $?
        fi
        return 0
    fi
}

# Start a service
# Usage: service_start "service_name"
service_start() {
    local service="$1"

    if has_systemd; then
        sudo systemctl start "$service" 2>/dev/null
        return $?
    else
        log_info "Non-systemd environment: attempting to start $service"
        if sudo service "$service" start 2>/dev/null; then
            return 0
        fi
        if [ -x "/etc/init.d/$service" ]; then
            sudo /etc/init.d/"$service" start 2>/dev/null
            return $?
        fi
        return 0
    fi
}

# Check service status
# Usage: service_status "service_name"
service_status() {
    local service="$1"

    if has_systemd; then
        sudo systemctl status "$service" 2>/dev/null
        return $?
    else
        if sudo service "$service" status 2>/dev/null; then
            return 0
        fi
        # Check if process is running
        if pgrep -f "$service" &>/dev/null; then
            log_info "$service is running (detected via process)"
            return 0
        fi
        return 1
    fi
}

# Reload systemd daemon (only if systemd is available)
# Usage: systemd_reload
systemd_reload() {
    if has_systemd; then
        sudo systemctl daemon-reload 2>/dev/null
    fi
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
        else
            log_info "contrib and non-free repositories already configured"
        fi
    fi

    # PHP Repository (Sury)
    log_info "Adding PHP repository (Sury)..."
    if [ -f /etc/apt/sources.list.d/php-sury.list ] || [ -f /etc/apt/sources.list.d/php.list ]; then
        log_info "PHP Sury repository already exists, skipping..."
    else
        # Determine supported codename for Sury PHP repository
        # Supported: bookworm, bullseye, buster (Debian 10+)
        local php_codename="${OS_CODENAME}"
        case "${OS_CODENAME}" in
            bookworm|bullseye|buster)
                php_codename="${OS_CODENAME}"
                ;;
            trixie|forky|sid|*)
                # Newer/unstable versions - fall back to bookworm (latest stable)
                log_warn "Debian ${OS_CODENAME} may not be supported by Sury PHP repository"
                log_info "Falling back to 'bookworm' (Debian 12) repository"
                php_codename="bookworm"
                ;;
        esac

        sudo curl -sSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php-sury.gpg 2>/dev/null || {
            # Alternative method using apt-key (deprecated but fallback)
            log_warn "GPG key download failed, trying apt-key method..."
            curl -sSL https://packages.sury.org/php/apt.gpg | sudo apt-key add - 2>/dev/null || {
                log_error "Failed to add PHP Sury GPG key"
            }
        }
        echo "deb https://packages.sury.org/php/ ${php_codename} main" | sudo tee /etc/apt/sources.list.d/php-sury.list
        log_info "PHP Sury repository added for ${php_codename}"
    fi

    # MariaDB Repository
    log_info "Adding MariaDB repository..."
    if [ -f /etc/apt/sources.list.d/mariadb.list ]; then
        log_info "MariaDB repository already exists, skipping..."
    else
        sudo curl -o /etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc 'https://mariadb.org/mariadb_release_signing_key.asc' 2>/dev/null || {
            log_warn "Failed to download MariaDB GPG key"
        }

        # Debian codenames supported by MariaDB (ordered by release year, newest first)
        # sid=unstable, bookworm=2023 (Debian 12), bullseye=2021 (Debian 11), buster=2019 (Debian 10)
        # trixie=2025 (Debian 13) - not yet in MariaDB repo, use sid as fallback
        local mariadb_codename="${OS_CODENAME}"

        case "${OS_CODENAME}" in
            sid|bookworm|bullseye|buster)
                # Supported directly
                mariadb_codename="${OS_CODENAME}"
                ;;
            trixie|forky|*)
                # Future/unsupported versions - use sid (unstable, always latest)
                log_warn "Debian ${OS_CODENAME} not yet supported by MariaDB repository"
                log_info "Using sid (unstable) repository as fallback"
                mariadb_codename="sid"
                ;;
        esac

        echo "deb [arch=amd64,arm64 signed-by=/etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/debian ${mariadb_codename} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
        log_info "MariaDB repository added for ${mariadb_codename}"
    fi

    # FFmpeg Repository (deb-multimedia.org - optimized builds with full codec support)
    log_info "Adding FFmpeg repository (deb-multimedia)..."
    if [ -f /etc/apt/sources.list.d/deb-multimedia.list ] || [ -f /etc/apt/sources.list.d/ffmpeg.list ]; then
        log_info "FFmpeg/deb-multimedia repository already exists, skipping..."
    else
        # Determine supported codename for deb-multimedia repository
        # Supported: bookworm, bullseye, buster, sid
        local ffmpeg_codename="${OS_CODENAME}"
        case "${OS_CODENAME}" in
            sid|bookworm|bullseye|buster)
                ffmpeg_codename="${OS_CODENAME}"
                ;;
            trixie|forky|*)
                # Newer/unstable versions - fall back to bookworm (latest stable)
                log_warn "Debian ${OS_CODENAME} may not be supported by deb-multimedia repository"
                log_info "Falling back to 'bookworm' (Debian 12) repository"
                ffmpeg_codename="bookworm"
                ;;
        esac

        # Add deb-multimedia keyring
        log_info "Adding deb-multimedia GPG key..."
        sudo curl -fsSL https://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2016.8.1_all.deb -o /tmp/deb-multimedia-keyring.deb 2>/dev/null && \
            sudo dpkg -i /tmp/deb-multimedia-keyring.deb 2>/dev/null && \
            sudo rm -f /tmp/deb-multimedia-keyring.deb || {
            # Fallback: manually add key
            log_warn "Keyring package failed, trying manual key import..."
            sudo mkdir -p /etc/apt/keyrings
            sudo curl -fsSL "https://www.deb-multimedia.org/pool/main/d/deb-multimedia-keyring/deb-multimedia-keyring_2016.8.1_all.deb" 2>/dev/null | \
                sudo dpkg-deb --fsys-tarfile /dev/stdin | sudo tar -xOf - ./usr/share/keyrings/deb-multimedia-keyring.gpg > /etc/apt/keyrings/deb-multimedia.gpg 2>/dev/null || {
                log_warn "Could not add deb-multimedia key, FFmpeg will be installed from default repos"
            }
        }

        # Add repository
        if [ -f /etc/apt/keyrings/deb-multimedia.gpg ]; then
            echo "deb [signed-by=/etc/apt/keyrings/deb-multimedia.gpg] https://www.deb-multimedia.org ${ffmpeg_codename} main non-free" | sudo tee /etc/apt/sources.list.d/deb-multimedia.list
        else
            echo "deb https://www.deb-multimedia.org ${ffmpeg_codename} main non-free" | sudo tee /etc/apt/sources.list.d/deb-multimedia.list
        fi
        log_info "FFmpeg deb-multimedia repository added for ${ffmpeg_codename}"
    fi

    # Update package lists
    log_info "Updating package lists..."
    sudo apt-get update -y 2>&1 | tee -a "$INSTALL_LOG"
}

setup_ubuntu_repositories() {
    log_info "Setting up Ubuntu repositories for ${OS_CODENAME}..."

    # Ensure universe and multiverse are enabled
    log_info "Checking universe and multiverse repositories..."
    if grep -rq "universe" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null; then
        log_info "Universe repository already enabled"
    else
        log_info "Enabling universe repository..."
        sudo add-apt-repository -y universe 2>/dev/null || true
    fi

    if grep -rq "multiverse" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null; then
        log_info "Multiverse repository already enabled"
    else
        log_info "Enabling multiverse repository..."
        sudo add-apt-repository -y multiverse 2>/dev/null || true
    fi

    # PHP Repository (Ondrej PPA)
    log_info "Adding PHP repository (Ondrej PPA)..."
    if ls /etc/apt/sources.list.d/*ondrej* 2>/dev/null | grep -q php || [ -f /etc/apt/sources.list.d/ondrej-php.list ]; then
        log_info "Ondrej PHP repository already exists, skipping..."
    else
        # Determine supported codename for Ondrej PHP PPA
        # Supported: noble (24.04), jammy (22.04), focal (20.04), bionic (18.04)
        local php_codename="${OS_CODENAME}"
        case "${OS_CODENAME}" in
            noble|jammy|focal|bionic)
                php_codename="${OS_CODENAME}"
                ;;
            oracular|plucky|*)
                # Newer versions - fall back to noble (latest LTS)
                log_warn "Ubuntu ${OS_CODENAME} may not be supported by Ondrej PHP PPA"
                log_info "Falling back to 'noble' (Ubuntu 24.04 LTS) repository"
                php_codename="noble"
                ;;
        esac

        sudo add-apt-repository -y ppa:ondrej/php 2>&1 | tee -a "$INSTALL_LOG" || {
            log_warn "PPA add failed, trying manual method with fallback codename..."
            # Manual fallback for unsupported systems
            sudo mkdir -p /etc/apt/keyrings
            sudo curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x4F4EA0AAE5267A6C" | sudo gpg --dearmor -o /etc/apt/keyrings/ondrej-php.gpg 2>/dev/null || {
                # Ultimate fallback using apt-key (deprecated)
                sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C 2>/dev/null || true
            }
            echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] http://ppa.launchpad.net/ondrej/php/ubuntu ${php_codename} main" | sudo tee /etc/apt/sources.list.d/ondrej-php.list
        }
        log_info "Ondrej PHP repository configured for ${php_codename}"
    fi

    # MariaDB Repository
    log_info "Adding MariaDB repository..."
    if [ -f /etc/apt/sources.list.d/mariadb.list ]; then
        log_info "MariaDB repository already exists, skipping..."
    else
        # Determine supported Ubuntu codename for MariaDB repository
        local mariadb_codename
        case "${OS_CODENAME}" in
            noble|jammy|focal|bionic)
                # These are officially supported by MariaDB
                mariadb_codename="${OS_CODENAME}"
                ;;
            oracular|plucky|*)
                # Newer Ubuntu versions not yet supported - fall back to noble (24.04 LTS)
                log_warn "Ubuntu ${OS_CODENAME} not yet supported by MariaDB repository"
                log_info "Falling back to 'noble' (Ubuntu 24.04 LTS) repository"
                mariadb_codename="noble"
                ;;
        esac

        sudo curl -o /etc/apt/trusted.gpg.d/mariadb_release_signing_key.asc 'https://mariadb.org/mariadb_release_signing_key.asc' 2>/dev/null || {
            log_warn "Failed to download MariaDB GPG key"
        }
        echo "deb [arch=amd64,arm64] https://mirrors.xtom.com/mariadb/repo/${MARIADB_VERSION}/ubuntu ${mariadb_codename} main" | sudo tee /etc/apt/sources.list.d/mariadb.list
        log_info "MariaDB repository added for ${mariadb_codename}"
    fi

    # FFmpeg Repository (Rob Savoury's PPA - optimized builds with full codec support)
    log_info "Adding FFmpeg repository (Savoury PPA)..."
    if ls /etc/apt/sources.list.d/*savoury* 2>/dev/null | grep -q .; then
        log_info "Savoury FFmpeg repository already exists, skipping..."
    elif [ -f /etc/apt/sources.list.d/ffmpeg-savoury.list ]; then
        log_info "FFmpeg Savoury repository already exists, skipping..."
    else
        # Determine supported codename for Savoury FFmpeg PPA
        # Supported: noble (24.04), jammy (22.04), focal (20.04)
        local ffmpeg_codename="${OS_CODENAME}"
        case "${OS_CODENAME}" in
            noble|jammy|focal)
                ffmpeg_codename="${OS_CODENAME}"
                ;;
            oracular|plucky|*)
                # Newer versions - fall back to noble (latest LTS with full support)
                log_warn "Ubuntu ${OS_CODENAME} may not be supported by Savoury FFmpeg PPA"
                log_info "Falling back to 'noble' (Ubuntu 24.04 LTS) repository"
                ffmpeg_codename="noble"
                ;;
            bionic)
                # Bionic is too old for Savoury, use default repos
                log_info "Ubuntu Bionic will use default FFmpeg packages"
                ffmpeg_codename=""
                ;;
        esac

        if [ -n "$ffmpeg_codename" ]; then
            # Try adding Savoury PPA (provides latest FFmpeg with all codecs)
            sudo add-apt-repository -y ppa:savoury1/ffmpeg6 2>&1 | tee -a "$INSTALL_LOG" || {
                log_warn "Savoury FFmpeg6 PPA failed, trying FFmpeg5..."
                sudo add-apt-repository -y ppa:savoury1/ffmpeg5 2>&1 | tee -a "$INSTALL_LOG" || {
                    log_warn "Savoury PPA failed, trying manual method..."
                    # Manual fallback
                    sudo mkdir -p /etc/apt/keyrings
                    sudo curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xE996735927E427A733BB653E374C7797FB006459" 2>/dev/null | \
                        sudo gpg --dearmor -o /etc/apt/keyrings/savoury-ffmpeg.gpg 2>/dev/null || true

                    if [ -f /etc/apt/keyrings/savoury-ffmpeg.gpg ]; then
                        echo "deb [signed-by=/etc/apt/keyrings/savoury-ffmpeg.gpg] http://ppa.launchpad.net/savoury1/ffmpeg6/ubuntu ${ffmpeg_codename} main" | \
                            sudo tee /etc/apt/sources.list.d/ffmpeg-savoury.list
                    else
                        log_warn "Could not add Savoury FFmpeg PPA, will use default Ubuntu packages"
                    fi
                }
            }
            log_info "FFmpeg Savoury repository configured for ${ffmpeg_codename}"
        fi
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

    export NVM_DIR="${HOME_DIR}/.nvm"

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
    if [ -f "${HOME_DIR}/.bashrc" ]; then
        shell_profile="${HOME_DIR}/.bashrc"
    elif [ -f "${HOME_DIR}/.bash_profile" ]; then
        shell_profile="${HOME_DIR}/.bash_profile"
    elif [ -f "${HOME_DIR}/.profile" ]; then
        shell_profile="${HOME_DIR}/.profile"
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

    # Also install NVM and Node.js for root user (needed for sudo npm commands)
    log_info "Installing NVM and Node.js for root user (for sudo commands)..."

    sudo bash -c "
        export HOME=/root
        export NVM_DIR=\"/root/.nvm\"

        # Install NVM for root
        curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash

        # Load NVM
        [ -s \"\$NVM_DIR/nvm.sh\" ] && . \"\$NVM_DIR/nvm.sh\"

        # Install Node.js
        nvm install ${NODE_VERSION}
        nvm use ${NODE_VERSION}
        nvm alias default ${NODE_VERSION}

        # Verify
        node --version && npm --version
    " 2>&1 | tee -a "$INSTALL_LOG" && {
        log_success "Node.js installed for root user"
    } || {
        log_warn "Failed to install Node.js for root (sudo npm commands may not work)"
    }

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
    log_error "After bootstrap, script should be running as ${USER}, not root"
    exit 1
fi

# Verify USER is set correctly
if [ -z "$USER" ]; then
    USER="$(whoami)"
    HOME_DIR="$(eval echo ~$USER)"
fi

# Verify sudo access
log_info "Verifying sudo access for ${USER}..."
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
log_info "Running as user: ${USER}"
log_info "User home: ${HOME_DIR}"
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
    export NVM_DIR="${HOME_DIR}/.nvm"
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
user = ${USER}
group = ${USER}
listen = 127.0.0.1:9002
listen.owner = ${USER}
listen.group = ${USER}
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

    # Detect environment type for service management
    if ! has_systemd; then
        log_warn "Non-systemd environment detected (WSL or container)"
        log_info "Services will be managed using alternative methods"
    fi

    # Create nginx user for system services (if needed for compatibility)
    if ! id "nginx" &>/dev/null; then
        sudo useradd -r -s /sbin/nologin nginx
        log_info "Created nginx system user"
    fi

    # Sudoers already configured in bootstrap, verify it's still there
    if [ ! -f "/etc/sudoers.d/${USER}" ]; then
        echo "${USER} ALL=(ALL) NOPASSWD: ALL" | sudo tee /etc/sudoers.d/${USER} > /dev/null
        sudo chmod 0440 /etc/sudoers.d/${USER}
        log_info "${USER} added to sudoers with NOPASSWD"
    else
        log_info "${USER} already has sudo configuration"
    fi

    # Restart PHP-FPM (using helper that handles non-systemd environments)
    log_info "Configuring PHP-FPM service..."
    if service_restart "php${PHP_VERSION}-fpm"; then
        log_success "PHP-FPM restarted successfully"
    else
        log_warn "PHP-FPM restart returned non-zero, checking if running..."
        # Check if PHP-FPM is actually running
        if pgrep -f "php-fpm.*${PHP_VERSION}" &>/dev/null; then
            log_info "PHP-FPM is running (detected via process)"
        else
            handle_error 9 "Failed to restart PHP-FPM"
        fi
    fi
    service_enable "php${PHP_VERSION}-fpm"

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
    if [ -f "${HOME_DIR}/.bashrc" ]; then
        if ! grep -q "MARIADB_BIN" "${HOME_DIR}/.bashrc" 2>/dev/null; then
            cat >> "${HOME_DIR}/.bashrc" <<'MARIADB_PATH_EOF'

# MariaDB PATH
export PATH="/usr/bin:$PATH"
alias mysql='mariadb'
alias mysqldump='mariadb-dump'
alias mysqlcheck='mariadb-check'
alias mysqladmin='mariadb-admin'
MARIADB_PATH_EOF
            log_info "Added MariaDB aliases to ${HOME_DIR}/.bashrc"
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
    service_stop "mariadb"
    service_start "mariadb" || handle_error 10 "Failed to start MariaDB"

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

    service_restart "mariadb" || handle_error 10 "Failed to restart MariaDB"
    service_enable "mariadb"

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
USER=${USER}
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
    chown ${USER}:${USER} "${FOS_DIR}/.env"

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
        export NVM_DIR="${HOME_DIR}/.nvm"
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
                    sudo chown -R ${USER}:${USER} "$NPM_PREFIX/lib/node_modules" 2>/dev/null || true
                    sudo chown -R ${USER}:${USER} "$NPM_PREFIX/bin" 2>/dev/null || true

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
    chown -R ${USER}:${USER} "${FOS_DIR}"

    # Ensure fospackv69 nginx has correct ownership
    if [ -d "${FOS_DIR}/fospackv69/fos/nginx" ]; then
        chown -R ${USER}:${USER} "${FOS_DIR}/fospackv69/fos/nginx"
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
    # Create Symlinks for FFmpeg/FFprobe in all binary directories
    # -------------------------------------------------------------------------
    log_info "Creating FFmpeg/FFprobe symlinks for system-wide access..."

    # Define all target directories for symlinks
    SYMLINK_DIRS=(
        "/usr/local/bin"
        "/usr/bin"
        "/bin"
        "${HOME_DIR}/.local/bin"
        "${HOME_DIR}/bin"
        "/opt/bin"
        "/snap/bin"
    )

    # Create symlinks for ffmpeg
    for dir in "${SYMLINK_DIRS[@]}"; do
        # Skip if this is where ffmpeg is already installed
        if [ "$FFMPEG_BIN" = "${dir}/ffmpeg" ]; then
            continue
        fi

        # Create directory if it doesn't exist (only for user directories)
        if [[ "$dir" == "${HOME_DIR}"* ]]; then
            mkdir -p "$dir" 2>/dev/null || true
        fi

        # Create symlink if directory exists and ffmpeg doesn't already exist there
        if [ -d "$dir" ]; then
            if [ ! -e "${dir}/ffmpeg" ]; then
                sudo ln -sf "$FFMPEG_BIN" "${dir}/ffmpeg" 2>/dev/null && \
                    log_info "Created symlink: ${dir}/ffmpeg -> ${FFMPEG_BIN}" || \
                    log_warn "Could not create symlink in ${dir}"
            else
                log_info "ffmpeg already exists in ${dir}, skipping symlink"
            fi
        fi
    done

    # Create symlinks for ffprobe
    for dir in "${SYMLINK_DIRS[@]}"; do
        # Skip if this is where ffprobe is already installed
        if [ "$FFPROBE_BIN" = "${dir}/ffprobe" ]; then
            continue
        fi

        # Create directory if it doesn't exist (only for user directories)
        if [[ "$dir" == "${HOME_DIR}"* ]]; then
            mkdir -p "$dir" 2>/dev/null || true
        fi

        # Create symlink if directory exists and ffprobe doesn't already exist there
        if [ -d "$dir" ]; then
            if [ ! -e "${dir}/ffprobe" ]; then
                sudo ln -sf "$FFPROBE_BIN" "${dir}/ffprobe" 2>/dev/null && \
                    log_info "Created symlink: ${dir}/ffprobe -> ${FFPROBE_BIN}" || \
                    log_warn "Could not create symlink in ${dir}"
            else
                log_info "ffprobe already exists in ${dir}, skipping symlink"
            fi
        fi
    done

    # Add user's local bin to PATH if not already there
    if [[ ":$PATH:" != *":${HOME_DIR}/.local/bin:"* ]]; then
        log_info "Adding ~/.local/bin to PATH in shell profile..."
        if [ -f "${HOME_DIR}/.bashrc" ]; then
            if ! grep -q "\.local/bin" "${HOME_DIR}/.bashrc" 2>/dev/null; then
                echo 'export PATH="${HOME}/.local/bin:${PATH}"' >> "${HOME_DIR}/.bashrc"
            fi
        fi
        if [ -f "${HOME_DIR}/.profile" ]; then
            if ! grep -q "\.local/bin" "${HOME_DIR}/.profile" 2>/dev/null; then
                echo 'export PATH="${HOME}/.local/bin:${PATH}"' >> "${HOME_DIR}/.profile"
            fi
        fi
    fi

    log_success "FFmpeg/FFprobe symlinks created"

    # -------------------------------------------------------------------------
    # System-Level Low Latency Optimizations
    # -------------------------------------------------------------------------
    log_info "Applying low latency streaming optimizations..."

    # Increase file descriptor limits for streaming
    sudo tee /etc/security/limits.d/fos-streaming.conf > /dev/null <<LIMITS_EOF
# FOS-Streaming Low Latency Optimizations
# Increase file descriptor limits for high-concurrency streaming

${USER}     soft    nofile          65535
${USER}     hard    nofile          65535
${USER}     soft    nproc           65535
${USER}     hard    nproc           65535
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

    # Create wrapper with detected FFmpeg path
    sudo tee /usr/local/bin/ffmpeg-stream > /dev/null <<FFMPEG_WRAPPER_EOF
#!/usr/bin/env bash
# FFmpeg wrapper with low-latency streaming defaults
# Used by FOS-Streaming for optimized stream processing
# Generated by FOS-Streaming installer

# Path to FFmpeg binary (detected during installation)
FFMPEG_BINARY="${FFMPEG_BIN}"

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
exec "\${FFMPEG_BINARY}" "\${FFMPEG_LOW_LATENCY_OPTS[@]}" "\$@"
FFMPEG_WRAPPER_EOF

    sudo chmod 755 /usr/local/bin/ffmpeg-stream

    # Also create ffprobe-stream wrapper for consistent analysis
    sudo tee /usr/local/bin/ffprobe-stream > /dev/null <<FFPROBE_WRAPPER_EOF
#!/usr/bin/env bash
# FFprobe wrapper for stream analysis
# Used by FOS-Streaming for stream inspection
# Generated by FOS-Streaming installer

# Path to FFprobe binary (detected during installation)
FFPROBE_BINARY="${FFPROBE_BIN}"

# Default options for quick stream analysis
FFPROBE_OPTS=(
    -v "quiet"
    -print_format "json"
    -show_format
    -show_streams
)

# Execute with default options (can be overridden)
exec "\${FFPROBE_BINARY}" "\${FFPROBE_OPTS[@]}" "\$@"
FFPROBE_WRAPPER_EOF

    sudo chmod 755 /usr/local/bin/ffprobe-stream

    log_info "FFmpeg streaming wrapper created at /usr/local/bin/ffmpeg-stream"
    log_info "FFprobe streaming wrapper created at /usr/local/bin/ffprobe-stream"

    # -------------------------------------------------------------------------
    # Configure sudoers for FFmpeg (no password required for streaming)
    # -------------------------------------------------------------------------
    log_info "Configuring FFmpeg sudo access..."
    sudo rm -f /etc/sudoers.d/fos-ffmpeg 2>/dev/null || true

    sudo tee /etc/sudoers.d/fos-ffmpeg > /dev/null <<SUDOERS_EOF
# FOS-Streaming FFmpeg/FFprobe sudo access
${USER} ALL = (root) NOPASSWD: ${FFMPEG_BIN}
${USER} ALL = (root) NOPASSWD: ${FFPROBE_BIN}
${USER} ALL = (root) NOPASSWD: /usr/local/bin/ffmpeg-stream
${USER} ALL = (root) NOPASSWD: /usr/local/bin/ffprobe-stream
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
    service_stop "redis-server"
    service_start "redis-server" || {
        log_warn "Redis failed to start with new config, trying default..."
        sudo cp /etc/redis/redis.conf.backup /etc/redis/redis.conf 2>/dev/null || true
        service_start "redis-server" || log_error "Redis failed to start"
    }
    service_enable "redis-server"

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
            sudo chown -R ${USER}:${USER} "${NGINX_DIR}"
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
User=${USER}
Group=${USER}
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

        systemd_reload
        service_enable "fos-nginx"
    else
        log_warn "Nginx binary not found, skipping service creation"
    fi

    service_enable "php${PHP_VERSION}-fpm"

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
${USER} soft nofile 1000000
${USER} hard nofile 1000000
${USER} soft nproc 65535
${USER} hard nproc 65535
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
    export NVM_DIR="${HOME_DIR}/.nvm"
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
- Installation User: ${USER}
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
  Installation User: ${USER}
  Project Directory: ${FOS_DIR}
  User Home:         ${HOME_DIR}
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
  NVM Usage (for ${USER}):
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

# Generate credentials report markdown file
CREDENTIALS_FILE=$(generate_credentials_report)

# Display prominent notice about credentials file
echo ""
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}  📄 CREDENTIALS SAVED TO FILE${NC}"
echo -e "${GREEN}================================================================${NC}"
echo ""
echo -e "  All credentials have been saved to:"
echo -e "  ${CYAN}${CREDENTIALS_FILE}${NC}"
echo ""
echo -e "  ${YELLOW}⚠️  IMPORTANT:${NC}"
echo -e "  1. View the file: ${CYAN}cat ${CREDENTIALS_FILE}${NC}"
echo -e "  2. Save credentials to a password manager"
echo -e "  3. Delete the file: ${CYAN}rm ${CREDENTIALS_FILE}${NC}"
echo ""
echo -e "${GREEN}================================================================${NC}"

# Display created user credentials prominently if a new user was created
if [ -n "$CREATED_USER_PASSWORD" ]; then
    echo ""
    echo -e "${RED}================================================================${NC}"
    echo -e "${RED}  ⚠️  IMPORTANT: SYSTEM USER CREDENTIALS  ⚠️${NC}"
    echo -e "${RED}================================================================${NC}"
    echo ""
    echo -e "  A new system user was created for this installation."
    echo -e "  ${YELLOW}SAVE THESE CREDENTIALS NOW!${NC}"
    echo ""
    echo -e "  ${CYAN}Username:${NC} ${USER}"
    echo -e "  ${CYAN}Password:${NC} ${CREATED_USER_PASSWORD}"
    echo ""
    echo -e "  ${GREEN}To login:${NC}"
    echo -e "    ssh ${USER}@${PUBLIC_IP:-<server-ip>}"
    echo -e "    su - ${USER}"
    echo ""
    echo -e "${RED}================================================================${NC}"
    echo ""
fi

log_success "Installation script finished successfully!"
log_info "Credentials file: ${CREDENTIALS_FILE}"
log_info "Installation log: ${INSTALL_LOG}"
log_info "Project directory: ${FOS_DIR}"
log_info "Run 'npm run dev' to start the development server"
log_info "Enjoy your FOS-Streaming installation!"
