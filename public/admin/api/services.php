<?php
/**
 * Categories API Endpoint
 * Manages category CRUD operations
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = Category::withCount('streams');

            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $total = $query->count();
            $categories = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $categories->map(function($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'streams_count' => $cat->streams_count,
                    'created_at' => $cat->created_at,
                    'updated_at' => $cat->updated_at
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formatted,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Category ID required');

            $category = Category::find($id);
            if (!$category) throw new Exception('Category not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['name'])) throw new Exception('Name required');

            $category = new Category();
            $category->name = $input['name'];
            $category->save();

            echo json_encode([
                'success' => true,
                'message' => 'Category created',
                'data' => ['id' => $category->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Category ID required');

            $category = Category::find($id);
            if (!$category) throw new Exception('Category not found');

            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['name'])) $category->name = $input['name'];
            $category->save();

            echo json_encode(['success' => true, 'message' => 'Category updated']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Category ID required');

            $category = Category::find($id);
            if (!$category) throw new Exception('Category not found');

            // Check if category has streams
            $streamCount = Stream::where('cat_id', $id)->count();
            if ($streamCount > 0) {
                throw new Exception("Cannot delete category with {$streamCount} stream(s)");
            }

            $category->delete();
            echo json_encode(['success' => true, 'message' => 'Category deleted']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
