#!/usr/bin/env python3
"""
FOS-Streaming v70 - Step 2: Dependencies Installation
======================================================

This script installs all prerequisites and dependencies for FOS-Streaming.
It should be run as a regular user with sudo privileges (created by 01-setup-user.py).

Features:
- Installs packages one-by-one with retry logic
- Configures PHP 8.4 with required extensions
- Installs Composer and Node.js (via NVM)
- Installs MariaDB client (server setup is manual)
- Installs Redis server
- Installs FFmpeg
- Provides progress feedback and logging

Usage:
    python3 02-install-deps.py [--skip-update] [--skip-node] [--skip-php] [--skip-redis]

Example:
    python3 02-install-deps.py
    python3 02-install-deps.py --skip-update
"""

import os
import sys
import subprocess
import shutil
import argparse
import time
import json
from pathlib import Path
from datetime import datetime
from typing import List, Tuple, Optional

# ANSI Colors
RED = '\033[0;31m'
GREEN = '\033[0;32m'
YELLOW = '\033[1;33m'
BLUE = '\033[0;34m'
CYAN = '\033[0;36m'
MAGENTA = '\033[0;35m'
NC = '\033[0m'  # No Color

# Configuration
PHP_VERSION = "8.4"
NODE_VERSION = "20"
MARIADB_VERSION = "11.4"

# Detect script and project directories
SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_DIR = SCRIPT_DIR.parent
LOG_FILE = SCRIPT_DIR / "install-deps.log"


def print_banner():
    """Display the installation banner."""
    print(f"""
{CYAN}================================================================{NC}
{CYAN}  FOS-Streaming v70 - Dependencies Installation{NC}
{CYAN}================================================================{NC}
{CYAN}  PHP: {PHP_VERSION}  |  Node.js: {NODE_VERSION} LTS  |  MariaDB: {MARIADB_VERSION}{NC}
{CYAN}================================================================{NC}
""")


def log_to_file(level: str, message: str):
    """Log message to file."""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with open(LOG_FILE, 'a') as f:
        f.write(f"[{timestamp}] [{level}] {message}\n")


def log_info(msg):
    print(f"{GREEN}[INFO]{NC} {msg}")
    log_to_file("INFO", msg)


def log_warn(msg):
    print(f"{YELLOW}[WARN]{NC} {msg}")
    log_to_file("WARN", msg)


def log_error(msg):
    print(f"{RED}[ERROR]{NC} {msg}")
    log_to_file("ERROR", msg)


def log_success(msg):
    print(f"{GREEN}[SUCCESS]{NC} {msg}")
    log_to_file("SUCCESS", msg)


def log_step(step: int, total: int, msg: str):
    print(f"\n{BLUE}[Step {step}/{total}]{NC} {msg}\n")
    log_to_file("STEP", f"Step {step}/{total}: {msg}")


def log_package(name: str, status: str):
    """Log package installation status."""
    if status == "installed":
        print(f"  {GREEN}✓{NC} {name}")
    elif status == "already":
        print(f"  {CYAN}✓{NC} {name} (already installed)")
    elif status == "failed":
        print(f"  {RED}✗{NC} {name} (FAILED)")
    elif status == "skipped":
        print(f"  {YELLOW}○{NC} {name} (skipped)")


def check_sudo():
    """Verify user has sudo access."""
    result = subprocess.run(
        ['sudo', '-n', 'true'],
        capture_output=True
    )
    if result.returncode != 0:
        log_error("This script requires sudo access!")
        log_info("Make sure you ran 01-setup-user.py first.")
        sys.exit(1)


def detect_os() -> Tuple[str, str, str]:
    """Detect the operating system."""
    os_release = Path('/etc/os-release')
    if os_release.exists():
        info = {}
        for line in os_release.read_text().splitlines():
            if '=' in line:
                key, value = line.split('=', 1)
                info[key] = value.strip('"')

        os_type = info.get('ID', 'unknown')
        os_version = info.get('VERSION_ID', 'unknown')
        os_codename = info.get('VERSION_CODENAME', 'unknown')
        return os_type, os_version, os_codename

    return 'unknown', 'unknown', 'unknown'


def run_cmd(cmd: List[str], capture=False, check=True, env=None) -> Tuple[bool, str]:
    """Run a command and return (success, output)."""
    log_to_file("CMD", ' '.join(cmd))
    try:
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            check=check,
            env=env or os.environ.copy()
        )
        output = result.stdout + result.stderr
        log_to_file("OUTPUT", output[:500] if len(output) > 500 else output)
        return True, result.stdout
    except subprocess.CalledProcessError as e:
        output = (e.stdout or '') + (e.stderr or '')
        log_to_file("ERROR", output[:500] if len(output) > 500 else output)
        return False, output


