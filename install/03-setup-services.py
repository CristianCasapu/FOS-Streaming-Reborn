#!/usr/bin/env python3
"""
FOS-Streaming v70 - Step 3: Service Configuration Setup
========================================================

This script installs and configures:
- PHP-FPM pools (admin and streaming)
- Nginx virtual hosts (admin and streaming)
- Required directories and permissions

Run this AFTER 02-install-deps.py and BEFORE starting services.

Usage:
    python3 03-setup-services.py [--user USERNAME] [--fos-dir /path/to/FOS-Streaming]

Example:
    python3 03-setup-services.py
    python3 03-setup-services.py --user fosadmin --fos-dir /home/fosadmin/FOS-Streaming
"""

import os
import sys
import subprocess
import shutil
import argparse
import re
from pathlib import Path

# ANSI Colors
RED = '\033[0;31m'
GREEN = '\033[0;32m'
YELLOW = '\033[1;33m'
BLUE = '\033[0;34m'
CYAN = '\033[0;36m'
NC = '\033[0m'

# Default configuration
DEFAULT_PHP_VERSION = "8.4"


def log_info(msg):
    print(f"{GREEN}[INFO]{NC} {msg}")


def log_warn(msg):
    print(f"{YELLOW}[WARN]{NC} {msg}")


def log_error(msg):
    print(f"{RED}[ERROR]{NC} {msg}")


def log_success(msg):
    print(f"{GREEN}[SUCCESS]{NC} {msg}")


def log_step(msg):
    print(f"\n{BLUE}==={NC} {msg} {BLUE}==={NC}\n")


def run_cmd(cmd, check=True):
    """Run a shell command."""
    try:
        result = subprocess.run(
            cmd,
            shell=isinstance(cmd, str),
            capture_output=True,
            text=True,
            check=check
        )
        return True, result.stdout.strip()
    except subprocess.CalledProcessError as e:
        return False, e.stderr


def check_sudo():
    """Verify user has sudo access."""
    result = subprocess.run(['sudo', '-n', 'true'], capture_output=True)
    if result.returncode != 0:
        log_error("This script requires sudo access!")
        sys.exit(1)


def detect_php_version():
    """Detect installed PHP version."""
    for version in ["8.4", "8.3", "8.2", "8.1"]:
        if Path(f"/etc/php/{version}/fpm").exists():
            return version
    return DEFAULT_PHP_VERSION


def replace_in_file(file_path: Path, replacements: dict) -> str:
    """Read file, make replacements, return modified content."""
    content = file_path.read_text()
    for old, new in replacements.items():
        content = content.replace(old, new)
    return content


def install_php_fpm_pools(fos_dir: Path, user: str, php_version: str):
    """Install PHP-FPM pool configurations."""
    log_step("Installing PHP-FPM Pool Configurations")

    config_dir = fos_dir / "install" / "config" / "php-fpm"
    fpm_pool_dir = Path(f"/etc/php/{php_version}/fpm/pool.d")

    if not fpm_pool_dir.exists():
        log_error(f"PHP-FPM pool directory not found: {fpm_pool_dir}")
        return False

    # Replacements for config files
    replacements = {
        "fosadmin": user,
        "/home/fosadmin/FOS-Streaming": str(fos_dir),
        "php8.4": f"php{php_version}",
    }

    pools = [
        ("fos-admin.conf", "Admin pool"),
        ("fos-streaming.conf", "Streaming pool"),
    ]

    for pool_file, description in pools:
        source = config_dir / pool_file
        dest = fpm_pool_dir / pool_file

        if not source.exists():
            log_warn(f"Source file not found: {source}")
            continue

        # Read and modify content
        content = replace_in_file(source, replacements)

        # Write to destination
        log_info(f"Installing {description}: {dest}")
        success, _ = run_cmd(f"sudo tee {dest} > /dev/null", check=False)

        proc = subprocess.run(
            ['sudo', 'tee', str(dest)],
            input=content,
            text=True,
            capture_output=True
        )

        if proc.returncode == 0:
            log_success(f"Installed {pool_file}")
        else:
            log_error(f"Failed to install {pool_file}")

    # Disable default www pool if both custom pools are installed
    default_pool = fpm_pool_dir / "www.conf"
    if default_pool.exists():
        log_info("Backing up and disabling default www pool...")
        run_cmd(f"sudo mv {default_pool} {default_pool}.disabled", check=False)

    return True


