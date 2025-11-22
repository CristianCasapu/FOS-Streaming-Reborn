# FOS-Streaming v70 - Vue.js Migration Plan

## Overview
Migrate all legacy PHP/Blade pages to modern Laravel API + Vue.js 3 SPA architecture.

**Status:** In Progress
**Started:** 2025-11-22
**Target Completion:** TBD

---

## Migration Strategy

### Phase 1: Core Admin Pages ✅ (COMPLETED)
- [x] Dashboard (DashboardEnhanced.vue)
- [x] Login (Login.vue)
- [x] Authentication API (auth.php)
- [x] Dashboard API (dashboard.php)

### Phase 2: Stream Management (HIGH PRIORITY)
**Pages to Migrate:**
1. **Streams List** (`streams.php` → `Streams.vue`)
   - View all streams with filters (online/offline/all)
   - Pagination and search
   - Actions: Edit, Delete, View Details

2. **Stream Create/Edit** (`manage_stream.php` → `StreamForm.vue`)
   - Create new stream
   - Edit existing stream
   - Stream configuration (RTMP, HLS, HTTP-FLV)

3. **Stream Importer** (`stream_importer.php` → `StreamImporter.vue`)
   - Import streams from M3U playlist
   - Bulk import functionality

**API Endpoints Needed:**
- `/public/admin/api/streams.php`
  - `?action=list` - Get all streams with filters
  - `?action=get&id=X` - Get single stream
  - `?action=create` - Create new stream
  - `?action=update&id=X` - Update stream
  - `?action=delete&id=X` - Delete stream
  - `?action=import` - Import from M3U

### Phase 3: User Management (HIGH PRIORITY)
**Pages to Migrate:**
1. **Users List** (`users.php` → `Users.vue`)
   - View all users
   - Filter by status (active/inactive)
   - Search functionality

2. **User Create/Edit** (`manage_user.php` → `UserForm.vue`)
   - Create new user
   - Edit user details
   - Assign categories
   - Set expiration dates

**API Endpoints Needed:**
- `/public/admin/api/users.php` (already defined, needs implementation)
  - `?action=list`
  - `?action=get&id=X`
  - `?action=create`
  - `?action=update&id=X`
  - `?action=delete&id=X`

### Phase 4: Category Management (MEDIUM PRIORITY)
**Pages to Migrate:**
1. **Categories List** (`categories.php` → `Categories.vue`)
   - View all categories
   - Stream count per category

2. **Category Create/Edit** (`manage_category.php` → `CategoryForm.vue`)
   - Create new category
   - Edit category details

**API Endpoints Needed:**
- `/public/admin/api/categories.php`
  - `?action=list`
  - `?action=get&id=X`
  - `?action=create`
  - `?action=update&id=X`
  - `?action=delete&id=X`

### Phase 5: Transcode Profiles (MEDIUM PRIORITY)
**Pages to Migrate:**
1. **Transcodes List** (`transcodes.php` → `Transcodes.vue`)
2. **Transcode Create/Edit** (`manage_transcode.php` → `TranscodeForm.vue`)

**API Endpoints Needed:**
- `/public/admin/api/transcodes.php`

### Phase 6: Security & IP Management (HIGH PRIORITY)
**Pages to Migrate:**
1. **IP Blocks List** (`ipblocks.php` → `IPBlocks.vue`)
   - View banned/whitelisted IPs
   - Add/Remove IPs

2. **IP Block Create/Edit** (`manage_ipblock.php` → `IPBlockForm.vue`)
3. **User Agent Blocks** (`useragentblocks.php` → `UserAgentBlocks.vue`)
4. **Security Settings** (`security_settings.php` → `SecuritySettings.vue`)

**API Endpoints Needed:**
- `/public/admin/api/ipblocks.php`
- `/public/admin/api/useragentblocks.php`
- `/public/admin/api/security.php`

