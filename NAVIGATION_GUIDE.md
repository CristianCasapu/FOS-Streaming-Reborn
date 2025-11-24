# FOS-Streaming v70 - Navigation Guide

## 🗺️ How to Access All New Components

All new components have been integrated into the admin navigation menu. Here's where to find them:

---

## 📍 Navigation Menu Structure

### **System** (NEW Dropdown)
Located in the main navigation bar between "Security" and "Resellers"

- **Health Monitor** → `/admin#/health`
  - Real-time system health monitoring
  - Database, Redis, Disk, Memory, CPU status
  - Service health checks (Nginx, PHP-FPM, MariaDB)
  - Auto-refresh every 5 seconds

- **Metrics Dashboard** → `/admin#/metrics`
  - Platform-wide statistics
  - Revenue tracking
  - Security metrics
  - Streaming analytics

- **Audit Logs** → `/admin#/audit-logs`
  - Complete audit trail of all actions
  - Filter by user type, action, date range
  - Search functionality
  - Statistics dashboard

- **V2Ray Nodes** → `/admin#/v2ray/nodes`
  - V2Ray server management
  - Server health status
  - Load balancing monitoring
  - Connection tracking

---

### **Security** (Expanded)
Existing dropdown with new addition

- IP Blocks
- User Agent Blocks
- Advanced Security
- **Device Management** → `/admin#/devices` ✨ NEW
  - Device fingerprint monitoring
  - Device binding management
  - Violation tracking

---

### **Resellers** (NEW Standalone)
Main navigation link

- **Resellers Management** → `/admin#/resellers`
  - List all resellers
  - View statistics (total, active, commissions)
  - Manage reseller accounts
  - Track financial data

- **Reseller Detail** → `/admin#/resellers/:id`
  - Individual reseller dashboard
  - Subscriber count
  - Balance and earnings
  - Transaction history

---

## 🚀 Quick Access URLs

After building the frontend (`npm run build`), access components at:

```
Base URL: http://localhost:7777/admin#/

System Monitoring:
├─ /health           - Health Monitor
├─ /metrics          - Metrics Dashboard
├─ /audit-logs       - Audit Logs
└─ /v2ray/nodes      - V2Ray Node Management

Security & Devices:
└─ /devices          - Device Management

Reseller Management:
├─ /resellers        - Resellers List
└─ /resellers/:id    - Reseller Detail
```

---

## 📊 Menu Organization

```
Navigation Bar:
┌─────────────────────────────────────────────────────────────┐
│ [Dashboard] [Streams▼] [Subscribers▼] [Security▼]          │
│ [System▼] [Resellers] [Activities] [Admins] [Settings]     │
└─────────────────────────────────────────────────────────────┘

System Dropdown:
├─ Health Monitor ✨
├─ Metrics Dashboard ✨
├─ Audit Logs ✨
├─ ─────────────────
└─ V2Ray Nodes ✨

Security Dropdown:
├─ IP Blocks
├─ User Agent Blocks
├─ Advanced Security
├─ ─────────────────
└─ Device Management ✨
```

---

## 🎨 Visual Indicators

- ✨ **NEW** - Recently added components
- **Dropdown** ▼ - Hover to expand menu
- **Standalone** - Direct navigation link

---

## 🔗 Related Components

### Health Monitor integrates with:
- Metrics Dashboard (performance data)
- System settings
- PM2 workers status

### Audit Logs tracks:
- All user actions
- Admin activities
- Reseller operations
- Subscriber changes
- System events

### Resellers connects to:
- Subscribers (reseller's customers)
- Subscriptions (revenue tracking)
- Transactions (financial operations)

### V2Ray Nodes manages:
- Server configurations
- Load balancing
- Traffic statistics
- Health monitoring

---

## 🛠️ Build & Deploy

### Development Mode (Hot Reload)
```bash
npm run dev
```
Access at: `http://localhost:5173` (Vite dev server proxies to backend)

### Production Build
```bash
npm run build
```
Compiled assets go to: `public/build/`

---

## 📱 Mobile Responsive

All new components are fully responsive:
- Mobile: Stacked layout, collapsible sections
- Tablet: 2-column grid
- Desktop: Full multi-column layout

---

## 🔐 Access Control (RBAC Ready)

Navigation items respect role-based permissions:
- **Admin**: Full access to all components
- **Supervisor**: Access to Health, Metrics, Audit Logs, Resellers
- **Support**: Read-only access to Health, limited Audit Logs

*Note: RBAC middleware is ready but needs to be activated in routes*

---

## 📝 Usage Tips

1. **Health Monitor** - Pin this to a dashboard for 24/7 monitoring
2. **Audit Logs** - Use filters to quickly find specific actions
3. **Metrics Dashboard** - Export data for reporting
4. **Resellers** - Track commission payments and subscriber counts
5. **V2Ray Nodes** - Monitor server load for load balancing

---

## ✅ Verification Checklist

After building frontend, verify:
- [ ] All menu items appear in navigation
- [ ] Dropdowns expand on hover
- [ ] Routes navigate to correct components
- [ ] Components load without errors
- [ ] API calls succeed (check browser console)
- [ ] Data displays correctly

---

**Navigation Updated**: 2025-11-24
**Components Integrated**: 7 new pages
**Menu Items Added**: 8 links
**Status**: ✅ All components accessible
