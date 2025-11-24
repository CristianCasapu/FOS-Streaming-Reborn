<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

class SettingsSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     *
     * Seed default settings with all columns from columnar schema
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Check if settings already exist
        $settingsExist = Capsule::table('settings')->where('id', 1)->exists();

        if (!$settingsExist) {
            Capsule::table('settings')->insert([
                'id' => 1,

                // Core System Settings
                'ffmpeg_path' => '/usr/local/bin/ffmpeg',
                'ffprobe_path' => '/usr/local/bin/ffprobe',
                'webip' => null,
                'webport' => 8000,
                'hlsfolder' => '/tmp/hls',
                'logourl' => '/assets/logo-default.svg',
                'faviconurl' => '/favicon.ico',
                'user_agent' => 'FOS-Streaming/v70.0',

                // Sudo & System Commands
                'sudo_user' => null,
                'sudo_password' => null,
                'system_commands_enabled' => 0,
                'last_command_at' => null,

                // Trial Subscription Settings
                'trial_duration_hours' => 24,
                'trial_enabled' => 1,
                'trial_requires_approval' => 0,
                'max_trials_per_user' => 1,

                // Device Fingerprinting & Security Settings
                'device_concurrent_stream_grace_seconds' => 30,
                'device_session_timeout_minutes' => 60,
                'device_max_registration_per_day' => 5,
                'device_fingerprint_ttl_days' => 365,

                // Device Violation Thresholds
                'device_violation_threshold_low' => 3,
                'device_violation_threshold_medium' => 5,
                'device_violation_threshold_high' => 10,
                'device_violation_threshold_critical' => 15,
                'device_violation_window_hours' => 24,
                'device_location_accuracy_km' => 100,

                // Timestamps
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($this->command) {
                $this->command->info('Settings seeded successfully with all 29 columns!');
            }
        } else {
            // Update existing settings to ensure all new fields are set
            Capsule::table('settings')->where('id', 1)->update([
                // Core System Settings (use COALESCE to keep existing values)
                'ffmpeg_path' => Capsule::raw("COALESCE(ffmpeg_path, '/usr/local/bin/ffmpeg')"),
                'ffprobe_path' => Capsule::raw("COALESCE(ffprobe_path, '/usr/local/bin/ffprobe')"),
                'webport' => Capsule::raw('COALESCE(webport, 8000)'),
                'hlsfolder' => Capsule::raw("COALESCE(hlsfolder, '/tmp/hls')"),
                'logourl' => Capsule::raw("COALESCE(logourl, '/assets/logo-default.svg')"),
                'faviconurl' => Capsule::raw("COALESCE(faviconurl, '/favicon.ico')"),
                'user_agent' => Capsule::raw("COALESCE(user_agent, 'FOS-Streaming/v70.0')"),

                // Sudo & System Commands
                'system_commands_enabled' => Capsule::raw('COALESCE(system_commands_enabled, 0)'),

                // Trial Settings
                'trial_duration_hours' => Capsule::raw('COALESCE(trial_duration_hours, 24)'),
                'trial_enabled' => Capsule::raw('COALESCE(trial_enabled, 1)'),
                'trial_requires_approval' => Capsule::raw('COALESCE(trial_requires_approval, 0)'),
                'max_trials_per_user' => Capsule::raw('COALESCE(max_trials_per_user, 1)'),

                // Device Security Settings
                'device_concurrent_stream_grace_seconds' => Capsule::raw('COALESCE(device_concurrent_stream_grace_seconds, 30)'),
                'device_session_timeout_minutes' => Capsule::raw('COALESCE(device_session_timeout_minutes, 60)'),
                'device_max_registration_per_day' => Capsule::raw('COALESCE(device_max_registration_per_day, 5)'),
                'device_fingerprint_ttl_days' => Capsule::raw('COALESCE(device_fingerprint_ttl_days, 365)'),

                // Device Violation Thresholds
                'device_violation_threshold_low' => Capsule::raw('COALESCE(device_violation_threshold_low, 3)'),
                'device_violation_threshold_medium' => Capsule::raw('COALESCE(device_violation_threshold_medium, 5)'),
                'device_violation_threshold_high' => Capsule::raw('COALESCE(device_violation_threshold_high, 10)'),
                'device_violation_threshold_critical' => Capsule::raw('COALESCE(device_violation_threshold_critical, 15)'),
                'device_violation_window_hours' => Capsule::raw('COALESCE(device_violation_window_hours, 24)'),
                'device_location_accuracy_km' => Capsule::raw('COALESCE(device_location_accuracy_km, 100)'),

                'updated_at' => $timestamp,
            ]);

            if ($this->command) {
                $this->command->info('Settings updated with new columns!');
            }
        }
    }
}