def install_nginx_configs(fos_dir: Path, user: str, use_system_nginx: bool):
    """Install Nginx configurations."""
    log_step("Installing Nginx Configurations")

    config_dir = fos_dir / "install" / "config" / "nginx"

    # Replacements
    replacements = {
        "fosadmin": user,
        "/home/fosadmin/FOS-Streaming": str(fos_dir),
    }

    if use_system_nginx:
        # System nginx: install to /etc/nginx/sites-available
        nginx_sites = Path("/etc/nginx/sites-available")
        nginx_enabled = Path("/etc/nginx/sites-enabled")

        if not nginx_sites.exists():
            log_error("System nginx not found at /etc/nginx/sites-available")
            return False

        # Install admin vhost
        admin_source = config_dir / "fos-admin.conf"
        admin_dest = nginx_sites / "fos-admin"

        if admin_source.exists():
            content = replace_in_file(admin_source, replacements)
            proc = subprocess.run(
                ['sudo', 'tee', str(admin_dest)],
                input=content,
                text=True,
                capture_output=True
            )
            if proc.returncode == 0:
                log_success("Installed fos-admin nginx config")
                # Enable site
                run_cmd(f"sudo ln -sf {admin_dest} {nginx_enabled}/fos-admin", check=False)
            else:
                log_error("Failed to install fos-admin nginx config")

        # Note: Streaming config requires HTTP-FLV module (FOS nginx)
        log_warn("System nginx doesn't support HTTP-FLV streaming.")
        log_info("Use FOS custom nginx for full streaming support.")

    else:
        # FOS nginx: install to fospackv69/fos/nginx/conf
        fos_nginx_conf = fos_dir / "fospackv69" / "fos" / "nginx" / "conf"

        if not fos_nginx_conf.exists():
            log_warn(f"FOS nginx conf directory not found: {fos_nginx_conf}")
            log_info("Creating directory...")
            fos_nginx_conf.mkdir(parents=True, exist_ok=True)

        # Copy main nginx.conf
        main_conf = config_dir / "nginx.conf"
        if main_conf.exists():
            content = replace_in_file(main_conf, replacements)
            dest = fos_nginx_conf / "nginx.conf"
            dest.write_text(content)
            log_success(f"Installed main nginx.conf to {dest}")

        # Copy vhost configs to install/config/nginx (they're already there)
        # Just update them with correct paths
        for conf_file in ["fos-admin.conf", "fos-streaming.conf", "fos-rtmp.conf"]:
            source = config_dir / conf_file
            if source.exists():
                content = replace_in_file(source, replacements)
                source.write_text(content)
                log_success(f"Updated {conf_file} with correct paths")

    return True


def create_directories(fos_dir: Path, user: str):
    """Create required directories."""
    log_step("Creating Required Directories")

    directories = [
        fos_dir / "logs",
        fos_dir / "hl",
        fos_dir / "cache",
        fos_dir / "storage" / "framework" / "cache",
        fos_dir / "storage" / "framework" / "sessions",
        fos_dir / "storage" / "framework" / "views",
        fos_dir / "storage" / "logs",
        fos_dir / "storage" / "thumbnails",
        Path("/run/php"),
    ]

    for directory in directories:
        if not directory.exists():
            log_info(f"Creating: {directory}")
            if str(directory).startswith("/run"):
                run_cmd(f"sudo mkdir -p {directory}", check=False)
                run_cmd(f"sudo chown {user}:{user} {directory}", check=False)
            else:
                directory.mkdir(parents=True, exist_ok=True)

    # Set permissions
    log_info("Setting directory permissions...")
    writable_dirs = ["logs", "hl", "cache", "storage"]
    for dir_name in writable_dirs:
        dir_path = fos_dir / dir_name
        if dir_path.exists():
            run_cmd(f"chmod -R 775 {dir_path}", check=False)

    log_success("Directories created and permissions set")
    return True


def create_systemd_services(fos_dir: Path, user: str, php_version: str):
    """Create systemd service files."""
    log_step("Creating Systemd Service Files")

    home_dir = Path.home()

    # FOS Nginx service
    nginx_service = f"""[Unit]
Description=FOS-Streaming Nginx with HTTP-FLV
After=network.target php{php_version}-fpm.service
Wants=php{php_version}-fpm.service

[Service]
Type=forking
User=root
PIDFile={fos_dir}/fospackv69/fos/nginx/logs/nginx.pid
ExecStartPre={fos_dir}/fospackv69/fos/nginx/sbin/nginx_fos -t
ExecStart={fos_dir}/fospackv69/fos/nginx/sbin/nginx_fos
ExecReload=/bin/kill -s HUP $MAINPID
ExecStop=/bin/kill -s QUIT $MAINPID
Restart=on-failure
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
"""

    service_path = Path("/etc/systemd/system/fos-nginx.service")
    proc = subprocess.run(
        ['sudo', 'tee', str(service_path)],
        input=nginx_service,
        text=True,
        capture_output=True
    )

    if proc.returncode == 0:
        log_success("Created fos-nginx.service")
        run_cmd("sudo systemctl daemon-reload", check=False)
    else:
        log_error("Failed to create fos-nginx.service")

    return True