def apt_update() -> bool:
    """Update package lists."""
    log_info("Updating package lists...")
    success, _ = run_cmd(['sudo', 'apt-get', 'update', '-y'], check=False)
    return success


def apt_install(package: str, max_retries=3) -> bool:
    """Install a single package with retry logic."""
    # Check if already installed
    result = subprocess.run(
        ['dpkg', '-l', package],
        capture_output=True,
        text=True
    )
    if result.returncode == 0 and 'ii' in result.stdout:
        log_package(package, "already")
        return True

    # Try to install
    for attempt in range(max_retries):
        env = os.environ.copy()
        env['DEBIAN_FRONTEND'] = 'noninteractive'

        success, output = run_cmd(
            ['sudo', 'apt-get', 'install', '-y', package],
            check=False,
            env=env
        )

        if success:
            log_package(package, "installed")
            return True

        if attempt < max_retries - 1:
            log_warn(f"  Retry {attempt + 1}/{max_retries} for {package}...")
            time.sleep(2)

    log_package(package, "failed")
    return False


def install_packages(packages: List[str], critical=False) -> Tuple[int, List[str]]:
    """Install multiple packages one by one."""
    installed = 0
    failed = []

    for pkg in packages:
        if apt_install(pkg):
            installed += 1
        else:
            failed.append(pkg)
            if critical:
                log_error(f"Critical package '{pkg}' failed to install!")
                return installed, failed

    return installed, failed


def setup_php_repository(os_type: str, os_codename: str) -> bool:
    """Set up PHP repository for the latest PHP version."""
    log_info(f"Setting up PHP {PHP_VERSION} repository...")

    if os_type == 'ubuntu':
        # Add ondrej/php PPA for Ubuntu
        success, _ = run_cmd(['sudo', 'apt-get', 'install', '-y', 'software-properties-common'], check=False)
        if not success:
            log_warn("Could not install software-properties-common")

        success, _ = run_cmd(['sudo', 'add-apt-repository', '-y', 'ppa:ondrej/php'], check=False)
        if not success:
            log_warn("Could not add ondrej/php PPA, trying sury.org...")
            return setup_sury_repo(os_codename)
        return True

    elif os_type == 'debian':
        return setup_sury_repo(os_codename)

    return False


def setup_sury_repo(os_codename: str) -> bool:
    """Set up sury.org PHP repository for Debian/Ubuntu."""
    log_info("Setting up sury.org PHP repository...")

    # Install prerequisites
    run_cmd(['sudo', 'apt-get', 'install', '-y', 'lsb-release', 'ca-certificates', 'curl'], check=False)

    # Download and install GPG key
    success, _ = run_cmd([
        'sudo', 'bash', '-c',
        'curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg'
    ], check=False)

    if not success:
        log_error("Failed to download PHP repository key")
        return False

    # Add repository
    repo_line = f"deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ {os_codename} main"

    success, _ = run_cmd([
        'sudo', 'bash', '-c',
        f'echo "{repo_line}" > /etc/apt/sources.list.d/php.list'
    ], check=False)

    if not success:
        log_error("Failed to add PHP repository")
        return False

    # Update package lists
    apt_update()
    return True


def setup_mariadb_repository(os_type: str, os_codename: str) -> bool:
    """Set up MariaDB repository."""
    log_info(f"Setting up MariaDB {MARIADB_VERSION} repository...")

    # Install prerequisites
    run_cmd(['sudo', 'apt-get', 'install', '-y', 'curl', 'gnupg'], check=False)

    # Download MariaDB repository setup script
    success, _ = run_cmd([
        'sudo', 'bash', '-c',
        'curl -sS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup | bash'
    ], check=False)

    if success:
        apt_update()
        return True

    log_warn("MariaDB repo setup failed, will use distribution packages")
    return False


