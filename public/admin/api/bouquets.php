<?php
/**
 * Bouquets API Endpoint
 *
 * Manage bouquets (groups) of TV channels
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
            $withChannels = isset($_GET['with_channels']) && $_GET['with_channels'] == '1';

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

            $formatted = $bouquets->map(function($bouquet) use ($withChannels) {
                $data = [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'description' => $bouquet->description,
                    'is_active' => $bouquet->is_active,
                    'sort_order' => $bouquet->sort_order,
                    'channel_count' => $bouquet->channels()->count(),
                    'package_count' => $bouquet->packages()->count(),
                    'created_at' => $bouquet->created_at,
                    'updated_at' => $bouquet->updated_at
                ];

                if ($withChannels) {
                    $data['channels'] = $bouquet->channels->map(function($channel) {
                        return [
                            'id' => $channel->id,
                            'name' => $channel->name,
                            'sort_order' => $channel->pivot->sort_order
                        ];
                    });
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

            $channels = $bouquet->channels->map(function($channel) {
                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'stream_id' => $channel->stream_id,
                    'category_id' => $channel->category_id,
                    'logo_url' => $channel->logo_url,
                    'is_active' => $channel->is_active,
                    'sort_order' => $channel->pivot->sort_order
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
                    'channels' => $channels,
                    'packages' => $packages,
                    'channel_count' => $channels->count(),
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
            $bouquet->save();

            // Assign channels if provided
            if (isset($input['channel_ids']) && is_array($input['channel_ids'])) {
                $channelsWithOrder = [];
                $order = 0;
                foreach ($input['channel_ids'] as $channelId) {
                    $channelsWithOrder[$channelId] = ['sort_order' => $order++];
                }
                $bouquet->channels()->sync($channelsWithOrder);
            }

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
            $bouquet->save();

            // Update channels if provided
            if (isset($input['channel_ids']) && is_array($input['channel_ids'])) {
                $channelsWithOrder = [];
                $order = 0;
                foreach ($input['channel_ids'] as $channelId) {
                    $channelsWithOrder[$channelId] = ['sort_order' => $order++];
                }
                $bouquet->channels()->sync($channelsWithOrder);
            }

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

        case 'assign_channels':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $channelIds = $input['channel_ids'] ?? [];

            if (!is_array($channelIds)) {
                throw new Exception('channel_ids must be an array');
            }

            $channelsWithOrder = [];
            $order = 0;
            foreach ($channelIds as $channelId) {
                $channelsWithOrder[$channelId] = ['sort_order' => $order++];
            }

            $bouquet->channels()->sync($channelsWithOrder);

            echo json_encode([
                'success' => true,
                'message' => 'Channels assigned successfully',
                'data' => [
                    'channel_count' => $bouquet->channels()->count()
                ]
            ]);
            break;

        case 'remove_channel':
            $id = $_GET['id'] ?? null;
            $channelId = $_GET['channel_id'] ?? null;

            if (!$id) throw new Exception('Bouquet ID required');
            if (!$channelId) throw new Exception('Channel ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $bouquet->channels()->detach($channelId);

            echo json_encode([
                'success' => true,
                'message' => 'Channel removed from bouquet'
            ]);
            break;

        case 'reorder_channels':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Bouquet ID required');

            $bouquet = Bouquet::find($id);
            if (!$bouquet) throw new Exception('Bouquet not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $channelIds = $input['channel_ids'] ?? [];

            if (!is_array($channelIds)) {
                throw new Exception('channel_ids must be an array');
            }

            $order = 0;
            foreach ($channelIds as $channelId) {
                $bouquet->channels()->updateExistingPivot($channelId, ['sort_order' => $order]);
                $order++;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Channels reordered successfully'
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
