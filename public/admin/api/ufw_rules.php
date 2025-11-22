<?php
/**
 * UFW Rules Management API
 * Database-backed firewall rules with lockout prevention
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get all rules from database
            $rules = UfwRule::orderBy('priority', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $formatted = $rules->map(function($rule) {
                return [
                    'id' => $rule->id,
                    'port' => $rule->port,
                    'protocol' => $rule->protocol,
                    'action' => $rule->action,
                    'direction' => $rule->direction,
                    'from_ip' => $rule->from_ip,
                    'to_ip' => $rule->to_ip,
                    'interface' => $rule->interface,
                    'description' => $rule->description,
                    'is_protected' => $rule->is_protected,
                    'is_default' => $rule->is_default,
                    'enabled' => $rule->enabled,
                    'applied' => $rule->applied,
                    'priority' => $rule->priority,
                    'readable_rule' => $rule->getReadableRule(),
                    'created_at' => $rule->created_at,
                    'updated_at' => $rule->updated_at
                ];
            });

            echo json_encode(['success' => true, 'data' => $formatted]);
            break;

        case 'list_default':
            // Get only default/seeded rules
            $rules = UfwRule::where('is_default', true)
                ->orderBy('priority', 'asc')
                ->get();

            $formatted = $rules->map(function($rule) {
                return [
                    'id' => $rule->id,
                    'port' => $rule->port,
                    'protocol' => $rule->protocol,
                    'action' => $rule->action,
                    'description' => $rule->description,
                    'is_protected' => $rule->is_protected,
                    'enabled' => $rule->enabled,
                    'applied' => $rule->applied,
                    'readable_rule' => $rule->getReadableRule()
                ];
            });

            echo json_encode(['success' => true, 'data' => $formatted]);
            break;

        case 'list_custom':
            // Get only custom (non-default) rules
            $rules = UfwRule::where('is_default', false)
                ->orderBy('priority', 'asc')
                ->get();

            $formatted = $rules->map(function($rule) {
                return [
                    'id' => $rule->id,
                    'port' => $rule->port,
                    'protocol' => $rule->protocol,
                    'action' => $rule->action,
                    'description' => $rule->description,
                    'is_protected' => $rule->is_protected,
                    'enabled' => $rule->enabled,
                    'applied' => $rule->applied,
                    'readable_rule' => $rule->getReadableRule()
                ];
            });

            echo json_encode(['success' => true, 'data' => $formatted]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Rule ID required');

            $rule = UfwRule::find($id);
            if (!$rule) throw new Exception('Rule not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $rule->id,
                    'port' => $rule->port,
                    'protocol' => $rule->protocol,
                    'action' => $rule->action,
                    'direction' => $rule->direction,
                    'from_ip' => $rule->from_ip,
                    'to_ip' => $rule->to_ip,
                    'interface' => $rule->interface,
                    'description' => $rule->description,
                    'is_protected' => $rule->is_protected,
                    'is_default' => $rule->is_default,
                    'enabled' => $rule->enabled,
                    'priority' => $rule->priority
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['port']) || empty($input['port'])) {
                throw new Exception('Port is required');
            }

            if (!isset($input['description']) || empty($input['description'])) {
                throw new Exception('Description is required');
            }

            // Validate port range
            $port = (int)$input['port'];
            if ($port < 1 || $port > 65535) {
                throw new Exception('Port must be between 1 and 65535');
            }

            // Create rule
            $rule = UfwRule::create([
                'port' => $port,
                'protocol' => $input['protocol'] ?? 'tcp',
                'action' => $input['action'] ?? 'allow',
                'direction' => $input['direction'] ?? 'in',
                'from_ip' => $input['from_ip'] ?? null,
                'to_ip' => $input['to_ip'] ?? null,
                'interface' => $input['interface'] ?? null,
                'description' => $input['description'],
                'is_protected' => false, // Custom rules cannot be protected
                'is_default' => false,   // Custom rules are not defaults
                'enabled' => $input['enabled'] ?? true,
                'priority' => $input['priority'] ?? 100,
                'applied' => false
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Firewall rule created successfully',
                'data' => ['id' => $rule->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Rule ID required');

            $rule = UfwRule::find($id);
            if (!$rule) throw new Exception('Rule not found');

            $input = json_decode(file_get_contents('php://input'), true);

            // Update fields
            if (isset($input['port'])) {
                $port = (int)$input['port'];
                if ($port < 1 || $port > 65535) {
                    throw new Exception('Port must be between 1 and 65535');
                }
                $rule->port = $port;
            }

            if (isset($input['protocol'])) $rule->protocol = $input['protocol'];
            if (isset($input['action'])) $rule->action = $input['action'];
            if (isset($input['direction'])) $rule->direction = $input['direction'];
            if (isset($input['from_ip'])) $rule->from_ip = $input['from_ip'];
            if (isset($input['to_ip'])) $rule->to_ip = $input['to_ip'];
            if (isset($input['interface'])) $rule->interface = $input['interface'];
            if (isset($input['description'])) $rule->description = $input['description'];
            if (isset($input['enabled'])) $rule->enabled = $input['enabled'];
            if (isset($input['priority'])) $rule->priority = $input['priority'];

            // Mark as unapplied since it changed
            $rule->applied = false;
            $rule->save();

            echo json_encode(['success' => true, 'message' => 'Rule updated successfully']);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Rule ID required');

            $rule = UfwRule::find($id);
            if (!$rule) throw new Exception('Rule not found');

            $rule->enabled = !$rule->enabled;
            $rule->applied = false; // Mark as unapplied
            $rule->save();

            echo json_encode([
                'success' => true,
                'message' => $rule->enabled ? 'Rule enabled' : 'Rule disabled',
                'data' => ['enabled' => $rule->enabled]
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Rule ID required');

            $rule = UfwRule::find($id);
            if (!$rule) throw new Exception('Rule not found');

            if ($rule->is_protected) {
                throw new Exception('Cannot delete protected rule. Protected rules are critical for server access.');
            }

            $rule->delete();
            echo json_encode(['success' => true, 'message' => 'Rule deleted successfully']);
            break;

        case 'apply_all':
            // Apply all enabled rules to UFW
            $enabledRules = UfwRule::getEnabledRules();

            if ($enabledRules->count() === 0) {
                throw new Exception('No enabled rules to apply');
            }

            // Reset UFW
            exec('sudo ufw --force reset 2>&1', $resetOutput, $resetCode);
            if ($resetCode !== 0) {
                throw new Exception('Failed to reset UFW: ' . implode(' ', $resetOutput));
            }

            // Set default policies
            exec('sudo ufw default deny incoming 2>&1');
            exec('sudo ufw default allow outgoing 2>&1');

            $applied = 0;
            $failed = [];

            foreach ($enabledRules as $rule) {
                $command = $rule->toUfwCommand() . ' 2>&1';
                exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    $rule->markApplied();
                    $applied++;
                } else {
                    $failed[] = "Port {$rule->port}: " . implode(' ', $output);
                }
            }

            // Enable UFW
            exec('sudo ufw --force enable 2>&1', $enableOutput, $enableCode);

            if ($enableCode !== 0) {
                throw new Exception('Failed to enable UFW after applying rules');
            }

            $response = [
                'success' => true,
                'message' => "Applied {$applied} rules to UFW",
                'applied' => $applied,
                'total' => $enabledRules->count()
            ];

            if (!empty($failed)) {
                $response['warnings'] = $failed;
            }

            echo json_encode($response);
            break;

        case 'apply_single':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Rule ID required');

            $rule = UfwRule::find($id);
            if (!$rule) throw new Exception('Rule not found');

            if (!$rule->enabled) {
                throw new Exception('Cannot apply disabled rule');
            }

            $command = $rule->toUfwCommand() . ' 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $rule->markApplied();
                exec('sudo ufw reload 2>&1');

                echo json_encode([
                    'success' => true,
                    'message' => 'Rule applied successfully'
                ]);
            } else {
                throw new Exception('Failed to apply rule: ' . implode(' ', $output));
            }
            break;

        case 'sync_from_ufw':
            // Sync database with current UFW rules
            exec('sudo ufw status numbered 2>/dev/null', $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Failed to get UFW status');
            }

            $ufwRuleCount = 0;
            foreach ($output as $line) {
                if (preg_match('/\[\s*(\d+)\]/', $line)) {
                    $ufwRuleCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Found {$ufwRuleCount} active rules in UFW",
                'data' => ['ufw_rules_count' => $ufwRuleCount]
            ]);
            break;

        case 'reseed_defaults':
            // Re-run the seeder
            $output = [];
            exec('php ' . escapeshellarg(base_path('scripts/seed_ufw_default_rules.php')) . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Default rules reseeded successfully',
                    'output' => $output
                ]);
            } else {
                throw new Exception('Failed to reseed defaults: ' . implode("\n", $output));
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
