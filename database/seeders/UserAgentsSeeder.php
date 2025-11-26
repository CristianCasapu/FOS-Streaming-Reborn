<?php

/**
 * User Agents Seeder
 *
 * Seeds predefined user agents for use with FFmpeg/FFprobe when connecting to streams.
 * These user agents help avoid detection/blocking by making requests look like real players.
 *
 * Usage: php artisan db:seed UserAgentsSeeder
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../models/UserAgent.php';

class UserAgentsSeeder
{
    public function run(): void
    {
        echo "Seeding user agents...\n";

        $userAgents = $this->getUserAgents();
        $created = 0;
        $skipped = 0;

        foreach ($userAgents as $ua) {
            // Check if already exists
            $existing = UserAgent::where('user_agent', $ua['user_agent'])->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            UserAgent::create($ua);
            $created++;
        }

        // Set default user agent if none is set
        $default = UserAgent::where('is_default', 1)->first();
        if (!$default) {
            // Set VLC/3.0.20 as default (most common)
            $vlc = UserAgent::where('user_agent', 'LIKE', 'VLC/3.0.20 LibVLC/3.0.20')->first();
            if ($vlc) {
                $vlc->is_default = true;
                $vlc->save();
                echo "Set VLC/3.0.20 as default user agent\n";
            } else {
                // Fallback: set first VLC as default
                $firstVlc = UserAgent::where('user_agent', 'LIKE', 'VLC/%')->first();
                if ($firstVlc) {
                    $firstVlc->is_default = true;
                    $firstVlc->save();
                    echo "Set {$firstVlc->user_agent} as default user agent\n";
                }
            }
        }

        echo "User agents seeding complete: {$created} created, {$skipped} skipped (already exist)\n";
    }

    private function getUserAgents(): array
    {
        return [
            // VLC Players - Various Versions
            [
                'user_agent' => 'VLC/3.0.21 LibVLC/3.0.21',
                'name' => 'VLC 3.0.21',
                'version' => '3.0.21',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.20 LibVLC/3.0.20',
                'name' => 'VLC 3.0.20',
                'version' => '3.0.20',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.20 LibVLC/3.0.21',
                'name' => 'VLC 3.0.20 (LibVLC 3.0.21)',
                'version' => '3.0.20',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.20-git LibVLC/3.0.20-git',
                'name' => 'VLC 3.0.20-git',
                'version' => '3.0.20-git',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.19 LibVLC/3.0.19',
                'name' => 'VLC 3.0.19',
                'version' => '3.0.19',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.18 LibVLC/3.0.18',
                'name' => 'VLC 3.0.18',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.16 LibVLC/3.0.16',
                'name' => 'VLC 3.0.16',
                'version' => '3.0.16',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.12 LibVLC/3.0.12',
                'name' => 'VLC 3.0.12',
                'version' => '3.0.12',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.11.1 LibVLC/3.0.11.1',
                'name' => 'VLC 3.0.11.1',
                'version' => '3.0.11.1',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.8 LibVLC/3.0',
                'name' => 'VLC 3.0.8',
                'version' => '3.0.8',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.4 LibVLC/3.0.4',
                'name' => 'VLC 3.0.4',
                'version' => '3.0.4',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.2.LibVLC/3.0.2',
                'name' => 'VLC 3.0.2',
                'version' => '3.0.2',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/3.0.0-git-20150129-0002 LibVLC/3.0.0-git-20150129-0002',
                'name' => 'VLC 3.0.0-git',
                'version' => '3.0.0-git',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/2.2.6 LibVLC/2.2.6',
                'name' => 'VLC 2.2.6',
                'version' => '2.2.6',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC / 2.2.6 LibVLC / 2.2.6',
                'name' => 'VLC 2.2.6 (alt)',
                'version' => '2.2.6',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/2.2.0-git LibVLC/2.2.0-git',
                'name' => 'VLC 2.2.0-git',
                'version' => '2.2.0-git',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC/2.0.5 LibVLC/2.0.5',
                'name' => 'VLC 2.0.5',
                'version' => '2.0.5',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC media player 3.0.11 Vetina',
                'name' => 'VLC 3.0.11 Vetinari',
                'version' => '3.0.11',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC media player - version 0.8.5 Janus - (c) 1996-2006 the VideoLAN team',
                'name' => 'VLC 0.8.5 Janus (Legacy)',
                'version' => '0.8.5',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'VLC,Mozilla/5.0',
                'name' => 'VLC Mozilla Hybrid',
                'version' => null,
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],

            // TV and Streaming Apps
            [
                'user_agent' => 'TV-Lite/0.7.6 (libVLC 3.0.20 Vetinari)',
                'name' => 'TV-Lite',
                'version' => '0.7.6',
                'os' => null,
                'hardware_type' => 'tv',
                'enabled' => true,
            ],
            [
                'user_agent' => 'MaxPlayer LibVLC/3.0.19',
                'name' => 'MaxPlayer',
                'version' => '3.0.19',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'UniPlayer-7c957ae0fbb07e06dd3814a7673fcba3 LibVLC/3.0.19',
                'name' => 'UniPlayer',
                'version' => '3.0.19',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'iptv_smarters_player_lite/1.0.5 VLC/3.1.2 LibVLC/3.1.2',
                'name' => 'IPTV Smarters Lite',
                'version' => '1.0.5',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'ivandiaz LibVLC/3.0.18',
                'name' => 'ivandiaz Player',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => '9XtreamPlayer LibVLC/3.0.18',
                'name' => '9Xtream Player',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => '9XtreamPlayerq LibVLC/3.0.18',
                'name' => '9Xtream Player (alt)',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'GsE LibVLC/3.0.18',
                'name' => 'GSE Smart IPTV',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'http LibVLC/3.0.18-rc2',
                'name' => 'HTTP LibVLC Client',
                'version' => '3.0.18-rc2',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],
            [
                'user_agent' => 'user LibVLC/3.0.18',
                'name' => 'Generic LibVLC User',
                'version' => '3.0.18',
                'os' => null,
                'hardware_type' => null,
                'enabled' => true,
            ],

            // OTT Navigator - Android
            [
                'user_agent' => 'OTT Navigator/1.7.1.6 (Linux;Android 12; tgoevv) VLC/3.0.18 Vetinari LibVLC/3.0.18',
                'name' => 'OTT Navigator Android 12',
                'version' => '1.7.1.6',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],
            [
                'user_agent' => 'OTT Navigator/1.6.9.4 (Linux;Android 9; 1d0yldp) VLC/3.0.16 Vetinari LibVLC/3.0.16',
                'name' => 'OTT Navigator Android 9',
                'version' => '1.6.9.4',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],
            [
                'user_agent' => 'OTT Navigator/1.6.9.4 (Linux;Android 9; 10984fj) VLC/3.0.16 Vetinari LibVLC/3.0.16',
                'name' => 'OTT Navigator Android 9 (alt)',
                'version' => '1.6.9.4',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],
            [
                'user_agent' => 'OTT TV/1.7.1.6 (Linux;Android 9; x1qb43) VLC/3.0.18 Vetinari LibVLC/3.0.18',
                'name' => 'OTT TV Android 9',
                'version' => '1.7.1.6',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],

            // Android Players
            [
                'user_agent' => 'Ultimate IPTV Playlist Loader PRO2.60 TvVLC _ Android 9 _ MBOX LibVLC/3.0.11.1',
                'name' => 'Ultimate IPTV Loader PRO',
                'version' => '2.60',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (Linux; Android 11; t3pi9e9VlC; U; en) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.153 Mobile AT/1.0.2 Safari/537.36',
                'name' => 'Android 11 Chrome Mobile',
                'version' => '117.0',
                'os' => 'Android',
                'hardware_type' => 'mobile',
                'enabled' => true,
            ],

            // iOS Safari - Various Versions
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 1125x2436 AE_LANG(de-CH) AE_CURRENCY(CHF) AliApp(AE/8.115.0) WindVane/8.7.2 AE_LANG(de-CH) AE_CURRENCY(CHF) TTID/ZvLcFhqm/IgDALdWvItXwi7y UT4Aplus/0.0.5 WK',
                'name' => 'iPhone iOS 17.6.1 AliExpress',
                'version' => '17.6.1',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_1_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15VLCR Safari/619.20.5',
                'name' => 'iPhone iOS 16.1 Safari',
                'version' => '16.1',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Mobile/3LVLCE Safari/617.26',
                'name' => 'iPhone iOS 16 Safari',
                'version' => '16.0',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_4 like Mac OS X) AppleWebKit/624.11 (KHTML, like Gecko) Version/11.1.91 Mobile/92VLCA Safari/624.11',
                'name' => 'iPhone iOS 15.4 Safari',
                'version' => '15.4',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_4 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Version/11.1 Mobile/RGVLCX Safari/631.26.10',
                'name' => 'iPhone iOS 15.4 Safari (alt)',
                'version' => '15.4',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_3 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Version/12.7 Mobile/3UVLCA Safari/622.18',
                'name' => 'iPhone iOS 15.3 Safari',
                'version' => '15.3',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_2 like Mac OS X) AppleWebKit/600.7.10 (KHTML, like Gecko) Version/14.3 Mobile/6PVLCQ Safari/630.15.12',
                'name' => 'iPhone iOS 15.2 Safari',
                'version' => '15.2',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/624.33 (KHTML, like Gecko) Version/14.3 Mobile/CVLCN0 Safari/624.33',
                'name' => 'iPhone iOS 14.4 Safari',
                'version' => '14.4',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/616.31.10 (KHTML, like Gecko) Version/10.2.76 Mobile/FVVLCM Safari/616.31.10',
                'name' => 'iPhone iOS 14.2 Safari',
                'version' => '14.2',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_2 like Mac OS X) AppleWebKit/600.2.10 (KHTML, like Gecko) Version/11.4 Mobile/VLCCNL Safari/620.26.15',
                'name' => 'iPhone iOS 14.2 Safari (alt)',
                'version' => '14.2',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_1 like Mac OS X) AppleWebKit/621.15.8 (KHTML, like Gecko) Version/13.1 Mobile/DWVLCP Safari/621.15.8',
                'name' => 'iPhone iOS 14.1 Safari',
                'version' => '14.1',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_1 like Mac OS X) AppleWebKit/616.16 (KHTML, like Gecko) Version/13.7 Mobile/VLCN3U Safari/616.16',
                'name' => 'iPhone iOS 14.1 Safari (alt)',
                'version' => '14.1',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
            [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3 like Mac OS X) AppleWebKit/631.8 (KHTML, like Gecko) Version/12.7 Mobile/VLCRJC Safari/631.8',
                'name' => 'iPhone iOS 13.3 Safari',
                'version' => '13.3',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],

            // Apple CoreMedia
            [
                'user_agent' => 'AppleCoreMedia/1.0.0.14D27 (iPhone; U; CPU OS 10_2_1 like Mac OS X; en_us) LibVLC/3.0.3',
                'name' => 'Apple CoreMedia iOS 10.2.1',
                'version' => '10.2.1',
                'os' => 'iOS',
                'hardware_type' => 'phone',
                'enabled' => true,
            ],
        ];
    }
}

// Run seeder if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $seeder = new UserAgentsSeeder();
    $seeder->run();
}
