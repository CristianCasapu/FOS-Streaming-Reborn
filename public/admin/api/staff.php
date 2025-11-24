<?php

/**
 * Staff Management API
 * Handles CRUD operations for staff members
 */

require_once '../../../config.php';
logincheck(); // Enforce authentication

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

// Load Staff model
require_once __DIR__ . '/../../../models/Staff.php';

switch ($action) {
    case 'list':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

        $query = Staff::orderBy('created_at', 'desc');

        // Get total count
        $total = $query->count();

        // Calculate pagination
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get paginated results
        $staff = $query->offset($offset)->limit($perPage)->get();

        // Hide sensitive data
        $staff = $staff->map(function($member) {
            $member->makeHidden(['password', 'tfa_secret', 'two_factor_secret', 'api_token']);
            return $member;
        });

        echo json_encode([
            'success' => true,
            'data' => $staff,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ]);
        break;

    case 'get':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        $staff = Staff::find($id);
        if ($staff) {
            $staff->makeHidden(['password', 'tfa_secret', 'two_factor_secret', 'api_token']);
            echo json_encode(['success' => true, 'data' => $staff]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
        }
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($data['username']) || empty($data['password'])) {
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            exit;
        }

        // Check if username already exists
        if (Staff::where('username', $data['username'])->exists()) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }

        // Hash password
        $data['password'] = md5($data['password']);

        // Set defaults
        if (!isset($data['role'])) $data['role'] = 'support';
        if (!isset($data['status'])) $data['status'] = 'active';

        $staff = Staff::create($data);

        if ($staff) {
            $staff->makeHidden(['password', 'tfa_secret', 'two_factor_secret', 'api_token']);
            echo json_encode(['success' => true, 'data' => $staff, 'message' => 'Staff member created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create staff member']);
        }
        break;

    case 'update':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        $staff = Staff::find($id);
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Don't allow updating username to existing one
        if (isset($data['username']) && $data['username'] !== $staff->username) {
            if (Staff::where('username', $data['username'])->exists()) {
                echo json_encode(['success' => false, 'error' => 'Username already exists']);
                exit;
            }
        }

        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = md5($data['password']);
        } else {
            unset($data['password']); // Don't update if empty
        }

        $staff->fill($data);
        $staff->save();

        $staff->makeHidden(['password', 'tfa_secret', 'two_factor_secret', 'api_token']);
        echo json_encode(['success' => true, 'data' => $staff, 'message' => 'Staff member updated successfully']);
        break;

    case 'delete':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        // Never allow deleting the main admin account (ID 1)
        if ($id == 1) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete the main admin account (ID 1)']);
            exit;
        }

        // Don't allow deleting self
        if ($id == $_SESSION['staff_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
            exit;
        }

        $staff = Staff::find($id);
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }

        // Soft delete
        $staff->delete();

        echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
        break;

    case 'restore':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        $staff = Staff::withTrashed()->find($id);
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }

        $staff->restore();

        echo json_encode(['success' => true, 'message' => 'Staff member restored successfully']);
        break;

    case 'change_password':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['password'])) {
            echo json_encode(['success' => false, 'error' => 'Password is required']);
            exit;
        }

        $staff = Staff::find($id);
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }

        $staff->password = md5($data['password']);
        $staff->password_changed_at = date('Y-m-d H:i:s');
        $staff->force_password_change = false;
        $staff->save();

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        break;

    case 'toggle_status':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        // Don't allow disabling self
        if ($id == $_SESSION['staff_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot change your own status']);
            exit;
        }

        $staff = Staff::find($id);
        if (!$staff) {
            echo json_encode(['success' => false, 'error' => 'Staff member not found']);
            exit;
        }

        $staff->status = ($staff->status === 'active') ? 'inactive' : 'active';
        $staff->save();

        echo json_encode(['success' => true, 'data' => $staff, 'message' => 'Status updated successfully']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
