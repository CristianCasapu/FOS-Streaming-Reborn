<?php
/**
 * Bouquets API Endpoint
 *
 * Manage bouquets (groups) of streams
 * Bouquets now directly reference streams via stream_ids JSON array
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $active = $_GET['active'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
            $withStreams = isset($_GET['with_streams']) && $_GET['with_streams'] == '1';

            $query = Bouquet::query()->orderBy('sort_order');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            if ($active !== null) {
                $query->where('is_active', $active);
            }

            $total = $query->count();
            $bouquets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $bouquets->map(function($bouquet) use ($withStreams) {
                $data = [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'description' => $bouquet->description,
                    'is_active' => $bouquet->is_active,
                    'sort_order' => $bouquet->sort_order,
                    'stream_count' => $bouquet->stream_count,
                    'running_stream_count' => $bouquet->running_stream_count,
                    'package_count' => $bouquet->packages()->count(),
                    'created_at' => $bouquet->created_at,
                    'updated_at' => $bouquet->updated_at
                ];

                if ($withStreams) {
                    $data['streams'] = $bouquet->streamsWithDetails;
                }

                return $data;
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
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $streams = $bouquet->streams()->map(function($stream, $index) {
                return [
                    'id' => $stream->id,
                    'name' => $stream->stream_display_name ?? $stream->name ?? "Stream {$stream->id}",
                    'category_id' => $stream->cat_id,
                    'category_name' => $stream->category ? $stream->category->name : null,
                    'running' => $stream->running == 1,
                    'streamurl' => $stream->streamurl,
                    'sort_order' => $index
                ];
            });

            $packages = $bouquet->packages->map(function($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'description' => $bouquet->description,
                    'is_active' => $bouquet->is_active,
                    'sort_order' => $bouquet->sort_order,
                    'stream_ids' => $bouquet->stream_ids ?? [],
                    'streams' => $streams,
                    'packages' => $packages,
                    'stream_count' => $streams->count(),
                    'package_count' => $packages->count(),
                    'created_at' => $bouquet->created_at,
                    'updated_at' => $bouquet->updated_at
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['name'])) {
                throw new Exception('Bouquet name is required');
            }

            // Check for duplicate name
            if (Bouquet::where('name', $input['name'])->exists()) {
                throw new Exception('A bouquet with this name already exists');
            }

            $bouquet = new Bouquet();
            $bouquet->name = $input['name'];
            $bouquet->description = $input['description'] ?? null;
            $bouquet->is_active = $input['is_active'] ?? 1;
            $bouquet->sort_order = $input['sort_order'] ?? 0;

            // Set stream IDs if provided
            if (isset($input['stream_ids']) && is_array($input['stream_ids'])) {
                $bouquet->stream_ids = array_values(array_unique($input['stream_ids']));
            }

            $bouquet->save();

            echo json_encode([
                'success' => true,
                'message' => 'Bouquet created successfully',
                'data' => ['id' => $bouquet->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $input = json_decode(file_get_contents('php://input'), true);

            // Check for duplicate name (excluding current bouquet)
            if (isset($input['name']) && $input['name'] != $bouquet->name) {
                if (Bouquet::where('name', $input['name'])->where('id', '!=', $id)->exists()) {
                    throw new Exception('A bouquet with this name already exists');
                }
            }

            if (isset($input['name'])) $bouquet->name = $input['name'];
            if (isset($input['description'])) $bouquet->description = $input['description'];
            if (isset($input['is_active'])) $bouquet->is_active = $input['is_active'];
            if (isset($input['sort_order'])) $bouquet->sort_order = $input['sort_order'];

            // Update stream IDs if provided
            if (isset($input['stream_ids']) && is_array($input['stream_ids'])) {
                $bouquet->stream_ids = array_values(array_unique($input['stream_ids']));
            }

            $bouquet->save();

            echo json_encode([
                'success' => true,
                'message' => 'Bouquet updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            // Check if bouquet is assigned to any packages
            $packageCount = $bouquet->packages()->count();
            if ($packageCount > 0) {
                throw new Exception("Cannot delete bouquet assigned to {$packageCount} package(s). Remove it from packages first.");
            }

            $bouquet->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Bouquet deleted successfully'
            ]);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $bouquet->is_active = !$bouquet->is_active;
            $bouquet->save();

            echo json_encode([
                'success' => true,
                'message' => 'Bouquet status updated',
                'data' => ['is_active' => $bouquet->is_active]
            ]);
            break;

        case 'assign_streams':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $streamIds = $input['stream_ids'] ?? [];

            if (!is_array($streamIds)) {
                throw new Exception('stream_ids must be an array');
            }

            $bouquet->setStreams($streamIds);

            echo json_encode([
                'success' => true,
                'message' => 'Streams assigned successfully',
                'data' => [
                    'stream_count' => $bouquet->stream_count
                ]
            ]);
            break;

        case 'add_stream':
            $id = $_GET['id'] ?? null;
            $streamId = $_GET['stream_id'] ?? null;

            if (!$id) throw new Exception('Bouquet ID required');
            if (!$streamId) throw new Exception('Stream ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            // Verify stream exists
            $stream = Stream::find($streamId);
            if (!$stream) throw new Exception('Stream not found');

            $bouquet->addStream($streamId);

            echo json_encode([
                'success' => true,
                'message' => 'Stream added to bouquet'
            ]);
            break;

        case 'remove_stream':
            $id = $_GET['id'] ?? null;
            $streamId = $_GET['stream_id'] ?? null;

            if (!$id) throw new Exception('Bouquet ID required');
            if (!$streamId) throw new Exception('Stream ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $bouquet->removeStream($streamId);

            echo json_encode([
                'success' => true,
                'message' => 'Stream removed from bouquet'
            ]);
            break;

        case 'reorder_streams':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $streamIds = $input['stream_ids'] ?? [];

            if (!is_array($streamIds)) {
                throw new Exception('stream_ids must be an array');
            }

            $bouquet->reorderStreams($streamIds);

            echo json_encode([
                'success' => true,
                'message' => 'Streams reordered successfully'
            ]);
            break;

        case 'reorder_bouquets':
            // Reorder multiple bouquets at once
            $input = json_decode(file_get_contents('php://input'), true);
            $bouquetIds = $input['bouquet_ids'] ?? [];

            if (!is_array($bouquetIds)) {
                throw new Exception('bouquet_ids must be an array');
            }

            $order = 0;
            foreach ($bouquetIds as $bouquetId) {
                $bouquet = Bouquet::find($bouquetId);
                if ($bouquet) {
                    $bouquet->sort_order = $order;
                    $bouquet->save();
                    $order++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Bouquets reordered successfully'
            ]);
            break;

        case 'cleanup_invalid_streams':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $bouquet->cleanupInvalidStreamIds();

            echo json_encode([
                'success' => true,
                'message' => 'Invalid stream IDs cleaned up',
                'data' => [
                    'stream_count' => $bouquet->stream_count
                ]
            ]);
            break;

        case 'available_streams':
            // Get all active streams for selection
            $bouquetId = $_GET['bouquet_id'] ?? null;
            $search = $_GET['search'] ?? null;
            $categoryId = $_GET['category_id'] ?? null;

            $query = Stream::query();

            if ($search) {
                $query->where('stream_display_name', 'LIKE', "%{$search}%");
            }

            if ($categoryId) {
                $query->where('cat_id', $categoryId);
            }

            $streams = $query->get(['id', 'stream_display_name', 'cat_id', 'running']);

            $formatted = $streams->map(function($stream) use ($bouquetId) {
                $inBouquet = false;
                if ($bouquetId) {
                    $bouquet = Bouquet::find($bouquetId);
                    $inBouquet = $bouquet && $bouquet->hasStream($stream->id);
                }

                return [
                    'id' => $stream->id,
                    'name' => $stream->stream_display_name ?: "Stream {$stream->id}",
                    'category_id' => $stream->cat_id,
                    'running' => $stream->running == 1,
                    'in_bouquet' => $inBouquet
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formatted,
                'total' => $formatted->count()
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
