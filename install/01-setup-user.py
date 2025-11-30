#!/usr/bin/env python3
"""
FOS-Streaming v70 - Step 1: System User Setup
==============================================

This script must be run as root. It:
1. Creates a dedicated system user for FOS-Streaming
2. Adds the user to the sudo group
3. Configures passwordless sudo access
4. Generates and displays a secure password

Usage:
    sudo python3 01-setup-user.py [username]

Arguments:
    username    Optional. Default: fosadmin

Example:
    sudo python3 01-setup-user.py
    sudo python3 01-setup-user.py myuser
"""

import os
import sys
import subprocess
import secrets
import string
import pwd
import grp
import crypt
import re
from pathlib import Path

# ANSI Colors
RED = '\033[0;31m'
GREEN = '\033[0;32m'
YELLOW = '\033[1;33m'
BLUE = '\033[0;34m'
CYAN = '\033[0;36m'
MAGENTA = '\033[0;35m'
NC = '\033[0m'  # No Color

DEFAULT_USERNAME = "fosadmin"
MIN_UID = 1000
MAX_UID = 60000


def print_banner():
    """Display the installation banner."""
    print(f"""
{CYAN}================================================================{NC}
{CYAN}  FOS-Streaming v70 - User Setup{NC}
{CYAN}================================================================{NC}
""")


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


def check_root():
    """Verify script is running as root."""
    if os.geteuid() != 0:
        log_error("This script must be run as root!")
        log_info("Usage: sudo python3 01-setup-user.py [username]")
        sys.exit(1)


def generate_password(length=24):
    """Generate a cryptographically secure password."""
    alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
    return ''.join(secrets.choice(alphabet) for _ in range(length))


def is_valid_username(username):
    """Validate username format."""
    # Must start with letter, contain only lowercase, digits, underscore, hyphen
    # Max 32 characters
    pattern = r'^[a-z][a-z0-9_-]{0,31}$'
    return bool(re.match(pattern, username))


def user_exists(username):
    """Check if a system user exists."""
    try:
        pwd.getpwnam(username)
        return True
    except KeyError:
        return False


def get_existing_regular_users():
    """Find existing non-root users with UID >= 1000."""
    users = []
    for user in pwd.getpwall():
        if MIN_UID <= user.pw_uid < MAX_UID:
            # Check if user has a valid shell
            if user.pw_shell and not any(x in user.pw_shell for x in ['nologin', 'false']):
                users.append(user.pw_name)
    return users


def run_cmd(cmd, capture=False, check=True):
    """Run a shell command."""
    try:
        result = subprocess.run(
            cmd,
            shell=isinstance(cmd, str),
            capture_output=capture,
            text=True,
            check=check
        )
        return result.stdout.strip() if capture else True
    except subprocess.CalledProcessError as e:
        if capture:
            return None
        log_error(f"Command failed: {cmd}")
        if e.stderr:
            print(e.stderr)
        return False


def create_user(username, password):
    """Create a new system user with home directory."""
    log_info(f"Creating user '{username}'...")

    # Create user with home directory and bash shell
    if not run_cmd(['useradd', '-m', '-s', '/bin/bash', username]):
        return False

    # Set password using chpasswd
    proc = subprocess.run(
        ['chpasswd'],
        input=f"{username}:{password}",
        text=True,
        capture_output=True
    )

    if proc.returncode != 0:
        log_error("Failed to set password")
        # Cleanup: remove the user
        run_cmd(['userdel', '-r', username], check=False)
        return False

    log_success(f"User '{username}' created successfully")
    return True


def add_to_sudo_group(username):
    """Add user to sudo group."""
    log_info(f"Adding '{username}' to sudo group...")

    # Check if sudo group exists
    try:
        grp.getgrnam('sudo')
        group_name = 'sudo'
    except KeyError:
        # Try 'wheel' group (used on some systems)
        try:
            grp.getgrnam('wheel')
            group_name = 'wheel'
        except KeyError:
            log_error("Neither 'sudo' nor 'wheel' group exists")
            return False

    if not run_cmd(['usermod', '-aG', group_name, username]):
        return False

    log_success(f"User '{username}' added to '{group_name}' group")
    return True


def configure_sudoers(username):
    """Configure passwordless sudo for the user."""
    log_info(f"Configuring passwordless sudo for '{username}'...")

    sudoers_dir = Path('/etc/sudoers.d')
    sudoers_file = sudoers_dir / username

    # Ensure sudoers.d directory exists
    if not sudoers_dir.exists():
        log_error("/etc/sudoers.d does not exist")
        return False

    # Check if already configured
    if sudoers_file.exists():
        content = sudoers_file.read_text()
        if 'NOPASSWD: ALL' in content:
            log_info(f"User '{username}' already has NOPASSWD sudo access")
            return True

    # Write sudoers config
    sudoers_content = f"{username} ALL=(ALL) NOPASSWD: ALL\n"

    try:
        sudoers_file.write_text(sudoers_content)
        # Set proper permissions (must be 0440)
        os.chmod(sudoers_file, 0o440)
        log_success(f"Passwordless sudo configured for '{username}'")
        return True
    except Exception as e:
        log_error(f"Failed to configure sudoers: {e}")
        return False


def validate_sudoers(username):
    """Validate sudoers configuration using visudo."""
    log_info("Validating sudoers configuration...")

    result = run_cmd(['visudo', '-c'], capture=True, check=False)
    if result is None or 'parsed OK' not in str(result):
        log_error("Sudoers validation failed!")
        # Try to fix by removing the file
        sudoers_file = Path(f'/etc/sudoers.d/{username}')
        if sudoers_file.exists():
            sudoers_file.unlink()
            log_warn("Removed invalid sudoers file")
        return False

    log_success("Sudoers configuration validated")
    return True


