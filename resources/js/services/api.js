/**
 * API Service Layer
 * Centralized API communication
 */

import axios from 'axios';
import { ADMIN_PATH } from '../config';

// Create axios instance with default config
const api = axios.create({
    baseURL: window.location.origin,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true, // Important for session cookies
});

// Admin API prefix
const ADMIN_API_PREFIX = `${ADMIN_PATH}/api`;

// Request interceptor
api.interceptors.request.use(
    (config) => {
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken.content;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Redirect to login on 401
            window.location.href = '/#/login';
        }
        return Promise.reject(error);
    }
);

export default api;

/**
 * Auth API
 */
export const authAPI = {
    login: (credentials) => api.post(`${ADMIN_API_PREFIX}/auth.php?action=login`, credentials),
    logout: () => api.post(`${ADMIN_API_PREFIX}/auth.php?action=logout`),
    check: () => api.get(`${ADMIN_API_PREFIX}/auth.php?action=check`),
    getUser: () => api.get(`${ADMIN_API_PREFIX}/auth.php?action=user`),
};

/**
 * Streams API
 */
export const streamsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/streams.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=delete&id=${id}`),
    massDelete: (ids) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=mass_delete`, { ids }),
    start: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=start&id=${id}`),
    stop: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=stop&id=${id}`),
    massStart: (ids) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=mass_start`, { ids }),
    massStop: (ids) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=mass_stop`, { ids }),
    fetchM3U: (url) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=fetch_m3u`, { url }),
    // Stream analysis endpoints
    analyze: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=analyze&id=${id}`),
    analyzeBatch: (ids) => api.post(`${ADMIN_API_PREFIX}/streams.php?action=analyze_batch`, { ids }),
    checkAccessibility: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=check_accessibility&id=${id}`),
    getTechnicalInfo: (id) => api.get(`${ADMIN_API_PREFIX}/streams.php?action=get_technical_info&id=${id}`),
};

/**
 * Subscribers API
 */
export const subscribersAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/subscribers.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/subscribers.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/subscribers.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/subscribers.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/subscribers.php?action=delete&id=${id}`),
};

/**
 * Packages API
 */
export const packagesAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/packages.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/packages.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/packages.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/packages.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/packages.php?action=delete&id=${id}`),
    toggle: (id) => api.get(`${ADMIN_API_PREFIX}/packages.php?action=toggle&id=${id}`),
    assignBouquets: (id, bouquetIds) => api.post(`${ADMIN_API_PREFIX}/packages.php?action=assign_bouquets&id=${id}`, { bouquet_ids: bouquetIds }),
    removeBouquet: (id, bouquetId) => api.get(`${ADMIN_API_PREFIX}/packages.php?action=remove_bouquet&id=${id}&bouquet_id=${bouquetId}`),
    getStats: (id) => api.get(`${ADMIN_API_PREFIX}/packages.php?action=stats&id=${id}`),
};

/**
 * Bouquets API
 */
export const bouquetsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/bouquets.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/bouquets.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/bouquets.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/bouquets.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/bouquets.php?action=delete&id=${id}`),
    toggle: (id) => api.get(`${ADMIN_API_PREFIX}/bouquets.php?action=toggle&id=${id}`),
    assignChannels: (id, channelIds) => api.post(`${ADMIN_API_PREFIX}/bouquets.php?action=assign_channels&id=${id}`, { channel_ids: channelIds }),
    removeChannel: (id, channelId) => api.get(`${ADMIN_API_PREFIX}/bouquets.php?action=remove_channel&id=${id}&channel_id=${channelId}`),
    reorderChannels: (id, channelIds) => api.post(`${ADMIN_API_PREFIX}/bouquets.php?action=reorder_channels&id=${id}`, { channel_ids: channelIds }),
    reorderBouquets: (bouquetIds) => api.post(`${ADMIN_API_PREFIX}/bouquets.php?action=reorder_bouquets`, { bouquet_ids: bouquetIds }),
};

/**
 * Channels API
 */
export const channelsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/channels.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/channels.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/channels.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/channels.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/channels.php?action=delete&id=${id}`),
    toggle: (id) => api.get(`${ADMIN_API_PREFIX}/channels.php?action=toggle&id=${id}`),
    syncFromStreams: () => api.get(`${ADMIN_API_PREFIX}/channels.php?action=sync_from_streams`),
    createFromStream: (streamId) => api.get(`${ADMIN_API_PREFIX}/channels.php?action=create_from_stream&stream_id=${streamId}`),
    getAvailableStreams: () => api.get(`${ADMIN_API_PREFIX}/channels.php?action=available_streams`),
};

/**
 * Subscriptions API
 */
export const subscriptionsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/subscriptions.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/subscriptions.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=delete&id=${id}`),
    toggle: (id) => api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=toggle&id=${id}`),
    renew: (id, days) => api.post(`${ADMIN_API_PREFIX}/subscriptions.php?action=renew&id=${id}`, { days }),
    bySubscriber: (subscriberId) => api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=by_subscriber&subscriber_id=${subscriberId}`),
    recordConnection: (id, data) => api.post(`${ADMIN_API_PREFIX}/subscriptions.php?action=record_connection&id=${id}`, data),
    getStats: () => api.get(`${ADMIN_API_PREFIX}/subscriptions.php?action=stats`),
};

