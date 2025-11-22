#!/bin/bash

# FOS Streaming v70 - Development Environment Setup Script
# This script helps you quickly set up the local development environment

set -e

echo "================================================"
echo "FOS Streaming v70 - Development Setup"
echo "================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
check_php() {
    if command -v php >/dev/null 2>&1; then
        PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        info "PHP $PHP_VERSION detected"
        return 0
    else
        error "PHP not found. Please install PHP 8.4+"
        return 1
    fi
}

check_composer() {
    if command -v composer >/dev/null 2>&1; then
        COMPOSER_VERSION=$(composer --version | cut -d " " -f 3)
        info "Composer $COMPOSER_VERSION detected"
        return 0
    else
        error "Composer not found. Please install Composer 2.x"
        return 1
    fi
}

check_node() {
    if command -v node >/dev/null 2>&1; then
        NODE_VERSION=$(node -v)
        info "Node.js $NODE_VERSION detected"
        return 0
    else
        error "Node.js not found. Please install Node.js 18+"
        return 1
    fi
}

check_npm() {
    if command -v npm >/dev/null 2>&1; then
        NPM_VERSION=$(npm -v)
        info "NPM $NPM_VERSION detected"
        return 0
    else
        error "NPM not found. Please install NPM 9+"
        return 1
    fi
}

echo "Checking prerequisites..."
echo ""

ALL_CHECKS_PASSED=true

check_php || ALL_CHECKS_PASSED=false
check_composer || ALL_CHECKS_PASSED=false
check_node || ALL_CHECKS_PASSED=false
check_npm || ALL_CHECKS_PASSED=false

echo ""

if [ "$ALL_CHECKS_PASSED" = false ]; then
    error "Some prerequisites are missing. Please install them and try again."
    exit 1
fi

info "All prerequisites found!"
echo ""

# Install PHP dependencies
echo "================================================"
echo "Installing PHP dependencies..."
echo "================================================"
echo ""

if [ -d "vendor" ]; then
    warn "vendor/ directory exists. Skipping composer install."
    warn "Run 'composer install' manually if needed."
else
    info "Running composer install..."
    composer install
fi

echo ""

# Install Node.js dependencies
echo "================================================"
echo "Installing Node.js dependencies..."
echo "================================================"
echo ""

if [ -d "node_modules" ]; then
    warn "node_modules/ directory exists. Skipping npm install."
    warn "Run 'npm install' manually if needed."
else
    info "Running npm install..."
    npm install
fi

echo ""

# Setup .env file
echo "================================================"
echo "Setting up environment configuration..."
echo "================================================"
echo ""

if [ -f ".env" ]; then
    warn ".env file already exists. Skipping."
else
    info "Creating .env from .env.example..."
    cp .env.example .env
    info ".env file created!"
    warn "Please edit .env and configure your database settings."
fi

echo ""

# Create necessary directories
echo "================================================"
echo "Creating necessary directories..."
echo "================================================"
echo ""

info "Creating storage directories..."
mkdir -p storage/{logs,framework/{cache,sessions,views},app}
mkdir -p cache
mkdir -p public/build

info "Setting permissions..."
chmod -R 775 storage cache
chmod -R 775 public/build

echo ""

# Summary
echo "================================================"
echo "Setup Complete!"
echo "================================================"
echo ""
info "Development environment is ready!"
echo ""
echo "Next steps:"
echo ""
echo "1. Configure database in .env:"
echo "   nano .env"
echo ""
echo "2. Create database:"
echo "   mysql -u root -p -e 'CREATE DATABASE fos_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'"
echo ""
echo "3. Start development servers:"
echo "   Terminal 1: npm run dev"
echo "   Terminal 2: php artisan serve"
echo ""
echo "4. Access application:"
echo "   http://localhost:8000"
echo ""
echo "For more information, see:"
echo "   docs/guides/LOCAL_DEVELOPMENT_GUIDE.md"
echo ""
info "Happy coding! 🚀"
echo ""