def save_credentials(username, password):
    """Save credentials to a secure file for reference."""
    creds_file = Path(f'/root/.fos_user_credentials_{username}')

    content = f"""FOS-Streaming User Credentials
=============================
Username: {username}
Password: {password}
Home: /home/{username}
Created: {subprocess.run(['date'], capture_output=True, text=True).stdout.strip()}

IMPORTANT: Delete this file after noting the password!
"""

    try:
        creds_file.write_text(content)
        os.chmod(creds_file, 0o600)
        log_info(f"Credentials saved to: {creds_file}")
        return True
    except Exception as e:
        log_warn(f"Could not save credentials file: {e}")
        return False


def display_summary(username, password, home_dir):
    """Display the installation summary."""
    print(f"""
{GREEN}================================================================{NC}
{GREEN}  User Setup Complete!{NC}
{GREEN}================================================================{NC}

  {CYAN}Username:{NC}  {username}
  {CYAN}Password:{NC}  {YELLOW}{password}{NC}
  {CYAN}Home:{NC}      {home_dir}

  {CYAN}Sudo:{NC}      Passwordless sudo enabled

{YELLOW}IMPORTANT: Save this password now! It will not be shown again.{NC}

{GREEN}================================================================{NC}

{BLUE}Next Steps:{NC}
  1. Log in as the new user:
     {CYAN}su - {username}{NC}

  2. Clone the FOS-Streaming repository (if not already done):
     {CYAN}git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git FOS-Streaming{NC}

  3. Run the dependencies installer:
     {CYAN}cd FOS-Streaming/install{NC}
     {CYAN}python3 02-install-deps.py{NC}

{GREEN}================================================================{NC}
""")


def prompt_username():
    """Prompt for username with validation."""
    print(f"""
{CYAN}User Creation{NC}
-------------
FOS-Streaming requires a non-root user to run securely.
""")

    # Check for existing users
    existing_users = get_existing_regular_users()
    if existing_users:
        print(f"Existing users found: {', '.join(existing_users)}")
        print("")
        response = input(f"Use existing user or create new? [existing/NEW]: ").strip().lower()
        if response in ['existing', 'e', 'exist']:
            if len(existing_users) == 1:
                return existing_users[0], None  # None means existing user
            print("Available users:", ', '.join(existing_users))
            while True:
                user = input("Enter username: ").strip()
                if user in existing_users:
                    return user, None
                log_error(f"User '{user}' not in list")

    # Create new user
    while True:
        username = input(f"Enter new username [{DEFAULT_USERNAME}]: ").strip()
        if not username:
            username = DEFAULT_USERNAME

        if not is_valid_username(username):
            log_error("Invalid username. Must start with a letter and contain only")
            log_error("lowercase letters, numbers, underscores, and hyphens (max 32 chars)")
            continue

        if user_exists(username):
            log_error(f"User '{username}' already exists")
            use_existing = input("Use this existing user? [y/N]: ").strip().lower()
            if use_existing in ['y', 'yes']:
                return username, None
            continue

        return username, generate_password()


def configure_existing_user(username):
    """Configure an existing user with sudo access."""
    log_step(f"Configuring existing user: {username}")

    # Add to sudo group
    if not add_to_sudo_group(username):
        log_warn("Could not add to sudo group, but continuing...")

    # Configure passwordless sudo
    if not configure_sudoers(username):
        log_error("Failed to configure sudoers")
        return False

    # Validate
    if not validate_sudoers(username):
        return False

    home_dir = f"/home/{username}"

    print(f"""
{GREEN}================================================================{NC}
{GREEN}  Existing User Configured!{NC}
{GREEN}================================================================{NC}

  {CYAN}Username:{NC}  {username}
  {CYAN}Home:{NC}      {home_dir}
  {CYAN}Sudo:{NC}      Passwordless sudo enabled

{GREEN}================================================================{NC}

{BLUE}Next Steps:{NC}
  1. Switch to the user (if not already):
     {CYAN}su - {username}{NC}

  2. Clone the FOS-Streaming repository (if not already done):
     {CYAN}git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git FOS-Streaming{NC}

  3. Run the dependencies installer:
     {CYAN}cd FOS-Streaming/install{NC}
     {CYAN}python3 02-install-deps.py{NC}

{GREEN}================================================================{NC}
""")
    return True


def main():
    print_banner()
    check_root()

    # Get username from argument or prompt
    if len(sys.argv) > 1:
        username = sys.argv[1]
        if not is_valid_username(username):
            log_error(f"Invalid username: {username}")
            sys.exit(1)

        if user_exists(username):
            log_info(f"User '{username}' already exists, configuring...")
            if configure_existing_user(username):
                sys.exit(0)
            else:
                sys.exit(1)

        password = generate_password()
    else:
        username, password = prompt_username()

        if password is None:
            # Using existing user
            if configure_existing_user(username):
                sys.exit(0)
            else:
                sys.exit(1)

    # Create new user
    log_step(f"Creating user: {username}")

    if not create_user(username, password):
        log_error("Failed to create user")
        sys.exit(1)

    if not add_to_sudo_group(username):
        log_warn("Could not add to sudo group, but continuing...")

    if not configure_sudoers(username):
        log_error("Failed to configure sudoers")
        sys.exit(1)

    if not validate_sudoers(username):
        log_error("Sudoers validation failed")
        sys.exit(1)

    # Save credentials
    save_credentials(username, password)

    # Display summary
    home_dir = f"/home/{username}"
    display_summary(username, password, home_dir)

    log_success("User setup completed successfully!")
    sys.exit(0)


if __name__ == "__main__":
    main()
