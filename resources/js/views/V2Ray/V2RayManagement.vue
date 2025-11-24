<template>
  <AppLayout>
  <div class="v2ray-management">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">V2Ray Management</h2>
      <p class="text-gray-600 dark:text-gray-400 mt-2">
        Configure V2Ray servers and manage user connections for traffic obfuscation
      </p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
      <nav class="-mb-px flex space-x-8">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
            activeTab === tab.id
              ? 'border-blue-500 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
          ]"
        >
          {{ tab.name }}
        </button>
      </nav>
    </div>

    <!-- Servers Tab -->
    <div v-if="activeTab === 'servers'" class="space-y-6">
      <!-- Server Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Active Servers</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ serverStats.active }}</p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
              <ServerIcon class="h-6 w-6 text-green-600 dark:text-green-400" />
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Connections</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ serverStats.connections }}</p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
              <LinkIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">Bandwidth Used</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ formatBytes(serverStats.bandwidth) }}</p>
            </div>
            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
              <ChartBarIcon class="h-6 w-6 text-purple-600 dark:text-purple-400" />
            </div>
          </div>
        </div>
      </div>

      <!-- Server List -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">V2Ray Servers</h3>
            <button
              @click="showAddServerModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
              Add Server
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Server
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Protocol
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Network
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Connections
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="server in servers" :key="server.id" class="hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ server.name }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ server.hostname }}:{{ server.port }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getProtocolClass(server.protocol)">
                    {{ server.protocol.toUpperCase() }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="text-sm text-gray-900 dark:text-gray-100">{{ server.network }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">
                    {{ server.current_connections }} / {{ server.max_connections }}
                  </div>
                  <div class="mt-1 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full"
                      :style="`width: ${(server.current_connections / server.max_connections) * 100}%`"></div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    server.status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                  ]">
                    {{ server.status ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button @click="editServer(server)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-2">
                    Edit
                  </button>
                  <button @click="toggleServer(server)" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 mr-2">
                    {{ server.status ? 'Disable' : 'Enable' }}
                  </button>
                  <button @click="deleteServer(server)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Users Tab -->
    <div v-if="activeTab === 'users'" class="space-y-6">
      <!-- User Search -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center space-x-4">
          <input
            v-model="userSearch"
            type="text"
            placeholder="Search users by email or UUID..."
            class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
          />
          <select
            v-model="userFilter.protocol"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
          >
            <option value="">All Protocols</option>
            <option value="vmess">VMess</option>
            <option value="vless">VLESS</option>
            <option value="trojan">Trojan</option>
            <option value="shadowsocks">Shadowsocks</option>
          </select>
          <button
            @click="fetchV2RayUsers"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition"
          >
            Search
          </button>
        </div>
      </div>

      <!-- Users List -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">V2Ray Users</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  User
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Protocol
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Server
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Traffic
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Last Connection
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="user in v2rayUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.email }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.uuid }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getProtocolClass(user.protocol)">
                    {{ user.protocol.toUpperCase() }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ user.server_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900 dark:text-gray-100">
                    {{ formatBytes(user.traffic_used) }}
                    <span v-if="user.traffic_limit"> / {{ formatBytes(user.traffic_limit) }}</span>
                  </div>
                  <div v-if="user.traffic_limit" class="mt-1 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full"
                      :style="`width: ${Math.min((user.traffic_used / user.traffic_limit) * 100, 100)}%`"></div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ user.last_connected_at ? formatDate(user.last_connected_at) : 'Never' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    user.enabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                  ]">
                    {{ user.enabled ? 'Active' : 'Disabled' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button @click="generateConfig(user)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-2">
                    Config
                  </button>
                  <button @click="resetTraffic(user)" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 mr-2">
                    Reset
                  </button>
                  <button @click="toggleUser(user)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                    {{ user.enabled ? 'Disable' : 'Enable' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Domain Fronting Tab -->
    <div v-if="activeTab === 'fronting'" class="space-y-6">
      <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
        <div class="flex">
          <ShieldCheckIcon class="h-5 w-5 text-yellow-400 mr-2" />
          <div>
            <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Domain Fronting</h4>
            <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
              Configure CDN domain fronting to disguise V2Ray traffic as regular HTTPS connections
            </p>
          </div>
        </div>
      </div>

      <!-- Fronting List -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Domain Fronting Configurations</h3>
            <button
              @click="showAddFrontingModal = true"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
              Add Configuration
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  CDN Provider
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Front Domain
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Real Domain
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  SNI Override
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="config in domainFronting" :key="config.id" class="hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                    {{ config.cdn_provider }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ config.front_domain }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                  {{ config.real_domain }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ config.sni_override || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    config.enabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                  ]">
                    {{ config.enabled ? 'Enabled' : 'Disabled' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button @click="testFronting(config)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-2">
                    Test
                  </button>
                  <button @click="editFronting(config)" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 mr-2">
                    Edit
                  </button>
                  <button @click="deleteFronting(config)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Configuration Modal -->
    <TransitionRoot appear :show="showConfigModal" as="template">
      <Dialog as="div" @close="showConfigModal = false" class="relative z-50">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black bg-opacity-25" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4 text-center">
            <TransitionChild
              as="template"
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                  V2Ray Client Configuration
                </DialogTitle>
                <div class="mt-4">
                  <div class="space-y-4">
                    <!-- QR Code -->
                    <div v-if="selectedConfig.qrcode" class="flex justify-center">
                      <img :src="selectedConfig.qrcode" alt="QR Code" class="w-64 h-64" />
                    </div>

                    <!-- URL -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Connection URL
                      </label>
                      <div class="flex items-center space-x-2">
                        <input
                          :value="selectedConfig.url"
                          readonly
                          class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-sm"
                        />
                        <button
                          @click="copyToClipboard(selectedConfig.url)"
                          class="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition"
                        >
                          Copy
                        </button>
                      </div>
                    </div>

                    <!-- JSON Config -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        JSON Configuration
                      </label>
                      <textarea
                        :value="JSON.stringify(selectedConfig.json, null, 2)"
                        readonly
                        rows="10"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-sm font-mono"
                      />
                    </div>
                  </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                  <button
                    @click="downloadConfig"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                  >
                    Download Config
                  </button>
                  <button
                    @click="showConfigModal = false"
                    class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition"
                  >
                    Close
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import AppLayout from '../../components/AppLayout.vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import {
  ServerIcon,
  LinkIcon,
  ChartBarIcon,
  ShieldCheckIcon
} from '@heroicons/vue/24/outline';
import { v2rayAPI } from '../../services/api';

// State
const activeTab = ref('servers');
const loading = ref(false);
const servers = ref([]);
const v2rayUsers = ref([]);
const domainFronting = ref([]);
const showAddServerModal = ref(false);
const showAddFrontingModal = ref(false);
const showConfigModal = ref(false);
const selectedConfig = ref({});
const userSearch = ref('');
const userFilter = reactive({
  protocol: '',
  server: ''
});

// Computed
const serverStats = computed(() => {
  const active = servers.value.filter(s => s.status).length;
  const connections = servers.value.reduce((acc, s) => acc + s.current_connections, 0);
  const bandwidth = servers.value.reduce((acc, s) => acc + s.bandwidth_used, 0);
  return { active, connections, bandwidth };
});

const tabs = [
  { id: 'servers', name: 'Servers' },
  { id: 'users', name: 'Users' },
  { id: 'fronting', name: 'Domain Fronting' }
];

// Methods
const fetchServers = async () => {
  loading.value = true;
  try {
    const response = await v2rayAPI.getServers();
    servers.value = response.data.data;
  } catch (error) {
    console.error('Error fetching servers:', error);
  } finally {
    loading.value = false;
  }
};

const fetchV2RayUsers = async () => {
  loading.value = true;
  try {
    const params = {
      search: userSearch.value,
      protocol: userFilter.protocol,
      server: userFilter.server
    };
    const response = await v2rayAPI.getUsers(params);
    v2rayUsers.value = response.data.data;
  } catch (error) {
    console.error('Error fetching users:', error);
  } finally {
    loading.value = false;
  }
};

const fetchDomainFronting = async () => {
  loading.value = true;
  try {
    const response = await v2rayAPI.getDomainFronting();
    domainFronting.value = response.data.data;
  } catch (error) {
    console.error('Error fetching domain fronting:', error);
  } finally {
    loading.value = false;
  }
};

const toggleServer = async (server) => {
  try {
    await v2rayAPI.toggleServer(server.id);
    await fetchServers();
  } catch (error) {
    console.error('Error toggling server:', error);
  }
};

const deleteServer = async (server) => {
  if (!confirm(`Are you sure you want to delete server "${server.name}"?`)) return;

  try {
    await v2rayAPI.deleteServer(server.id);
    await fetchServers();
  } catch (error) {
    console.error('Error deleting server:', error);
  }
};

const generateConfig = async (user) => {
  loading.value = true;
  try {
    const response = await v2rayAPI.generateConfig(user.id);
    selectedConfig.value = response.data.data;
    showConfigModal.value = true;
  } catch (error) {
    console.error('Error generating config:', error);
  } finally {
    loading.value = false;
  }
};

const resetTraffic = async (user) => {
  if (!confirm(`Reset traffic counter for ${user.email}?`)) return;

  try {
    await v2rayAPI.resetTraffic(user.id);
    await fetchV2RayUsers();
  } catch (error) {
    console.error('Error resetting traffic:', error);
  }
};

const toggleUser = async (user) => {
  try {
    await v2rayAPI.toggleUser(user.id);
    await fetchV2RayUsers();
  } catch (error) {
    console.error('Error toggling user:', error);
  }
};

const testFronting = async (config) => {
  loading.value = true;
  try {
    const response = await v2rayAPI.testFronting(config.id);
    alert(response.data.message || 'Test completed');
  } catch (error) {
    console.error('Error testing fronting:', error);
    alert('Test failed: ' + error.message);
  } finally {
    loading.value = false;
  }
};

const deleteFronting = async (config) => {
  if (!confirm(`Delete domain fronting for ${config.front_domain}?`)) return;

  try {
    await v2rayAPI.deleteFronting(config.id);
    await fetchDomainFronting();
  } catch (error) {
    console.error('Error deleting fronting:', error);
  }
};

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text);
  alert('Copied to clipboard!');
};

const downloadConfig = () => {
  const blob = new Blob([JSON.stringify(selectedConfig.value.json, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'v2ray-config.json';
  a.click();
  URL.revokeObjectURL(url);
};

const getProtocolClass = (protocol) => {
  const classes = {
    vmess: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    vless: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    trojan: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    shadowsocks: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
  };
  return classes[protocol] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};

const formatBytes = (bytes) => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (date) => {
  return new Date(date).toLocaleString();
};

// Lifecycle
onMounted(() => {
  fetchServers();
  fetchV2RayUsers();
  fetchDomainFronting();
});
</script>