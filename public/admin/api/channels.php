<?php
/**
 * Channels API Endpoint
 *
 * Manage TV channels linked to streams and organized in bouquets
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
            $categoryId = $_GET['category_id'] ?? null;
            $bouquetId = $_GET['bouquet_id'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = Channel::with(['stream', 'category']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('epg_id', 'LIKE', "%{$search}%");
                });
            }

            if ($active !== null) {
                $query->where('is_active', $active);
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($bouquetId) {
                $query->whereHas('bouquets', function($q) use ($bouquetId) {
                    $q->where('bouquets.id', $bouquetId);
                });
            }

            $total = $query->count();
            $channels = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $channels->map(function($channel) {
                return [
                    'id' => $channel->id,
                    'stream_id' => $channel->stream_id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'category_id' => $channel->category_id,
                    'category_name' => $channel->category ? $channel->category->name : null,
                    'logo_url' => $channel->logo_url,
                    'epg_id' => $channel->epg_id,
                    'is_active' => $channel->is_active,
                    'is_running' => $channel->stream && $channel->stream->running == 1,
                    'bouquet_count' => $channel->bouquets()->count(),
                    'stream_name' => $channel->stream ? $channel->stream->stream_display_name : null,
                    'created_at' => $channel->created_at,
                    'updated_at' => $channel->updated_at
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
            if (!$id) throw new Exception('Channel ID required');

            $channel = Channel::with(['stream', 'category', 'bouquets'])->find($id);
            if (!$channel) throw new Exception('Channel not found');

            $bouquets = $channel->bouquets->map(function($bouquet) {
                return [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'sort_order' => $bouquet->pivot->sort_order
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $channel->id,
                    'stream_id' => $channel->stream_id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'category_id' => $channel->category_id,
                    'category_name' => $channel->category ? $channel->category->name : null,
                    'logo_url' => $channel->logo_url,
                    'epg_id' => $channel->epg_id,
                    'is_active' => $channel->is_active,
                    'stream' => $channel->stream ? [
                        'id' => $channel->stream->id,
                        'name' => $channel->stream->stream_display_name,
                        'running' => $channel->stream->running == 1
                    ] : null,
                    'bouquets' => $bouquets,
                    'bouquet_count' => $bouquets->count(),
                    'created_at' => $channel->created_at,
                    'updated_at' => $channel->updated_at
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['stream_id'])) {
                throw new Exception('Stream ID is required');
            }

            if (!isset($input['name'])) {
                throw new Exception('Channel name is required');
            }

            // Verify stream exists
            $stream = Stream::find($input['stream_id']);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            // Check if channel already exists for this stream
            if (Channel::where('stream_id', $input['stream_id'])->exists()) {
                throw new Exception('A channel already exists for this stream');
            }

            $channel = new Channel();
            $channel->stream_id = $input['stream_id'];
            $channel->name = $input['name'];
            $channel->description = $input['description'] ?? null;
            $channel->category_id = $input['category_id'] ?? null;
            $channel->logo_url = $input['logo_url'] ?? null;
            $channel->epg_id = $input['epg_id'] ?? null;
            $channel->is_active = $input['is_active'] ?? 1;
            $channel->save();

            echo json_encode([
                'success' => true,
                'message' => 'Channel created successfully',
                'data' => ['id' => $channel->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Channel ID required');

            $channel = Channel::find($id);
            if (!$channel) throw new Exception('Channel not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['name'])) $channel->name = $input['name'];
            if (isset($input['description'])) $channel->description = $input['description'];
            if (isset($input['category_id'])) $channel->category_id = $input['category_id'];
            if (isset($input['logo_url'])) $channel->logo_url = $input['logo_url'];
            if (isset($input['epg_id'])) $channel->epg_id = $input['epg_id'];
            if (isset($input['is_active'])) $channel->is_active = $input['is_active'];
            $channel->save();

            echo json_encode([
                'success' => true,
                'message' => 'Channel updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Channel ID required');

            $channel = Channel::find($id);
            if (!$channel) throw new Exception('Channel not found');

            // Check if channel is in any bouquets
            $bouquetCount = $channel->bouquets()->count();
            if ($bouquetCount > 0) {
                throw new Exception("Cannot delete channel assigned to {$bouquetCount} bouquet(s). Remove it from bouquets first.");
            }

            $channel->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Channel deleted successfully'
            ]);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Channel ID required');

            $channel = Channel::find($id);
            if (!$channel) throw new Exception('Channel not found');

            $channel->is_active = !$channel->is_active;
            $channel->save();

            echo json_encode([
                'success' => true,
                'message' => 'Channel status updated',
                'data' => ['is_active' => $channel->is_active]
            ]);
            break;

        case 'sync_from_streams':
            // Create channels from streams that don't have channels yet
            $created = Channel::syncFromStreams();

            echo json_encode([
                'success' => true,
                'message' => "{$created} channel(s) created from streams",
                'data' => ['created' => $created]
            ]);
            break;

        case 'create_from_stream':
            $streamId = $_GET['stream_id'] ?? null;
            if (!$streamId) throw new Exception('Stream ID required');

            // Check if channel already exists
            if (Channel::where('stream_id', $streamId)->exists()) {
                throw new Exception('Channel already exists for this stream');
            }

            $channel = Channel::createFromStream($streamId);

            if (!$channel) {
                throw new Exception('Stream not found');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Channel created from stream',
                'data' => ['id' => $channel->id]
            ]);
            break;

        case 'available_streams':
            // Get streams that don't have channels yet
            $streams = Stream::whereNotIn('id', function($query) {
                $query->select('stream_id')->from('channels');
            })->get(['id', 'stream_display_name', 'category_id', 'running']);

            $formatted = $streams->map(function($stream) {
                return [
                    'id' => $stream->id,
                    'name' => $stream->stream_display_name ?: "Stream {$stream->id}",
                    'category_id' => $stream->category_id,
                    'running' => $stream->running == 1
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