/**
 * Trials API
 */
export const trialsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/trials.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/trials.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/trials.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/trials.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/trials.php?action=delete&id=${id}`),
    activate: (id) => api.get(`${ADMIN_API_PREFIX}/trials.php?action=activate&id=${id}`),
    deactivate: (id) => api.get(`${ADMIN_API_PREFIX}/trials.php?action=deactivate&id=${id}`),
    extend: (id, hours) => api.post(`${ADMIN_API_PREFIX}/trials.php?action=extend&id=${id}`, { hours }),
    convert: (id, data) => api.post(`${ADMIN_API_PREFIX}/trials.php?action=convert&id=${id}`, data),
    bySubscriber: (subscriberId) => api.get(`${ADMIN_API_PREFIX}/trials.php?action=by_subscriber&subscriber_id=${subscriberId}`),
    recordConnection: (id, data) => api.post(`${ADMIN_API_PREFIX}/trials.php?action=record_connection&id=${id}`, data),
    getStats: () => api.get(`${ADMIN_API_PREFIX}/trials.php?action=stats`),
    getSettings: () => api.get(`${ADMIN_API_PREFIX}/trials.php?action=settings`),
};

/**
 * Categories API
 */
export const categoriesAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/categories.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/categories.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/categories.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/categories.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/categories.php?action=delete&id=${id}`),
};

/**
 * Transcodes API
 */
export const transcodesAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/transcodes.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/transcodes.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/transcodes.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/transcodes.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/transcodes.php?action=delete&id=${id}`),
};

/**
 * IP Blocks API
 */
export const ipblocksAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/ipblocks.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/ipblocks.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/ipblocks.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/ipblocks.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/ipblocks.php?action=delete&id=${id}`),
};

/**
 * User Agent Blocks API
 */
export const useragentsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/useragents.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/useragents.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/useragents.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/useragents.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/useragents.php?action=delete&id=${id}`),
};

/**
 * Admins API
 */
export const adminsAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/admins.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/admins.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/admins.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/admins.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/admins.php?action=delete&id=${id}`),
};

/**
 * Activities API
 */
export const activitiesAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/activities.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/activities.php?action=get&id=${id}`),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/activities.php?action=delete&id=${id}`),
    deleteAll: () => api.get(`${ADMIN_API_PREFIX}/activities.php?action=delete_all`),
    getStats: () => api.get(`${ADMIN_API_PREFIX}/activities.php?action=stats`),
};

/**
 * Settings API
 */
