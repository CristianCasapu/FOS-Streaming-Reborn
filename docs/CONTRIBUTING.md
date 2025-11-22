# Contributing to FOS-Streaming Reborn

Thank you for your interest in contributing to FOS-Streaming Reborn! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Branch Strategy](#branch-strategy)
- [Coding Standards](#coding-standards)
- [Commit Messages](#commit-messages)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on what is best for the community
- Show empathy towards other community members

## Getting Started

### Prerequisites

- Debian 12 (Bookworm) for production testing
- PHP 8.2+
- Node.js 20 LTS
- Composer 2.x
- Git
- Basic knowledge of PHP, JavaScript/Vue.js, and Nginx

### Setting Up Development Environment

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/YOUR_USERNAME/FOS-Streaming-Reborn.git
   cd FOS-Streaming-Reborn
   ```

2. **Add upstream remote**
   ```bash
   git remote add upstream https://github.com/CristianCasapu/FOS-Streaming-Reborn.git
   ```

3. **Install dependencies**
   ```bash
   # PHP dependencies
   composer install

   # Node dependencies
   npm install
   ```

4. **Set up environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

5. **Build frontend assets**
   ```bash
   npm run dev  # Development with hot reload
   # OR
   npm run build  # Production build
   ```

## Development Workflow

1. **Sync with upstream**
   ```bash
   git checkout develop
   git fetch upstream
   git merge upstream/develop
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   # OR
   git checkout -b fix/bug-description
   ```

3. **Make your changes**
   - Write clean, readable code
   - Follow coding standards (see below)
   - Add tests if applicable
   - Update documentation if needed

4. **Test your changes**
   ```bash
   # PHP syntax check
   composer test

   # Frontend build
   npm run build

   # Manual testing
   # Install on test server and verify functionality
   ```

5. **Commit your changes**
   ```bash
   git add .
   git commit -m "feat: add new feature description"
   ```

6. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```

7. **Open a Pull Request**
   - Target the `develop` branch (NOT master)
   - Provide clear description of changes
   - Reference any related issues

## Branch Strategy

We follow Git Flow branching model:

### Main Branches

- **`master`** - Production releases only
  - Tagged with version numbers (v70.0.0, v70.1.0, etc.)
  - Always stable and tested
  - Protected branch - no direct commits

- **`develop`** - Active development (default branch)
  - Integration branch for features
  - Should be relatively stable
  - Base for all feature branches

### Supporting Branches

- **`feature/*`** - New features
  - Branch from: `develop`
  - Merge to: `develop`
  - Naming: `feature/short-description`

- **`fix/*`** - Bug fixes
  - Branch from: `develop`
  - Merge to: `develop`
  - Naming: `fix/bug-description`

- **`hotfix/*`** - Critical production fixes
  - Branch from: `master`
  - Merge to: `master` AND `develop`
  - Naming: `hotfix/issue-description`

- **`release/*`** - Release preparation
  - Branch from: `develop`
  - Merge to: `master` AND `develop`
  - Naming: `release/v70.1.0`

## Coding Standards

### PHP

Follow PSR-12 coding standard:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'streams';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'url',
        'category_id',
    ];

    /**
     * Get the category for the stream.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

**Key points:**
- Use 4 spaces for indentation (no tabs)
- Opening braces on same line for methods
- One blank line between methods
- Type hints for all parameters and return types when possible
- Use Eloquent ORM (no raw SQL queries)
- Use environment variables via `env()` helper

### JavaScript/Vue.js

Follow Vue.js 3 Composition API style:

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { streamsAPI } from '../services/api';

const streams = ref([]);
const loading = ref(true);

const fetchStreams = async () => {
    try {
        loading.value = true;
        const response = await streamsAPI.getAll();
        streams.value = response.data.data;
    } catch (error) {
        console.error('Error fetching streams:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchStreams();
});
</script>

<template>
    <div class="streams-list">
        <div v-if="loading">Loading...</div>
        <div v-else>
            <StreamCard
                v-for="stream in streams"
                :key="stream.id"
                :stream="stream"
            />
        </div>
    </div>
</template>
```

**Key points:**
- Use Composition API with `<script setup>`
- Use TailwindCSS for styling
- Use Pinia for state management
- Use axios for API calls
- Component names in PascalCase
- Props with type definitions

### Shell Scripts

```bash
#!/bin/bash
set -e  # Exit on error

# Use descriptive variable names
readonly INSTALL_DIR="/home/fosstreaming/fos"
readonly PHP_VERSION="8.2"

# Function naming: use snake_case
install_dependencies() {
    local packages="$1"
    sudo apt-get install -y $packages
}

# Always quote variables
echo "Installing to: ${INSTALL_DIR}"
```

## Commit Messages

Follow Conventional Commits specification:

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, no logic change)
- **refactor**: Code refactoring
- **perf**: Performance improvements
- **test**: Adding or updating tests
- **chore**: Build process or auxiliary tool changes
- **ci**: CI/CD changes

### Examples

```bash
# Simple feature
git commit -m "feat: add stream search functionality"

# Bug fix with description
git commit -m "fix: resolve nginx config path issue

The nginx configuration was using hardcoded paths which failed
on custom installations. Now uses environment variables."

# Breaking change
git commit -m "feat!: migrate to Vue Router history mode

BREAKING CHANGE: URLs no longer use hash (#) routing.
Server configuration must support SPA routing."
```

## Pull Request Process

1. **Before submitting:**
   - Ensure all tests pass
   - Update documentation if needed
   - Verify code follows style guidelines
   - Rebase on latest `develop` if needed

2. **PR Title:**
   - Follow conventional commit format
   - Be descriptive and concise
   - Example: `feat: add user authentication system`

3. **PR Description:**
   ```markdown
   ## Description
   Brief description of changes

   ## Changes
   - Added feature X
   - Fixed bug Y
   - Updated documentation

   ## Testing
   - [ ] Manual testing completed
   - [ ] No console errors
   - [ ] Works on Debian 12

   ## Related Issues
   Closes #123
   ```

4. **Review process:**
   - Maintainer will review within 1-7 days
   - Address feedback promptly
   - Keep discussion focused and respectful

5. **After approval:**
   - Maintainer will merge (do not merge yourself)
   - Delete your feature branch
   - Pull latest `develop`

## Testing

### Manual Testing

1. **Install on test server**
   ```bash
   # Use the installer on a fresh Debian 12 instance
   ./install/debian12-reborn
   ```

2. **Test all affected features**
   - Create/edit/delete streams
   - Test authentication
   - Verify API responses
   - Check frontend rendering
   - Test streaming functionality (RTMP/HLS/HTTP-FLV)

3. **Browser testing**
   - Chrome/Chromium (latest)
   - Firefox (latest)
   - Safari (if available)

### Automated Testing

```bash
# PHP tests (when implemented)
composer test

# Frontend linting
npm run lint

# Build verification
npm run build
```

## Questions?

- **GitHub Issues**: https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues
- **Discussions**: https://github.com/CristianCasapu/FOS-Streaming-Reborn/discussions

Thank you for contributing to FOS-Streaming Reborn! 🚀
