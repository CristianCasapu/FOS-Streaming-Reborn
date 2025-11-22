<?php

/**
 * Authentication API Endpoints
 * Handles login, logout, and auth status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/middleware.php';

// Apply security middleware
requireAdminPath();
validateCsrfToken();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/**
 * Helper function to send JSON response
 */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Helper function to get JSON input
 */
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

// Route requests
switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
        handleLogin();
        break;

    case 'logout':
        if ($method !== 'POST') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
        handleLogout();
        break;

    case 'check':
        if ($method !== 'GET') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
        handleCheck();
        break;

    case 'user':
        if ($method !== 'GET') {
            sendResponse(['error' => 'Method not allowed'], 405);
        }
        handleGetUser();
        break;

    default:
        sendResponse(['error' => 'Invalid action'], 400);
}

/**
 * Handle login request
 */
function handleLogin() {
    $input = getJsonInput();

    // Validate input
    if (empty($input['username']) || empty($input['password'])) {
        sendResponse([
            'success' => false,
            'message' => 'Username and password are required'
        ], 400);
    }

    $username = stripslashes($input['username']);
    $password = stripslashes($input['password']);

    // Rate limiting
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit($identifier);

    // Find user
    $user = Admin::where('username', '=', $username)
                 ->where('password', '=', md5($password))
                 ->first();

    if ($user) {
        // Clear rate limit on success
        clearRateLimit($identifier);

        // Set session
        $_SESSION['user_id'] = $username;
        $_SESSION['admin_id'] = $user->id;
        $_SESSION['logged_in'] = true;

        // Log successful login
        logSecurityEvent('login_success', ['username' => $username]);

        sendResponse([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email ?? null
            ]
        ]);
    } else {
        // Log failed login attempt
        logSecurityEvent('login_failed', ['username' => $username]);

        sendResponse([
            'success' => false,
            'message' => 'Invalid username or password'
        ], 401);
    }
}

/**
 * Handle logout request
 */
function handleLogout() {
    // Clear session
    session_unset();
    session_destroy();

    sendResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Check if user is authenticated
 */
function handleCheck() {
    $isAuthenticated = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);

    sendResponse([
        'authenticated' => $isAuthenticated
    ]);
}

/**
 * Get current user info
 */
function handleGetUser() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Not authenticated'
        ], 401);
    }

    $user = Admin::where('username', '=', $_SESSION['user_id'])->first();

    if ($user) {
        sendResponse([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email ?? null
            ]
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }
}
