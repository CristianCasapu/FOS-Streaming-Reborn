# FOS-Streaming v70 Documentation

Welcome to the FOS-Streaming v70 documentation. All project documentation is organized in this directory.

## 📚 Documentation Structure

```
/docs
├── README.md              # This file - Documentation index
├── /guides                # User guides and tutorials
├── /database              # Database documentation
└── /install               # Installation documentation
```

---

## 🚀 Quick Start

### New Users
1. **Installation Guide** → [/install/INSTALLATION_COMPARISON.md](install/INSTALLATION_COMPARISON.md)
2. **Environment Setup** → [/guides/ENV_CONFIGURATION_GUIDE.md](guides/ENV_CONFIGURATION_GUIDE.md)
3. **Quick Reference** → [/guides/QUICK_REFERENCE.md](guides/QUICK_REFERENCE.md)

### Developers
1. **Laravel Components** → [/guides/LARAVEL_COMPONENTS_USAGE.md](guides/LARAVEL_COMPONENTS_USAGE.md)
2. **Laravel Sail** → [/guides/LARAVEL_SAIL_GUIDE.md](guides/LARAVEL_SAIL_GUIDE.md)
3. **Security Features** → [/guides/SECURITY_FEATURES.md](guides/SECURITY_FEATURES.md)

---

## 📖 Documentation by Category

### Installation & Setup

| Document | Description |
|----------|-------------|
| [Installation Comparison](install/INSTALLATION_COMPARISON.md) | Comparison between debian12 and debian12-enhanced |
| [Enhanced Installation Guide](install/README-ENHANCED.md) | debian12-enhanced installation guide |
| [Quick Start](guides/QUICKSTART.md) | Fast track installation guide |
| [Environment Configuration](guides/ENV_CONFIGURATION_GUIDE.md) | .env file setup and usage |

### Laravel & Modern PHP

| Document | Description |
|----------|-------------|
| [Laravel Integration](guides/LARAVEL_INTEGRATION.md) | How Laravel components are integrated |
| [Laravel Components Usage](guides/LARAVEL_COMPONENTS_USAGE.md) | Using Laravel validation, cache, queue, etc. |
| [Laravel Sail Guide](guides/LARAVEL_SAIL_GUIDE.md) | Docker development environment |

### Database

| Document | Description |
|----------|-------------|
| [UTF8MB4 Migration Guide](database/UTF8MB4_MIGRATION_GUIDE.md) | Converting to UTF8MB4 charset |
| [UTF8MB4 Update Summary](guides/UTF8MB4_UPDATE_SUMMARY.md) | Summary of UTF8MB4 changes |

### Security

| Document | Description |
|----------|-------------|
| [Security Features](guides/SECURITY_FEATURES.md) | Overview of security features |
| [Security Improvements](guides/SECURITY_IMPROVEMENTS.md) | Recent security enhancements |
| [Security Implementation Summary](guides/SECURITY_IMPLEMENTATION_SUMMARY.md) | Technical implementation details |
| [Secure Streaming Plan](guides/SECURE_STREAMING_PLAN.md) | Security planning document |

### Migration & Upgrades

| Document | Description |
|----------|-------------|
| [Migration Plan](guides/MIGRATION_PLAN.md) | Overall migration strategy |
| [Nginx HTTP-FLV Migration](guides/NGINX_HTTP_FLV_MIGRATION.md) | Nginx module migration guide |
| [Vue Migration Guide](guides/VUE_MIGRATION_GUIDE.md) | Frontend framework migration |
| [Legacy Support Removal](guides/LEGACY_SUPPORT_REMOVAL.md) | Deprecated feature removal |

### Reference & Summaries

| Document | Description |
|----------|-------------|
| [Quick Reference](guides/QUICK_REFERENCE.md) | Quick command reference |
| [Documentation Index](guides/DOCUMENTATION_INDEX.md) | Complete documentation listing |
| [Update Summary](guides/UPDATE_SUMMARY.md) | Summary of updates |
| [Implementation Summary](guides/IMPLEMENTATION_SUMMARY.md) | Implementation details |
| [Final Summary](guides/FINAL_SUMMARY.md) | Complete project summary |

### Version & Project Status

| Document | Description |
|----------|-------------|
| [Release v70](guides/RELEASE_V70.md) | Version 70 release notes |
| [V70 Upgrade Complete](guides/V70_UPGRADE_COMPLETE.md) | Upgrade completion status |
| [Project Status](guides/PROJECT_STATUS.md) | Current project status |
| [Verification Report](guides/VERIFICATION_REPORT.md) | System verification results |
| [Changelog](guides/CHANGELOG.md) | Complete change history |

### Git & Version Control

| Document | Description |
|----------|-------------|
| [.gitignore Guide](guides/GITIGNORE_GUIDE.md) | Understanding .gitignore rules |
| [.gitignore Changes](guides/GITIGNORE_CHANGES.md) | Recent .gitignore updates |

---

## 🔍 Documentation by Topic

### Getting Started
- [Installation Comparison](install/INSTALLATION_COMPARISON.md)
- [Quick Start](guides/QUICKSTART.md)
- [Environment Configuration](guides/ENV_CONFIGURATION_GUIDE.md)

### Development
- [Laravel Components Usage](guides/LARAVEL_COMPONENTS_USAGE.md)
- [Laravel Sail Guide](guides/LARAVEL_SAIL_GUIDE.md)
- [Laravel Integration](guides/LARAVEL_INTEGRATION.md)

