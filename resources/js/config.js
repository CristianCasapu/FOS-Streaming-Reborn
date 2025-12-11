/**
 * FOS Streaming v70 - Frontend Configuration
 */

// Admin base path - reads from PHP-injected meta tag (runtime) or falls back to URL detection
// The meta tag is injected by app.html from the ADMIN_PATH environment variable
function getAdminPath() {
    // First, check for PHP-injected meta tag (most reliable - runtime value from .env)
    const adminPathMeta = document.querySelector('meta[name="admin-path"]');
    if (adminPathMeta && adminPathMeta.content) {
        return adminPathMeta.content;
    }

    // Second, check Vite environment variable (build-time value)
    if (import.meta.env.VITE_ADMIN_PATH) {
        return import.meta.env.VITE_ADMIN_PATH;
    }

    // Fall back to detecting from current URL path
    // If we're at /adminx/something, extract /adminx
    const path = window.location.pathname;
    const match = path.match(/^(\/[^/]+)/);
    if (match && match[1] !== '/build' && match[1] !== '/assets') {
        return match[1];
    }

    // Default fallback
    return '/admin';
}

export const ADMIN_PATH = getAdminPath();

// API base URL
export const API_BASE_URL = '';

// Development mode check
export const isDev = import.meta.env.DEV;
