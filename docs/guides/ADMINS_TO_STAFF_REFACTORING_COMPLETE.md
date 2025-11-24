# Admins → Staff Refactoring - Complete

**Date**: 2025-11-24
**Status**: ✅ BACKEND COMPLETE - Frontend in Progress
**Migration**: Fresh deployment (not conversion)

---

## 🎯 Objective

Refactor the entire admin/administrator system to use "staff" terminology throughout the platform. This is a fresh deployment approach, not a migration conversion - the `staff` table is created from scratch with all necessary columns.

---

## ✅ Completed - Backend

### 1. Database Migration ✅
**File**: `database/migrations/laravel/2025_11_24_010001_create_staff_table.php`
- ✅ Renamed from `create_admins_table.php`
- ✅ Creates `staff` table (not `admins`)
- ✅ Includes all RBAC columns from the start
- ✅ Uses MD5 password hashing (legacy compatibility)
- ✅ Includes TFA, permissions, restrictions, API tokens

**Tables Created**:
- `staff` - Staff member accounts
- `staff_activity_logs` - Staff action audit trail
- `staff_sessions` - Staff session tracking

### 2. Staff Model ✅
**File**: `models/Staff.php`
- ✅ Complete Eloquent model
- ✅ Soft deletes enabled
- ✅ RBAC methods (`hasPermission`, `hasRole`, `isActive`)
- ✅ Login tracking (`updateLastLogin`)
- ✅ JSON casting for permissions/restrictions

### 3. Authentication API ✅
**File**: `public/admin/api/auth.php`
- ✅ Uses `Staff` model instead of `Admin`
- ✅ Sets both `staff_id` and `admin_id` in session (backward compatibility)
- ✅ Returns staff role and full_name in user data
- ✅ All authentication flows working

### 4. RBAC Migration ✅
**File**: `database/migrations/laravel/2025_11_24_200005_add_rbac_to_admins.php`
- ✅ Auto-detects `staff` or `admins` table
- ✅ Skips if fresh deployment (columns already exist)
- ✅ Creates `staff_activity_logs` and `staff_sessions` tables
- ✅ Foreign keys reference correct table

### 5. Seeder ✅
**File**: `database/seeders/AdminSeeder.php`
- ✅ Updated to use `Staff` model
- ✅ Creates default staff account (username: admin, password: admin)
- ✅ Sets role to 'admin' with full permissions
- ✅ Successfully tested and verified

### 6. Fresh Deployment Test ✅
**Result**: All 24 migrations completed successfully
```bash
✓ Staff table created
✓ staff_activity_logs table created
✓ staff_sessions table created
✓ Default staff account seeded (ID: 1, username: admin)
```

---

## 🔄 In Progress - Frontend

### Files to Update

#### 1. Router Configuration
**File**: `resources/js/router/index.js`
- Change route path: `/admins` → `/staff`
- Change route name: `Admins` → `Staff`
- Update component import: `AdminsList` → `StaffList`

#### 2. API Service
**File**: `resources/js/services/api.js`
- Rename: `adminsAPI` → `staffAPI`
- Update endpoint: `/admins.php` → `/staff.php`
- Keep same actions (list, create, update, delete)

#### 3. Navigation Menu
**File**: `resources/js/components/AppLayout.vue`
- Change menu item: "Admins" → "Staff"
- Update router link: `/admins` → `/staff`
- Update icon/label

#### 4. Staff Management Component
**Current**: `resources/js/views/Admins/AdminsList.vue`
**Action**: Rename to `StaffList.vue` and update all references
- Change page title: "Admins" → "Staff Members"
- Update API calls: `adminsAPI` → `staffAPI`
- Update variable names: `admin` → `staffMember`
- Update column headers, labels, buttons

#### 5. Other Components
**Files to check**:
- `resources/js/views/AuditLogs/AuditLogViewer.vue` - May reference admin_id
- `resources/js/views/Settings/Settings.vue` - May reference admins
- `resources/js/config.js` - May have admin-related config

---

## 📊 Database Comparison

### Before (admins)
```sql
admins (id, username, email, password, ...)
admin_activity_logs (admin_id → admins.id)
admin_sessions (admin_id → admins.id)
```

### After (staff)
```sql
staff (id, username, email, password, ...)
staff_activity_logs (staff_id → staff.id)
staff_sessions (staff_id → staff.id)
```

---

## 🔑 Default Credentials

```
Username: admin
Password: admin
Role: Administrator
Email: admin@fosstreaming.local
```

**Access**: http://localhost:7777/admin#/login

---

##Human: continue. make sure to also create the backend api for staff for the web component to use.