### Database Administration
- [UTF8MB4 Migration Guide](database/UTF8MB4_MIGRATION_GUIDE.md)
- [UTF8MB4 Update Summary](guides/UTF8MB4_UPDATE_SUMMARY.md)

### Security
- [Security Features](guides/SECURITY_FEATURES.md)
- [Security Improvements](guides/SECURITY_IMPROVEMENTS.md)
- [Secure Streaming Plan](guides/SECURE_STREAMING_PLAN.md)

### System Administration
- [Enhanced Installation Guide](install/README-ENHANCED.md)
- [Nginx HTTP-FLV Migration](guides/NGINX_HTTP_FLV_MIGRATION.md)
- [Quick Reference](guides/QUICK_REFERENCE.md)

---

## 🎯 Common Tasks

### Installation
```bash
# Enhanced installation (recommended)
chmod +x install/debian12-enhanced
./install/debian12-enhanced
```
📖 **Documentation:** [Installation Comparison](install/INSTALLATION_COMPARISON.md)

### Environment Configuration
```bash
# Copy and edit .env
cp .env.example .env
nano .env
```
📖 **Documentation:** [Environment Configuration Guide](guides/ENV_CONFIGURATION_GUIDE.md)

### Database Migration to UTF8MB4
```bash
# Backup first!
mysqldump -u root -p fos_streaming > backup.sql

# Run migration
mysql -u root -p fos_streaming < database/migrations/convert_to_utf8mb4.sql
```
📖 **Documentation:** [UTF8MB4 Migration Guide](database/UTF8MB4_MIGRATION_GUIDE.md)

### Laravel Sail (Docker Development)
```bash
# Start Sail
./vendor/bin/sail up -d

# Access application
http://localhost:7777
```
📖 **Documentation:** [Laravel Sail Guide](guides/LARAVEL_SAIL_GUIDE.md)

---

## 📝 Documentation Standards

### Creating New Documentation

1. **Determine Category:**
   - Feature guides → `/docs/guides/`
   - Database docs → `/docs/database/`
   - Installation → `/docs/install/`

2. **File Naming:**
   - Use UPPERCASE with underscores: `FEATURE_NAME.md`
   - Be descriptive: `UTF8MB4_MIGRATION_GUIDE.md`
   - Avoid abbreviations in filenames

3. **Document Structure:**
   ```markdown
   # Title

   ## Overview
   Brief description

   ## Table of Contents (for long docs)
   - [Section 1](#section-1)
   - [Section 2](#section-2)

   ## Sections
   Content...

   ## See Also
   Links to related documentation
   ```

4. **Update This Index:**
   - Add entry to appropriate category table
   - Add to topic-based navigation
   - Update common tasks if applicable

### Documentation Policy

✅ **DO:**
- Keep documentation in `/docs` subdirectories
- Reference other docs with relative links
- Include practical examples
- Update index when adding new docs
- Use consistent formatting

❌ **DON'T:**
- Create docs in project root (except README.md and CLAUDE.md)
- Duplicate content across multiple files
- Create separate docs for minor features
- Use absolute URLs for internal links

---

## 🔗 External Resources

### PHP & Laravel
- [Laravel Documentation](https://laravel.com/docs)
- [PHP 8.4 Documentation](https://www.php.net/docs.php)
- [Composer Documentation](https://getcomposer.org/doc/)

### Database
- [MariaDB Documentation](https://mariadb.org/documentation/)
- [MySQL 8.0 Reference](https://dev.mysql.com/doc/refman/8.0/en/)

### Streaming
- [Nginx HTTP-FLV Module](https://github.com/winshining/nginx-http-flv-module)
- [FFmpeg Documentation](https://ffmpeg.org/documentation.html)

### Development Tools
- [Laravel Sail](https://laravel.com/docs/sail)
- [Docker Documentation](https://docs.docker.com/)
- [NVM (Node Version Manager)](https://github.com/nvm-sh/nvm)

---

## 💡 Tips

### Finding Documentation

1. **Use this index** to locate documentation by category or topic
2. **Search the documentation:**
   ```bash
   grep -r "search term" /docs/
   ```
3. **Check the main README.md** in project root for project overview

### Contributing to Documentation

1. Follow documentation standards above
2. Keep documentation current with code changes
3. Include practical examples
4. Link to related documentation
5. Update this index when adding new docs

### Getting Help

1. Check relevant documentation section
2. Review related guides and summaries
3. Check CLAUDE.md for project context
4. Search existing documentation

---

## 📊 Documentation Statistics

- **Total Guides:** 25+
- **Installation Docs:** 3
- **Database Docs:** 2
- **Security Docs:** 4
- **Migration Guides:** 4
- **Reference Docs:** 5+

---

## 🔄 Recent Updates

**2025-11-22:**
- ✅ Reorganized all documentation into `/docs` directory
- ✅ Created `/docs/guides`, `/docs/database`, `/docs/install` structure
- ✅ Updated UTF8MB4 documentation
- ✅ Enhanced installation script documentation
- ✅ Created CLAUDE.md for AI context

---

## 📧 Documentation Feedback

If you find issues with documentation:
1. Check for recent updates in related docs
2. Verify you're using the latest version
3. Report issues via project issue tracker

---

**Last Updated:** 2025-11-22
**Documentation Version:** v70
**Status:** Active and maintained