def restart_services(php_version: str):
    """Restart PHP-FPM to apply new configurations."""
    log_step("Restarting Services")

    log_info(f"Restarting PHP {php_version} FPM...")
    success, output = run_cmd(f"sudo systemctl restart php{php_version}-fpm", check=False)

    if success:
        log_success("PHP-FPM restarted successfully")
    else:
        log_warn("PHP-FPM restart had issues, checking status...")
        run_cmd(f"sudo systemctl status php{php_version}-fpm", check=False)

    # Verify pools are running
    log_info("Verifying PHP-FPM pools...")
    admin_sock = Path(f"/run/php/php{php_version}-fpm-admin.sock")
    streaming_sock = Path(f"/run/php/php{php_version}-fpm-streaming.sock")

    # Give it a moment to start
    import time
    time.sleep(2)

    if admin_sock.exists():
        log_success("Admin pool socket created")
    else:
        log_warn("Admin pool socket not found (may need manual verification)")

    if streaming_sock.exists():
        log_success("Streaming pool socket created")
    else:
        log_warn("Streaming pool socket not found (may need manual verification)")

    return True


def print_summary(fos_dir: Path, user: str, php_version: str):
    """Print installation summary."""
    print(f"""
{GREEN}================================================================{NC}
{GREEN}  Service Configuration Complete!{NC}
{GREEN}================================================================{NC}

{CYAN}PHP-FPM Pools Installed:{NC}
  - Admin Pool:     /run/php/php{php_version}-fpm-admin.sock
  - Streaming Pool: /run/php/php{php_version}-fpm-streaming.sock

{CYAN}Nginx Configurations:{NC}
  - Main Config:    {fos_dir}/fospackv69/fos/nginx/conf/nginx.conf
  - Admin VHost:    {fos_dir}/install/config/nginx/fos-admin.conf
  - Streaming VHost:{fos_dir}/install/config/nginx/fos-streaming.conf
  - RTMP Config:    {fos_dir}/install/config/nginx/fos-rtmp.conf

{CYAN}Systemd Services:{NC}
  - fos-nginx.service (FOS custom nginx with HTTP-FLV)

{GREEN}================================================================{NC}

{BLUE}Next Steps:{NC}

  1. Verify PHP-FPM is running:
     {CYAN}sudo systemctl status php{php_version}-fpm{NC}

  2. Start FOS Nginx (after building or if binary exists):
     {CYAN}sudo systemctl start fos-nginx{NC}

  3. Check service status:
     {CYAN}sudo systemctl status fos-nginx{NC}

  4. View logs:
     {CYAN}tail -f {fos_dir}/logs/*.log{NC}

{GREEN}================================================================{NC}
""")


def main():
    parser = argparse.ArgumentParser(description='FOS-Streaming Service Configuration')
    parser.add_argument('--user', default=os.environ.get('USER', 'fosadmin'),
                        help='Username for FOS-Streaming (default: current user)')
    parser.add_argument('--fos-dir', default=None,
                        help='FOS-Streaming installation directory')
    parser.add_argument('--system-nginx', action='store_true',
                        help='Configure for system nginx instead of FOS nginx')
    args = parser.parse_args()

    print(f"""
{CYAN}================================================================{NC}
{CYAN}  FOS-Streaming v70 - Service Configuration{NC}
{CYAN}================================================================{NC}
""")

    # Check sudo
    check_sudo()
    log_success("Sudo access verified")

    # Determine FOS directory
    if args.fos_dir:
        fos_dir = Path(args.fos_dir)
    else:
        # Try to find it
        script_dir = Path(__file__).resolve().parent
        fos_dir = script_dir.parent

    if not fos_dir.exists():
        log_error(f"FOS directory not found: {fos_dir}")
        sys.exit(1)

    log_info(f"FOS Directory: {fos_dir}")
    log_info(f"User: {args.user}")

    # Detect PHP version
    php_version = detect_php_version()
    log_info(f"PHP Version: {php_version}")

    # Install configurations
    install_php_fpm_pools(fos_dir, args.user, php_version)
    install_nginx_configs(fos_dir, args.user, args.system_nginx)
    create_directories(fos_dir, args.user)
    create_systemd_services(fos_dir, args.user, php_version)
    restart_services(php_version)

    # Print summary
    print_summary(fos_dir, args.user, php_version)

    log_success("Service configuration completed!")


if __name__ == "__main__":
    main()