def install_nvm_nodejs() -> bool:
    """Install NVM and Node.js."""
    home_dir = Path.home()
    nvm_dir = home_dir / '.nvm'

    log_info("Installing NVM (Node Version Manager)...")

    # Download and run NVM installer
    success, _ = run_cmd([
        'bash', '-c',
        'curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash'
    ], check=False)

    if not success:
        log_error("Failed to install NVM")
        return False

    # Source NVM and install Node.js
    log_info(f"Installing Node.js {NODE_VERSION} LTS...")

    nvm_script = nvm_dir / 'nvm.sh'
    if not nvm_script.exists():
        log_error("NVM script not found after installation")
        return False

    # Install Node.js using NVM
    install_cmd = f'''
    export NVM_DIR="{nvm_dir}"
    [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
    nvm install {NODE_VERSION}
    nvm use {NODE_VERSION}
    nvm alias default {NODE_VERSION}
    '''

    success, output = run_cmd(['bash', '-c', install_cmd], check=False)
    if not success:
        log_error("Failed to install Node.js via NVM")
        return False

    log_success(f"Node.js {NODE_VERSION} installed via NVM")

    # Install PM2 globally
    log_info("Installing PM2 process manager...")
    pm2_cmd = f'''
    export NVM_DIR="{nvm_dir}"
    [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
    npm install -g pm2
    '''
    success, _ = run_cmd(['bash', '-c', pm2_cmd], check=False)
    if success:
        log_success("PM2 installed globally")
    else:
        log_warn("PM2 installation failed - can be installed later")

    return True


def install_composer() -> bool:
    """Install Composer globally."""
    log_info("Installing Composer...")

    # Check if already installed
    if shutil.which('composer'):
        version = subprocess.run(['composer', '--version'], capture_output=True, text=True)
        log_info(f"Composer already installed: {version.stdout.strip()}")
        return True

    # Download Composer installer
    success, _ = run_cmd([
        'bash', '-c',
        '''
        EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

        if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
            rm composer-setup.php
            echo 'ERROR: Invalid installer checksum'
            exit 1
        fi

        php composer-setup.php --quiet
        rm composer-setup.php
        sudo mv composer.phar /usr/local/bin/composer
        sudo chmod +x /usr/local/bin/composer
        '''
    ], check=False)

    if success:
        log_success("Composer installed successfully")
        return True

    log_error("Composer installation failed")
    return False


def configure_redis(password: str) -> bool:
    """Configure Redis with password authentication."""
    log_info("Configuring Redis...")

    redis_conf = '/etc/redis/redis.conf'

    # Check if config exists using sudo (avoid permission error)
    result = subprocess.run(['sudo', 'test', '-f', redis_conf], capture_output=True)
    if result.returncode != 0:
        log_warn("Redis config not found, skipping configuration")
        return False

    # Backup original config
    run_cmd(['sudo', 'cp', redis_conf, redis_conf + '.backup'], check=False)

    # Configure Redis
    config_commands = f'''
    sudo sed -i 's/^# requirepass .*/requirepass {password}/' {redis_conf}
    sudo sed -i 's/^requirepass .*/requirepass {password}/' {redis_conf}
    sudo sed -i 's/^bind 127.0.0.1.*/bind 127.0.0.1/' {redis_conf}
    sudo sed -i 's/^# maxmemory .*/maxmemory 256mb/' {redis_conf}
    sudo sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' {redis_conf}
    '''

    run_cmd(['bash', '-c', config_commands], check=False)

    # Restart Redis
    run_cmd(['sudo', 'systemctl', 'restart', 'redis-server'], check=False)
    run_cmd(['sudo', 'systemctl', 'enable', 'redis-server'], check=False)

    log_success("Redis configured")
    return True


def save_install_state(state: dict):
    """Save installation state for resume capability."""
    state_file = PROJECT_DIR / '.install-deps-state.json'
    with open(state_file, 'w') as f:
        json.dump(state, f, indent=2)


def load_install_state() -> dict:
    """Load installation state."""
    state_file = PROJECT_DIR / '.install-deps-state.json'
    if state_file.exists():
        with open(state_file) as f:
            return json.load(f)
    return {}


def generate_redis_password() -> str:
    """Generate a secure Redis password."""
    import secrets
    import string
    alphabet = string.ascii_letters + string.digits
    return ''.join(secrets.choice(alphabet) for _ in range(32))