### Phase 7: Admin Management (MEDIUM PRIORITY)
**Pages to Migrate:**
1. **Admins List** (`admins.php` → `Admins.vue`)
2. **Admin Create/Edit** (`manage_admin.php` → `AdminForm.vue`)

**API Endpoints Needed:**
- `/public/admin/api/admins.php`

### Phase 8: Activities & Monitoring (LOW PRIORITY)
**Pages to Migrate:**
1. **Activities List** (`activities.php` → `Activities.vue`)
   - View user streaming activities
   - Filter by date/user/stream

**API Endpoints Needed:**
- `/public/admin/api/activities.php`

### Phase 9: Settings & Configuration (MEDIUM PRIORITY)
**Pages to Migrate:**
1. **System Settings** (`settings.php` → `Settings.vue`)
   - Server configuration
   - Streaming settings
   - Port configuration

**API Endpoints Needed:**
- `/public/admin/api/settings.php`

### Phase 10: Utilities (LOW PRIORITY)
**Pages to Keep/Convert:**
1. **Clients Generator** (`clientsgen.php`)
   - May need to be kept as standalone or API endpoint

2. **API Endpoint** (`api.php`)
   - Review and possibly merge into new API structure

3. **Cron Jobs** (`cron.php`)
   - Keep as CLI script

4. **Installation** (`install_database_tables.php`)
   - Keep as one-time setup script

---

## Component Structure

### Vue Components Directory Layout
```
/resources/js/
├── views/
│   ├── Dashboard.vue (DashboardEnhanced.vue) ✅
│   ├── Login.vue ✅
│   ├── Streams/
│   │   ├── StreamsList.vue
│   │   ├── StreamForm.vue
│   │   └── StreamImporter.vue
│   ├── Users/
│   │   ├── UsersList.vue
│   │   └── UserForm.vue
│   ├── Categories/
│   │   ├── CategoriesList.vue
│   │   └── CategoryForm.vue
│   ├── Transcodes/
│   │   ├── TranscodesList.vue
│   │   └── TranscodeForm.vue
│   ├── Security/
│   │   ├── IPBlocks.vue
│   │   ├── IPBlockForm.vue
│   │   ├── UserAgentBlocks.vue
│   │   └── SecuritySettings.vue
│   ├── Admins/
│   │   ├── AdminsList.vue
│   │   └── AdminForm.vue
│   ├── Activities.vue
│   └── Settings.vue
├── components/
│   ├── DataTable.vue (reusable table component)
│   ├── FormInput.vue (reusable form inputs)
│   ├── Modal.vue (reusable modal)
│   ├── Alert.vue (reusable alerts)
│   ├── LoadingSpinner.vue
│   └── ConfirmDialog.vue
├── stores/
│   ├── auth.js ✅
│   ├── streams.js
│   ├── users.js
│   ├── categories.js
│   ├── security.js
│   └── settings.js
├── services/
│   └── api.js ✅ (expanded with all endpoints)
└── router/
    └── index.js ✅ (expand with all routes)
```

### API Endpoints Directory Layout
```
/public/admin/api/
├── auth.php ✅
├── dashboard.php ✅
├── streams.php (to create)
├── users.php (to create)
├── categories.php (to create)
├── transcodes.php (to create)
├── ipblocks.php (to create)
├── useragentblocks.php (to create)
├── admins.php (to create)
├── activities.php (to create)
├── settings.php (to create)
├── security.php (to create)
└── middleware.php ✅
```

---

## Migration Checklist Per Page

For each page migration, follow this checklist:

### Backend (API)
- [ ] Create API endpoint file (`/public/admin/api/[name].php`)
- [ ] Implement `list` action (with pagination, search, filters)
- [ ] Implement `get` action (single item)
- [ ] Implement `create` action (with validation)
- [ ] Implement `update` action (with validation)
- [ ] Implement `delete` action
- [ ] Add authentication check (`logincheck()`)
- [ ] Add JSON response headers
- [ ] Add error handling
- [ ] Test all endpoints with curl/Postman

