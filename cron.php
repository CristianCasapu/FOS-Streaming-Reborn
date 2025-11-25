<?php
/**
 * Legacy Cron Script - Stream Recovery
 *
 * Note: This legacy cron is deprecated in favor of PM2 workers (stream-manager-worker, stream-monitor-worker)
 * It is kept for backward compatibility but the PM2 workers handle stream monitoring now.
 *
 * Uses 'state' as single source of truth for stream status.
 */
if (isset($_SERVER['SERVER_ADDR'])) {
    if ($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR']) {
        die('access is not permitted');
    }
}
include('config.php');
$setting = Setting::first();

// Check streams that should be running but PID died
foreach (Stream::where('pid', '!=', 0)->whereIn('state', ['running', 'starting'])->get() as $stream) {
    if (!checkPid($stream->pid)) {
        $stream->checker = 0;
        $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
        $streaminfo = (array)json_decode($checkstreamurl);
        if (count($streaminfo) > 0) {
            $pid = shell_exec(getTranscode($stream->id));
            $stream->pid = $pid;
            $stream->state = 'running';

        } else {
            $stream->state = 'error';
            shell_exec("kill -9 " . $stream->pid);
            shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");

            if ($stream->streamurl2) {
                $stream->checker = 2;
                echo "checking stream 2";
                $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl2 . '" -v  quiet -print_format json -show_streams 2>&1');
                $streaminfo = (array)json_decode($checkstreamurl);
                if (count($streaminfo) > 0) {
                    echo getTranscode($stream->id, 2);
                    $pid = shell_exec(getTranscode($stream->id, 2));
                    $stream->pid = $pid;
                    $stream->state = 'running';
                } else {
                    $stream->state = 'error';
                    shell_exec("kill -9 " . $stream->pid);
                    shell_exec("/bin/rm -r /home/fos-streaming/fos/www/" . $setting->hlsfolder . "/" . $stream->id . "*");
                    if ($stream->streamurl3) {
                        $stream->checker = 3;
                        $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl3 . '" -v  quiet -print_format json -show_streams 2>&1');
                        $streaminfo = (array)json_decode($checkstreamurl);
                        if (count($streaminfo) > 0) {
                            $pid = shell_exec(getTranscode($stream->id, 3));
                            $stream->pid = $pid;
                            $stream->state = 'running';

                        } else {
                            $stream->state = 'error';
                        }
                    }
                }
            }
        }
        $stream->save();
    }
}

// Check restream sources
foreach (Stream::where('restream', '=', 1)->whereIn('state', ['running', 'starting'])->get() as $stream) {
    $stream->checker = 0;
    $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl . '" -v  quiet -print_format json -show_streams 2>&1');
    $streaminfo = (array)json_decode($checkstreamurl);
    if (count($streaminfo) > 0) {
        $stream->checker = 0;
    } else {
        if ($stream->streamurl2) {
            $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl2 . '" -v  quiet -print_format json -show_streams 2>&1');
            $streaminfo = (array)json_decode($checkstreamurl);
            if (count($streaminfo) > 0) {
                $stream->checker = 2;
            } else { // fail 2
                if ($stream->streamurl3) {

                    $checkstreamurl = shell_exec('/usr/bin/timeout 15s ' . $setting->ffprobe_path . ' -analyzeduration 10000000 -probesize 9000000 -i "' . $stream->streamurl3 . '" -v  quiet -print_format json -show_streams 2>&1');
                    $streaminfo = (array)json_decode($checkstreamurl);
                    if (count($streaminfo) > 0) {
                        $stream->checker = 3;
                    }
                }
            }
        }
    }
    $stream->save();
}