def main():
    parser = argparse.ArgumentParser(description='FOS-Streaming Dependencies Installer')
    parser.add_argument('--skip-update', action='store_true', help='Skip apt update')
    parser.add_argument('--skip-node', action='store_true', help='Skip Node.js installation')
    parser.add_argument('--skip-php', action='store_true', help='Skip PHP installation')
    parser.add_argument('--skip-redis', action='store_true', help='Skip Redis installation')
    parser.add_argument('--skip-ffmpeg', action='store_true', help='Skip FFmpeg installation')
    args = parser.parse_args()

    print_banner()

    # Initialize log file
    log_to_file("INFO", "=" * 60)
    log_to_file("INFO", f"FOS-Streaming Dependencies Installation Started")
    log_to_file("INFO", f"Date: {datetime.now().isoformat()}")
    log_to_file("INFO", "=" * 60)

    # Check sudo access
    check_sudo()
    log_success("Sudo access verified")

    # Detect OS
    os_type, os_version, os_codename = detect_os()
    log_info(f"Detected OS: {os_type} {os_version} ({os_codename})")

    if os_type not in ['debian', 'ubuntu']:
        log_error(f"Unsupported OS: {os_type}")
        log_info("This installer supports Debian and Ubuntu only.")
        sys.exit(1)

    total_steps = 9
    current_step = 0

    # =========================================================================
    # Step 1: Update System
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Updating System")

    if not args.skip_update:
        apt_update()

        log_info("Upgrading installed packages...")
        run_cmd(['sudo', 'apt-get', 'upgrade', '-y'], check=False)

        log_info("Cleaning up unused packages...")
        run_cmd(['sudo', 'apt-get', 'autoremove', '-y'], check=False)
    else:
        log_info("Skipping system update (--skip-update)")

    # =========================================================================
    # Step 2: Install Build Dependencies
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Installing Build Dependencies")

    build_deps = [
        "build-essential",
        "libssl-dev",
        "libpcre3",
        "libpcre3-dev",
        "zlib1g-dev",
        "curl",
        "wget",
        "zip",
        "unzip",
        "git",
        "lsof",
        "htop",
        "ca-certificates",
        "apt-transport-https",
        "gnupg2",
        "software-properties-common",
        "openssl",
    ]

    installed, failed = install_packages(build_deps)
    log_info(f"Installed {installed}/{len(build_deps)} build dependencies")
    if failed:
        log_warn(f"Failed packages: {', '.join(failed)}")

    # =========================================================================
    # Step 3: Install Library Dependencies
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Installing Library Dependencies")

    lib_deps = [
        "libxml2-dev",
        "libbz2-dev",
        "libcurl4-openssl-dev",
        "libxslt1-dev",
        "libsqlite3-dev",
        "libgd-dev",
        "libjpeg-dev",
        "libpng-dev",
        "libfreetype6-dev",
        "libwebp-dev",
        "libzip-dev",
        "libonig-dev",
        "libreadline-dev",
        "libsodium-dev",
        "libicu-dev",
    ]

    installed, failed = install_packages(lib_deps)
    log_info(f"Installed {installed}/{len(lib_deps)} library dependencies")
    if failed:
        log_warn(f"Failed packages: {', '.join(failed)}")

    # =========================================================================
    # Step 4: Install PHP 8.4
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, f"Installing PHP {PHP_VERSION}")

    if not args.skip_php:
        # Setup PHP repository
        setup_php_repository(os_type, os_codename)

        # Critical PHP packages
        php_critical = [
            f"php{PHP_VERSION}",
            f"php{PHP_VERSION}-cli",
            f"php{PHP_VERSION}-fpm",
            f"php{PHP_VERSION}-common",
            f"php{PHP_VERSION}-mysql",
            f"php{PHP_VERSION}-curl",
            f"php{PHP_VERSION}-mbstring",
            f"php{PHP_VERSION}-xml",
            f"php{PHP_VERSION}-opcache",
            f"php{PHP_VERSION}-readline",
        ]

        log_info("Installing critical PHP packages...")
        installed, failed = install_packages(php_critical, critical=True)

        if failed:
            log_error(f"Critical PHP packages failed: {', '.join(failed)}")
            log_error("Cannot continue without PHP. Please check the log file.")
            sys.exit(1)

        # Optional PHP packages
        php_optional = [
            f"php{PHP_VERSION}-gd",
            f"php{PHP_VERSION}-zip",
            f"php{PHP_VERSION}-bcmath",
            f"php{PHP_VERSION}-intl",
            f"php{PHP_VERSION}-bz2",
            f"php{PHP_VERSION}-redis",
            f"php{PHP_VERSION}-imagick",
        ]

        log_info("Installing optional PHP packages...")
        installed, failed = install_packages(php_optional)
        if failed:
            log_warn(f"Optional PHP packages not installed: {', '.join(failed)}")
            log_info("These can be installed manually later if needed.")

        # Verify PHP installation
        result = subprocess.run(['php', '-v'], capture_output=True, text=True)
        if result.returncode == 0:
            php_ver = result.stdout.split('\n')[0]
            log_success(f"PHP installed: {php_ver}")
        else:
            log_error("PHP installation verification failed!")
    else:
        log_info("Skipping PHP installation (--skip-php)")

    # =========================================================================
    # Step 5: Install Composer
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Installing Composer")

    if not args.skip_php:
        install_composer()
    else:
        log_info("Skipping Composer (PHP was skipped)")

    # =========================================================================
    # Step 6: Install Node.js via NVM
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, f"Installing Node.js {NODE_VERSION}")

    if not args.skip_node:
        install_nvm_nodejs()
    else:
        log_info("Skipping Node.js installation (--skip-node)")

    # =========================================================================
    # Step 7: Setup MariaDB Repository & Client
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, f"Setting up MariaDB {MARIADB_VERSION}")

    # Setup MariaDB repository for latest version
    setup_mariadb_repository(os_type, os_codename)

    # Install MariaDB client only (server installation is manual for security)
    mariadb_packages = ["mariadb-client"]
    installed, failed = install_packages(mariadb_packages)

    if installed > 0:
        result = subprocess.run(['mariadb', '--version'], capture_output=True, text=True)
        if result.returncode == 0:
            log_success(f"MariaDB client installed: {result.stdout.strip()}")
        log_info("NOTE: MariaDB SERVER installation is manual - see README.md Step 4")
    else:
        log_warn("MariaDB client not installed - install manually later")

    # =========================================================================
    # Step 8: Install Redis
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Installing Redis")

    if not args.skip_redis:
        redis_packages = ["redis-server", "redis-tools"]
        installed, failed = install_packages(redis_packages)

        if installed > 0:
            # Generate and save Redis password
            redis_password = generate_redis_password()
            configure_redis(redis_password)

            # Save password to file
            redis_pass_file = Path('/root/REDIS_PASSWORD')
            run_cmd(['sudo', 'bash', '-c', f'echo "{redis_password}" > {redis_pass_file}'], check=False)
            run_cmd(['sudo', 'chmod', '600', str(redis_pass_file)], check=False)

            log_info(f"Redis password saved to: {redis_pass_file}")
    else:
        log_info("Skipping Redis installation (--skip-redis)")

    # =========================================================================
    # Step 9: Install FFmpeg
    # =========================================================================
    current_step += 1
    log_step(current_step, total_steps, "Installing FFmpeg")

    if not args.skip_ffmpeg:
        ffmpeg_packages = ["ffmpeg"]
        installed, failed = install_packages(ffmpeg_packages)

        if installed > 0:
            result = subprocess.run(['ffmpeg', '-version'], capture_output=True, text=True)
            if result.returncode == 0:
                ffmpeg_ver = result.stdout.split('\n')[0]
                log_success(f"FFmpeg installed: {ffmpeg_ver}")
        else:
            log_warn("FFmpeg not installed - streaming features may be limited")
    else:
        log_info("Skipping FFmpeg installation (--skip-ffmpeg)")

    # =========================================================================
    # Summary
    # =========================================================================
    print(f"""
{GREEN}================================================================{NC}
{GREEN}  Dependencies Installation Complete!{NC}
{GREEN}================================================================{NC}

  {CYAN}Log file:{NC}  {LOG_FILE}

{GREEN}================================================================{NC}

{BLUE}Next Steps:{NC}

  {YELLOW}1. Create the Database{NC}
     See README.md for manual database setup instructions.
     Quick summary:
     {CYAN}sudo mariadb{NC}
     {CYAN}CREATE DATABASE fos_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;{NC}
     {CYAN}CREATE USER 'fos'@'localhost' IDENTIFIED BY 'your_secure_password';{NC}
     {CYAN}GRANT ALL ON fos_streaming.* TO 'fos'@'localhost';{NC}
     {CYAN}FLUSH PRIVILEGES;{NC}

  {YELLOW}2. Configure Environment{NC}
     {CYAN}cp .env.example .env{NC}
     {CYAN}nano .env  # Edit database credentials{NC}

  {YELLOW}3. Install PHP Dependencies{NC}
     {CYAN}composer install{NC}

  {YELLOW}4. Install Frontend Dependencies{NC}
     {CYAN}source ~/.nvm/nvm.sh{NC}
     {CYAN}npm install{NC}
     {CYAN}npm run build{NC}

  {YELLOW}5. Run Database Migrations{NC}
     {CYAN}php artisan migrate{NC}
     {CYAN}php artisan db:seed{NC}

  {YELLOW}6. Build Nginx (Optional){NC}
     See README.md for nginx build instructions.
     Pre-built binaries may be available in fospackv69/.

{GREEN}================================================================{NC}
""")

    log_success("Dependencies installation completed!")
    log_to_file("INFO", "Installation completed successfully")


if __name__ == "__main__":
    main()
