<?php
/**
 * Transcodes API Endpoint
 * Manages transcode profile CRUD operations
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

            $query = Transcode::query();

            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $total = $query->count();
            $transcodes = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $transcodes->map(function($trans) {
                return [
                    'id' => $trans->id,
                    'name' => $trans->name,
                    'video_codec' => $trans->video_codec,
                    'audio_codec' => $trans->audio_codec,
                    'profile' => $trans->profile,
                    'preset_values' => $trans->preset_values,
                    'scale' => $trans->scale,
                    'aspect_ratio' => $trans->aspect_ratio,
                    'video_bitrate' => $trans->video_bitrate,
                    'audio_bitrate' => $trans->audio_bitrate,
                    'audio_channel' => $trans->audio_channel,
                    'fps' => $trans->fps,
                    'probesize' => $trans->probesize,
                    'analyzeduration' => $trans->analyzeduration,
                    'minrate' => $trans->minrate,
                    'maxrate' => $trans->maxrate,
                    'bufsize' => $trans->bufsize,
                    'audio_sampling_rate' => $trans->audio_sampling_rate,
                    'crf' => $trans->crf,
                    'threads' => $trans->threads,
                    'deinterlance' => $trans->deinterlance,
                    'created_at' => $trans->created_at,
                    'updated_at' => $trans->updated_at
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
            if (!$id) throw new Exception('Transcode ID required');

            $trans = Transcode::find($id);
            if (!$trans) throw new Exception('Transcode profile not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $trans->id,
                    'name' => $trans->name,
                    'video_codec' => $trans->video_codec,
                    'audio_codec' => $trans->audio_codec,
                    'profile' => $trans->profile,
                    'preset_values' => $trans->preset_values,
                    'scale' => $trans->scale,
                    'aspect_ratio' => $trans->aspect_ratio,
                    'video_bitrate' => $trans->video_bitrate,
                    'audio_bitrate' => $trans->audio_bitrate,
                    'audio_channel' => $trans->audio_channel,
                    'fps' => $trans->fps,
                    'probesize' => $trans->probesize,
                    'analyzeduration' => $trans->analyzeduration,
                    'minrate' => $trans->minrate,
                    'maxrate' => $trans->maxrate,
                    'bufsize' => $trans->bufsize,
                    'audio_sampling_rate' => $trans->audio_sampling_rate,
                    'crf' => $trans->crf,
                    'threads' => $trans->threads,
                    'deinterlance' => $trans->deinterlance
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['name']) || empty($input['name'])) {
                throw new Exception('Profile name is required');
            }

            // Check for duplicate name
            $exists = Transcode::where('name', '=', $input['name'])->count();
            if ($exists > 0) {
                throw new Exception('Transcode profile name already exists');
            }

            $trans = new Transcode();
            $trans->name = $input['name'];
            $trans->probesize = $input['probesize'] ?? '5000000';
            $trans->analyzeduration = $input['analyzeduration'] ?? '5000000';
            $trans->video_codec = $input['video_codec'] ?? 'libx264';
            $trans->audio_codec = $input['audio_codec'] ?? 'aac';
            $trans->profile = $input['profile'] ?? 'main';
            $trans->preset_values = $input['preset_values'] ?? 'veryfast';
            $trans->scale = $input['scale'] ?? '1920:1080';
            $trans->aspect_ratio = $input['aspect_ratio'] ?? '16:9';
            $trans->video_bitrate = $input['video_bitrate'] ?? '2500k';
            $trans->audio_channel = $input['audio_channel'] ?? '2';
            $trans->audio_bitrate = $input['audio_bitrate'] ?? '128k';
            $trans->fps = $input['fps'] ?? '25';
            $trans->minrate = $input['minrate'] ?? '2000k';
            $trans->maxrate = $input['maxrate'] ?? '3000k';
            $trans->bufsize = $input['bufsize'] ?? '5000k';
            $trans->audio_sampling_rate = $input['audio_sampling_rate'] ?? '44100';
            $trans->crf = $input['crf'] ?? '23';
            $trans->threads = $input['threads'] ?? '4';
            $trans->deinterlance = isset($input['deinterlance']) ? (int)$input['deinterlance'] : 0;
            $trans->save();

            echo json_encode([
                'success' => true,
                'message' => 'Transcode profile created',
                'data' => ['id' => $trans->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Transcode ID required');

            $trans = Transcode::find($id);
            if (!$trans) throw new Exception('Transcode profile not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['name']) && !empty($input['name'])) {
                // Check for duplicate name (excluding current profile)
                $exists = Transcode::where('name', '=', $input['name'])
                    ->where('id', '!=', $id)
                    ->count();
                if ($exists > 0) {
                    throw new Exception('Transcode profile name already exists');
                }
                $trans->name = $input['name'];
            }

            if (isset($input['probesize'])) $trans->probesize = $input['probesize'];
            if (isset($input['analyzeduration'])) $trans->analyzeduration = $input['analyzeduration'];
            if (isset($input['video_codec'])) $trans->video_codec = $input['video_codec'];
            if (isset($input['audio_codec'])) $trans->audio_codec = $input['audio_codec'];
            if (isset($input['profile'])) $trans->profile = $input['profile'];
            if (isset($input['preset_values'])) $trans->preset_values = $input['preset_values'];
            if (isset($input['scale'])) $trans->scale = $input['scale'];
            if (isset($input['aspect_ratio'])) $trans->aspect_ratio = $input['aspect_ratio'];
            if (isset($input['video_bitrate'])) $trans->video_bitrate = $input['video_bitrate'];
            if (isset($input['audio_channel'])) $trans->audio_channel = $input['audio_channel'];
            if (isset($input['audio_bitrate'])) $trans->audio_bitrate = $input['audio_bitrate'];
            if (isset($input['fps'])) $trans->fps = $input['fps'];
            if (isset($input['minrate'])) $trans->minrate = $input['minrate'];
            if (isset($input['maxrate'])) $trans->maxrate = $input['maxrate'];
            if (isset($input['bufsize'])) $trans->bufsize = $input['bufsize'];
            if (isset($input['audio_sampling_rate'])) $trans->audio_sampling_rate = $input['audio_sampling_rate'];
            if (isset($input['crf'])) $trans->crf = $input['crf'];
            if (isset($input['threads'])) $trans->threads = $input['threads'];
            if (isset($input['deinterlance'])) $trans->deinterlance = (int)$input['deinterlance'];

            $trans->save();

            echo json_encode(['success' => true, 'message' => 'Transcode profile updated']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Transcode ID required');

            $trans = Transcode::find($id);
            if (!$trans) throw new Exception('Transcode profile not found');

            $trans->delete();
            echo json_encode(['success' => true, 'message' => 'Transcode profile deleted']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
