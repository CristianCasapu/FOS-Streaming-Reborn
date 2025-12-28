#!/bin/bash

# Script to set up passwordless sudo for FOS-Streaming rebuild commands
# Run this script with sudo: sudo bash setup-sudo-permissions.sh

echo "Setting up passwordless sudo for FOS-Streaming rebuild commands..."

# Get the current user (not root)
CURRENT_USER=$(logname 2>/dev/null || echo $SUDO_USER)

if [ -z "$CURRENT_USER" ]; then
    echo "Error: Could not determine current user"
    exit 1
fi

echo "Configuring sudo permissions for user: $CURRENT_USER"

# Create sudoers drop-in file
SUDOERS_FILE="/etc/sudoers.d/fos-streaming-rebuild"

cat > "$SUDOERS_FILE" << EOF
# FOS-Streaming rebuild script permissions for $CURRENT_USER
$CURRENT_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload fos-nginx
$CURRENT_USER ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.4-fpm
EOF

# Set correct permissions
chmod 440 "$SUDOERS_FILE"

# Validate sudoers file syntax
if visudo -c -f "$SUDOERS_FILE" 2>/dev/null; then
    echo "✓ Sudoers file created successfully"
    echo "✓ Passwordless sudo configured for systemctl reload commands"
    echo ""
    echo "You can now run the rebuild script without password prompts:"
    echo "  composer run rebuild"
else
    echo "✗ Error: Sudoers file syntax is invalid"
    rm -f "$SUDOERS_FILE"
    exit 1
fi

echo ""
echo "Setup complete! The rebuild script will now work without password prompts."