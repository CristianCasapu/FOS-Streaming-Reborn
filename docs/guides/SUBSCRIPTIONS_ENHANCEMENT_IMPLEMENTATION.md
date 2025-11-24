# Subscriptions Management Enhancement - Implementation Guide

**Date**: 2025-11-24
**Feature**: Auto-calculating subscription modal with package integration
**Status**: Ready for implementation

---

## Overview

This guide provides the complete code to enhance the Subscriptions management page with:
- Auto-populated duration from selected package
- Auto-calculated expiration date (start date + duration)
- Proper datetime format for database compatibility
- Comprehensive form with all subscription fields

---

## Backend Status

✅ **Already Fixed**:
- `/public/admin/api/subscriptions.php` - All `now()` references replaced with `date('Y-m-d H:i:s')`
- API accepts `expire_date` in format: `YYYY-MM-DD HH:mm:ss`
- Required fields: `subscriber_id`, `package_id`, `expire_date`

---

## Frontend Implementation

### Step 1: Update Imports

In `/resources/js/views/Subscribers/SubscriptionsList.vue`, update the imports:

```javascript
import { subscriptionsAPI, subscribersAPI, packagesAPI } from '../../services/api';
```

### Step 2: Add Reactive State Variables

Add these after the existing `ref()` declarations:

```javascript
const subscribers = ref([]);
const packages = ref([]);
const saving = ref(false);
const showModal = ref(false);
const editingSubscription = ref(null);

const formData = ref({
    subscriber_id: '',
    package_id: '',
    start_date: '',
    duration_days: 30,
    expire_date: '',
    max_concurrent_connections: 1,
    device: '',
    device_mac: '',
    is_active: true,
    auto_renew: false,
    notes: ''
});
```

### Step 3: Add Helper Functions

Add these functions before `openCreateModal`:

```javascript
const fetchSubscribers = async () => {
    try {
        const response = await subscribersAPI.getAll({ per_page: 1000 });
        if (response.data.success) {
            subscribers.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching subscribers:', error);
    }
};

const fetchPackages = async () => {
    try {
        const response = await packagesAPI.getAll({ per_page: 1000 });
        if (response.data.success) {
            packages.value = response.data.data;
        }
    } catch (error) {
        console.error('Error fetching packages:', error);
    }
};

const getTodayDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const onPackageChange = () => {
    const selectedPackage = packages.value.find(p => p.id == formData.value.package_id);
    if (selectedPackage) {
        // Auto-populate duration from package
        formData.value.duration_days = selectedPackage.duration_days || 30;
        // Auto-populate max connections from package
        formData.value.max_concurrent_connections = selectedPackage.max_concurrent_devices || 1;
        // Recalculate expire date
        calculateExpireDate();
    }
};

const calculateExpireDate = () => {
    if (formData.value.start_date && formData.value.duration_days) {
        const startDate = new Date(formData.value.start_date);
        const expireDate = new Date(startDate);
        expireDate.setDate(expireDate.getDate() + parseInt(formData.value.duration_days));

        // Format as YYYY-MM-DDTHH:mm for datetime-local input
        const year = expireDate.getFullYear();
        const month = String(expireDate.getMonth() + 1).padStart(2, '0');
        const day = String(expireDate.getDate()).padStart(2, '0');
        const hours = String(expireDate.getHours()).padStart(2, '0');
        const minutes = String(expireDate.getMinutes()).padStart(2, '0');

        formData.value.expire_date = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
};
```

### Step 4: Replace `openCreateModal` Function

Replace the existing placeholder function with:

```javascript
const openCreateModal = async () => {
    editingSubscription.value = null;

    // Reset form with defaults
    formData.value = {
        subscriber_id: '',
        package_id: '',
        start_date: getTodayDate(),
        duration_days: 30,
        expire_date: '',
        max_concurrent_connections: 1,
        device: '',
        device_mac: '',
        is_active: true,
        auto_renew: false,
        notes: ''
    };

    // Calculate initial expire date
    calculateExpireDate();

    // Load subscribers and packages if not already loaded
    if (subscribers.value.length === 0) {
        await fetchSubscribers();
    }
    if (packages.value.length === 0) {
        await fetchPackages();
    }

    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSubscription.value = null;
};

const saveSubscription = async () => {
    saving.value = true;
    try {
        // Convert datetime-local format to database format (YYYY-MM-DD HH:mm:ss)
        const expireDate = new Date(formData.value.expire_date);
        const formattedExpireDate = expireDate.toISOString().slice(0, 19).replace('T', ' ');

        const data = {
            subscriber_id: formData.value.subscriber_id,
            package_id: formData.value.package_id,
            expire_date: formattedExpireDate,
            max_concurrent_connections: formData.value.max_concurrent_connections,
            device: formData.value.device || null,
            device_mac: formData.value.device_mac || null,
            is_active: formData.value.is_active ? 1 : 0,
            auto_renew: formData.value.auto_renew ? 1 : 0,
            notes: formData.value.notes || null
        };

        const response = editingSubscription.value
            ? await subscriptionsAPI.update(editingSubscription.value.id, data)
            : await subscriptionsAPI.create(data);

        if (response.data.success) {
            closeModal();
            await fetchSubscriptions();
            alert(response.data.message || 'Subscription saved successfully');
        }
    } catch (error) {
        console.error('Error saving subscription:', error);
        alert(error.response?.data?.message || 'Failed to save subscription');
    } finally {
        saving.value = false;
    }
};
```