export const settingsAPI = {
    get: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=get`),
    update: (data) => api.post(`${ADMIN_API_PREFIX}/settings.php?action=update`, data),
    testFFmpeg: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=test_ffmpeg`),
    testFFprobe: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=test_ffprobe`),
    getSystemInfo: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=system_info`),
    testSudo: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=test_sudo`),
    clearSudo: () => api.get(`${ADMIN_API_PREFIX}/settings.php?action=clear_sudo`),
    uploadLogo: (formData) => api.post(`${ADMIN_API_PREFIX}/upload-logo.php`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }),
    uploadFavicon: (formData) => api.post(`${ADMIN_API_PREFIX}/upload-favicon.php`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }),
};

/**
 * System Commands API
 */
export const systemCommandsAPI = {
    execute: (command, description) => api.post(`${ADMIN_API_PREFIX}/system-commands.php?action=execute`, { command, description }),
    installPackage: (packageName) => api.post(`${ADMIN_API_PREFIX}/system-commands.php?action=install_package`, { package: packageName }),
    restartService: (service) => api.post(`${ADMIN_API_PREFIX}/system-commands.php?action=restart_service`, { service }),
    serviceStatus: (service) => api.post(`${ADMIN_API_PREFIX}/system-commands.php?action=service_status`, { service }),
    getLogs: (limit = 50) => api.get(`${ADMIN_API_PREFIX}/system-commands.php?action=get_logs&limit=${limit}`),
    getFailedLogs: (limit = 50) => api.get(`${ADMIN_API_PREFIX}/system-commands.php?action=get_failed_logs&limit=${limit}`),
    getAllowedCommands: () => api.get(`${ADMIN_API_PREFIX}/system-commands.php?action=allowed_commands`),
    getQuickActions: () => api.get(`${ADMIN_API_PREFIX}/system-commands.php?action=quick_actions`),
};

/**
 * Security API (Advanced)
 */
export const securityAPI = {
    getStatus: () => api.get(`${ADMIN_API_PREFIX}/security.php?action=get_status`),
    getUFWRules: () => api.get(`${ADMIN_API_PREFIX}/security.php?action=get_ufw_rules`),
    toggleUFW: (enable) => api.post(`${ADMIN_API_PREFIX}/security.php?action=toggle_ufw`, { enable }),
    addUFWRule: (data) => api.post(`${ADMIN_API_PREFIX}/security.php?action=add_ufw_rule`, data),
    deleteUFWRule: (ruleNumber) => api.post(`${ADMIN_API_PREFIX}/security.php?action=delete_ufw_rule`, { rule_number: ruleNumber }),
    getFail2banJails: () => api.get(`${ADMIN_API_PREFIX}/security.php?action=get_fail2ban_jails`),
    getJailStatus: (jail) => api.get(`${ADMIN_API_PREFIX}/security.php?action=get_jail_status&jail=${jail}`),
    banIP: (data) => api.post(`${ADMIN_API_PREFIX}/security.php?action=ban_ip`, data),
    unbanIP: (data) => api.post(`${ADMIN_API_PREFIX}/security.php?action=unban_ip`, data),
    reloadFail2ban: () => api.post(`${ADMIN_API_PREFIX}/security.php?action=reload_fail2ban`, {}),
    getSecurityLogs: (limit = 50) => api.get(`${ADMIN_API_PREFIX}/security.php?action=get_security_logs&limit=${limit}`),
};

/**
 * UFW Rules API (Database-backed)
 */
export const ufwRulesAPI = {
    getAll: () => api.get(`${ADMIN_API_PREFIX}/ufw_rules.php?action=list`),
    getDefaultRules: () => api.get(`${ADMIN_API_PREFIX}/ufw_rules.php?action=list_default`),
    getCustomRules: () => api.get(`${ADMIN_API_PREFIX}/ufw_rules.php?action=list_custom`),
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/ufw_rules.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=update&id=${id}`, data),
    toggle: (id) => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=toggle&id=${id}`, {}),
    delete: (id) => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=delete&id=${id}`, {}),
    applyAll: () => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=apply_all`, {}),
    applySingle: (id) => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=apply_single&id=${id}`, {}),
    syncFromUFW: () => api.get(`${ADMIN_API_PREFIX}/ufw_rules.php?action=sync_from_ufw`),
    reseedDefaults: () => api.post(`${ADMIN_API_PREFIX}/ufw_rules.php?action=reseed_defaults`, {}),
};

/**
 * Dashboard API
 */
export const dashboardAPI = {
    getStats: () => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=stats`),
    getActivities: (limit = 10) => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=activities&limit=${limit}`),
    getStreams: (limit = 5) => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=streams&limit=${limit}`),
    getCategories: (limit = 5) => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=categories&limit=${limit}`),
    getSecurity: () => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=security`),
    getSystem: () => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=system`),
    getAlerts: () => api.get(`${ADMIN_API_PREFIX}/dashboard.php?action=alerts`),
};

/**
 * PM2 Process Manager API
 */
export const pm2API = {
    getStatus: () => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=status`),
    install: () => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=install`),
    start: (workerId = 'all') => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=start&worker_id=${workerId}`),
    stop: (workerId = 'all') => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=stop&worker_id=${workerId}`),
    restart: (workerId = 'all') => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=restart&worker_id=${workerId}`),
    getLogs: (worker = 'all', lines = 100) => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=logs&worker=${worker}&lines=${lines}`),
    getQueueStats: () => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=queue_stats`),
    getConfig: (worker) => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=get_config&worker=${worker}`),
    updateConfig: (worker, config) => api.post(`${ADMIN_API_PREFIX}/pm2.php?action=update_config`, { worker, config: JSON.stringify(config) }),
    serviceAction: (service, action) => api.post(`${ADMIN_API_PREFIX}/pm2.php?action=service_action`, { service, action }),
    sync: () => api.get(`${ADMIN_API_PREFIX}/pm2.php?action=sync`),
};

/**
 * PM2 Workers CRUD API
 */
export const pm2WorkersAPI = {
    getAll: () => api.get(`${ADMIN_API_PREFIX}/pm2-workers.php?action=list`),
    getOne: (name) => api.get(`${ADMIN_API_PREFIX}/pm2-workers.php?action=get&name=${encodeURIComponent(name)}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/pm2-workers.php?action=create`, data),
    update: (name, data) => api.post(`${ADMIN_API_PREFIX}/pm2-workers.php?action=update&name=${encodeURIComponent(name)}`, data),
    delete: (name) => api.get(`${ADMIN_API_PREFIX}/pm2-workers.php?action=delete&name=${encodeURIComponent(name)}`),
    toggle: (name) => api.get(`${ADMIN_API_PREFIX}/pm2-workers.php?action=toggle&name=${encodeURIComponent(name)}`),
};