### Frontend (Vue)
- [ ] Create Vue component (`/resources/js/views/[Name].vue`)
- [ ] Add to router (`/resources/js/router/index.js`)
- [ ] Add API methods to service (`/resources/js/services/api.js`)
- [ ] Create Pinia store if needed (`/resources/js/stores/[name].js`)
- [ ] Implement list view with table
- [ ] Implement create/edit form
- [ ] Implement delete confirmation
- [ ] Add loading states
- [ ] Add error handling
- [ ] Add success notifications
- [ ] Test all CRUD operations

### Cleanup
- [ ] Remove old PHP controller file
- [ ] Remove old Blade view file
- [ ] Update navigation links
- [ ] Test production build
- [ ] Update documentation

---

## Files to DELETE After Migration

### Legacy PHP Controllers (Root Level)
```
/dashboard.php (keep temporarily for reference)
/streams.php
/manage_stream.php
/stream_importer.php
/users.php
/manage_user.php
/categories.php
/manage_category.php
/transcodes.php
/manage_transcode.php
/ipblocks.php
/manage_ipblock.php
/useragentblocks.php
/manage_useragentblock.php
/admins.php
/manage_admin.php
/activities.php
/settings.php
/security_settings.php
```

### Legacy Blade Views
```
/views/dashboard.blade.php (keep for reference)
/views/streams.blade.php
/views/manage_stream.blade.php
/views/stream_importer.blade.php
/views/users.blade.php
/views/manage_user.blade.php
/views/categories.blade.php
/views/manage_category.blade.php
/views/transcodes.blade.php
/views/manage_transcode.blade.php
/views/ipblocks.blade.php
/views/manage_ipblock.blade.php
/views/useragentblocks.blade.php
/views/manage_useragentblock.blade.php
/views/admins.blade.php
/views/manage_admin.blade.php
/views/activities.blade.php
/views/manage_settings.blade.php
/views/main.blade.php (old navigation, replaced by Vue components)
```

### Files to KEEP
```
/config.php (application bootstrap)
/helpers.php (helper functions)
/functions.php (legacy helpers - evaluate for removal later)
/index.php (entry point - will redirect to Vue SPA)
/api.php (evaluate for merging)
/cron.php (background jobs)
/install_database_tables.php (setup script)
/play.php (streaming player - evaluate)
/playlist.php (M3U playlist - evaluate)
/retrieve.php (evaluate)
/stream.php (evaluate)
/clientsgen.php (evaluate)
/migrate_passwords.php (one-time script)
/server.php (PHP built-in server)
```

---

## Testing Strategy

### Unit Testing
- Test all API endpoints individually
- Test Vue components with Vitest
- Test Pinia stores

### Integration Testing
- Test complete CRUD flows
- Test authentication flows
- Test error scenarios

### User Acceptance Testing
- Test all admin workflows
- Verify data integrity
- Performance testing

---

## Rollback Plan

1. Keep git branch of old version
2. Keep old files until all features migrated and tested
3. Database migrations should be reversible
4. Document any breaking changes

---

## Progress Tracking

### Completed ✅
- [x] Dashboard page and API
- [x] Authentication system
- [x] Project architecture documentation (CLAUDE.md)

### In Progress 🔄
- [ ] Migration plan documentation

### Not Started 📋
- [ ] All other pages listed above

---

## Success Criteria

Migration is complete when:
1. All admin features work in Vue.js SPA
2. No legacy PHP pages remain (except utilities)
3. All API endpoints documented
4. All tests passing
5. Production build successful
6. Performance acceptable (< 3s page loads)
7. No console errors in production
8. All old files removed from repository

---

## Notes

- Always maintain backwards compatibility during migration
- Test each migration thoroughly before moving to next
- Keep stakeholders informed of progress
- Document any API changes
- Ensure proper error handling throughout
- Mobile responsiveness is required for all new pages