### Step 5: Update `onMounted`

Update the onMounted hook to also load packages:

```javascript
onMounted(() => {
    fetchSubscriptions();
    fetchPackages(); // Load packages for filter dropdown
});
```

### Step 6: Add Modal HTML

Add this modal HTML right before the closing `</AppLayout>` tag (after the main table div):

```vue
<!-- Create/Edit Subscription Modal -->
<div v-if="showModal" class="fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="sm:flex sm:items-start">
                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        {{ editingSubscription ? 'Edit Subscription' : 'Create New Subscription' }}
                    </h3>

                    <form @submit.prevent="saveSubscription" class="space-y-4">
                        <!-- Subscriber Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Subscriber *</label>
                            <select v-model="formData.subscriber_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select Subscriber</option>
                                <option v-for="subscriber in subscribers" :key="subscriber.id" :value="subscriber.id">
                                    {{ subscriber.username }} ({{ subscriber.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Package Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Package *</label>
                            <select v-model="formData.package_id" @change="onPackageChange" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select Package</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                                    {{ pkg.name }} - {{ pkg.duration_days }} days (${{ pkg.price }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Start Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Start Date *</label>
                                <input v-model="formData.start_date" @change="calculateExpireDate" type="date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <!-- Duration (auto-populated from package) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Duration (days) *</label>
                                <input v-model.number="formData.duration_days" @input="calculateExpireDate" type="number" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Expiration Date (calculated) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expiration Date (Auto-calculated)</label>
                            <input v-model="formData.expire_date" type="datetime-local" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm cursor-not-allowed">
                            <p class="mt-1 text-xs text-gray-500">Automatically calculated: Start Date + Duration</p>
                        </div>

                        <!-- Max Concurrent Connections -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Concurrent Connections</label>
                            <input v-model.number="formData.max_concurrent_connections" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">From selected package</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Device Info (Optional) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Device Name</label>
                                <input v-model="formData.device" type="text" placeholder="e.g., Samsung TV" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Device MAC Address</label>
                                <input v-model="formData.device_mac" type="text" placeholder="e.g., 00:11:22:33:44:55" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Status Options -->
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input v-model="formData.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                            <label class="flex items-center">
                                <input v-model="formData.auto_renew" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Auto-Renew</span>
                            </label>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea v-model="formData.notes" rows="2" placeholder="Optional notes..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                {{ saving ? 'Saving...' : 'Save Subscription' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Step 7: Update Package Filter Dropdown

In the filters section (around line 28-30), update the package dropdown to show actual packages:

```vue
<select v-model="filters.package" @change="fetchSubscriptions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
    <option value="">All Packages</option>
    <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
</select>
```

---

## Key Features

### Auto-Calculations:
1. **Package Selection** → Duration and max connections auto-populated
2. **Start Date + Duration** → Expiration date automatically calculated
3. **Real-time Updates** → Changes reflect immediately

### Date Format Handling:
- **Frontend Input**: `YYYY-MM-DDTHH:mm` (datetime-local format)
- **Database Storage**: `YYYY-MM-DD HH:mm:ss` (MySQL datetime format)
- **Conversion**: `toISOString().slice(0, 19).replace('T', ' ')`

### Validation:
- Required fields: Subscriber, Package, Start Date, Duration
- Min values: Duration ≥ 1 day, Max Connections ≥ 1
- Optional fields gracefully handle empty values (converted to NULL)

---

## Testing Checklist

- [ ] Modal opens when clicking "New Subscription"
- [ ] Subscribers dropdown loads all subscribers
- [ ] Packages dropdown loads all packages with price/duration
- [ ] Selecting package auto-populates duration and max connections
- [ ] Changing start date recalculates expiration date
- [ ] Changing duration recalculates expiration date
- [ ] Expiration date field is readonly (calculated only)
- [ ] Form submits successfully
- [ ] New subscription appears in list
- [ ] Proper datetime format saved to database
- [ ] Empty optional fields save as NULL
- [ ] Validation prevents submission without required fields

---

## Troubleshooting

### Issue: Subscribers/Packages not loading
**Solution**: Check API responses in browser console. Ensure `subscribersAPI` and `packagesAPI` are correctly imported and implemented in `/resources/js/services/api.js`.

### Issue: Date format error
**Solution**: Verify the conversion in `saveSubscription`:
```javascript
const formattedExpireDate = expireDate.toISOString().slice(0, 19).replace('T', ' ');
```
This should produce: `2025-11-24 15:30:00`

### Issue: Package fields not showing
**Solution**: Ensure packages API returns `duration_days`, `max_concurrent_devices`, and `price` fields.

---

## Build & Deploy

After making changes:

```bash
# Build frontend
npm run build

# Hard refresh browser
Ctrl+Shift+R (or Cmd+Shift+R on Mac)
```

---

## Next Steps

After implementing this modal:
1. Test thoroughly with real data
2. Add edit functionality (populate form with existing subscription data)
3. Consider adding subscription preview before saving
4. Add bulk actions (renew multiple subscriptions)

---

**Implementation Date**: 2025-11-24
**Status**: Ready for implementation
**Estimated Time**: 30-45 minutes
