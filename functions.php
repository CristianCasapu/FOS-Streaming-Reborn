<?php

function redirect($url, $time)
{
    echo "<script>
                window.setTimeout(function(){
                    window.location.href = '" . $url . "';
                }, " . $time . ");
            </script>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("location: index.php");
}

function logincheck()
{
    if (!isset($_SESSION['user_id'])) {
        header("location: index.php");
    }
}

function lists($list, $column)
{
    $columns = [];
    foreach ($list->toArray() as $key => $value) {
        array_push($columns, $value[$column]);
    }

    return $columns;
}

function checkPid($pid)
{
    exec("ps $pid", $output, $result);
    return count($output) >= 2 ? true : false;
}

function stop_stream($id)
{
    $stream = Stream::find($id);
    $setting = Setting::first();

    // Get streams path from settings or auto-detect
    $streamsPath = $setting->streams_path ?: \App\Services\PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';

    if (checkPid($stream->pid)) {
        shell_exec("kill -9 " . $stream->pid);
        // Clean up DASH segments
        shell_exec("/bin/rm -rf " . $streamsPath . "/dash/" . $stream->id . " 2>/dev/null");
        // Clean up HLS segments
        shell_exec("/bin/rm -rf " . $streamsPath . "/hls/" . $stream->id . " 2>/dev/null");
        shell_exec("/bin/rm -f " . $streamsPath . "/hls/" . $stream->id . "_*.m3u8 2>/dev/null");
        shell_exec("/bin/rm -f " . $streamsPath . "/hls/" . $stream->id . "_*.ts 2>/dev/null");
    }
    $stream->pid = null;
    $stream->updateState('stopped'); // Use state as single source of truth
    sleep(2);
}


function getTranscode($id, $streamnumber = null)
{
    $stream = Stream::find($id);
    $setting = Setting::first();
    $trans = $stream->transcode;
    $ffmpeg = $setting->ffmpeg_path;
    $url = $stream->streamurl;
    if ($streamnumber == 2) {
        $url = $stream->streamurl2;
    }
    if ($streamnumber == 3) {
        $url = $stream->streamurl3;
    }

    // Get streaming protocol settings (use auto-detection for defaults)
    $streamingProtocol = $setting->streaming_protocol ?? 'both';
    $rtmpPort = $setting->rtmp_port ?? 1935;
    $streamsPath = $setting->streams_path ?: \App\Services\PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';
    $hlsFragment = $setting->hls_fragment ?? 3;
    $hlsPlaylistLength = $setting->hls_playlist_length ?? 60;

    // Build output based on streaming protocol
    $endofffmpeg = "";
    $endofffmpeg .= $stream->bitstreamfilter ? ' -bsf h264_mp4toannexb' : '';

    // Use RTMP push for DASH/Both protocols (nginx-rtmp handles DASH/HLS conversion)
    // Use direct HLS output only when HLS-only mode is selected
    if ($streamingProtocol === 'dash' || $streamingProtocol === 'both') {
        // Push to nginx-rtmp server which handles DASH and HLS output
        $endofffmpeg .= ' -f flv rtmp://127.0.0.1:' . $rtmpPort . '/live/' . $stream->id;
        $endofffmpeg .= ' > /dev/null 2>/dev/null & echo $!';
    } else {
        // Legacy HLS-only mode: output directly to HLS files
        $hlsListSize = intval($hlsPlaylistLength / $hlsFragment);
        $endofffmpeg .= ' -hls_flags delete_segments -hls_time ' . $hlsFragment;
        $endofffmpeg .= ' -hls_list_size ' . $hlsListSize . ' ' . $streamsPath . '/hls/' . $stream->id . '_.m3u8';
        $endofffmpeg .= ' > /dev/null 2>/dev/null & echo $!';
    }

    if ($trans) {
        $ffmpeg .= ' -y';
        $ffmpeg .= ' -probesize ' . ($trans->probesize ? $trans->probesize : '15000000');
        $ffmpeg .= ' -analyzeduration ' . ($trans->analyzeduration ? $trans->analyzeduration : '12000000');
        $ffmpeg .= ' -i ' . '"' . "$url" . '"';
        $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
        $ffmpeg .= ' -strict -2 -dn ';
        $ffmpeg .= $trans->scale ? ' -vf scale=' . ($trans->scale ? $trans->scale : '') : '';
        $ffmpeg .= $trans->audio_codec ? ' -acodec ' . $trans->audio_codec : '';
        '';
        $ffmpeg .= $trans->video_codec ? ' -vcodec ' . $trans->video_codec : '';
        $ffmpeg .= $trans->profile ? ' -profile:v ' . $trans->profile : '';
        $ffmpeg .= $trans->preset ? ' -preset ' . $trans->preset_values : '';
        $ffmpeg .= $trans->video_bitrate ? ' -b:v ' . $trans->video_bitrate . 'k' : '';
        $ffmpeg .= $trans->audio_bitrate ? ' -b:a ' . $trans->audio_bitrate . 'k' : '';
        $ffmpeg .= $trans->fps ? ' -r ' . $trans->fps : '';
        $ffmpeg .= $trans->minrate ? ' -minrate ' . $trans->minrate . 'k' : '';
        $ffmpeg .= $trans->maxrate ? ' -maxrate ' . $trans->maxrate . 'k' : '';
        $ffmpeg .= $trans->bufsize ? ' -bufsize ' . $trans->bufsize . 'k' : '';
        $ffmpeg .= $trans->aspect_ratio ? ' -aspect ' . $trans->aspect_ratio : '';
        $ffmpeg .= $trans->audio_sampling_rate ? ' -ar ' . $trans->audio_sampling_rate : '';
        $ffmpeg .= $trans->crf ? ' -crf ' . $trans->crf : '';
        $ffmpeg .= $trans->audio_channel ? ' -ac ' . $trans->audio_channel : '';
        $ffmpeg .= $stream->bitstreamfilter ? ' -bsf h264_mp4toannexb' : '';
        $ffmpeg .= $trans->threads ? ' -threads ' . $trans->threads : '';
        $ffmpeg .= $trans->deinterlance ? ' -vf yadif' : '';
        $ffmpeg .= $endofffmpeg;
        return $ffmpeg;
    }

    $ffmpeg .= ' -probesize 15000000 -analyzeduration 9000000 -i "' . $url . '"';
    $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
    $ffmpeg .= ' -c copy -c:a aac -b:a 128k';
    $ffmpeg .= $endofffmpeg;
    return $ffmpeg;
}

function getTranscodedata($id)
{
    $trans = Transcode::find($id);
    $setting = Setting::first();
    $ffmpeg = "ffmpeg";
    $ffmpeg .= ' -y';
    $ffmpeg .= ' -probesize ' . ($trans->probesize ? $trans->probesize : '15000000');
    $ffmpeg .= ' -analyzeduration ' . ($trans->analyzeduration ? $trans->analyzeduration : '12000000');
    $ffmpeg .= ' -i ' . '"' . "[input]" . '"';
    $ffmpeg .= ' -user_agent "' . ($setting->user_agent ? $setting->user_agent : 'FOS-Streaming') . '"';
    $ffmpeg .= ' -strict -2 -dn ';
    $ffmpeg .= $trans->scale ? ' -vf scale=' . ($trans->scale ? $trans->scale : '') : '';
    $ffmpeg .= $trans->audio_codec ? ' -acodec ' . $trans->audio_codec : '';
    $ffmpeg .= $trans->video_codec ? ' -vcodec ' . $trans->video_codec : '';
    $ffmpeg .= $trans->profile ? ' -profile:v ' . $trans->profile : '';
    $ffmpeg .= $trans->preset ? ' -preset ' . $trans->preset_values : '';
    $ffmpeg .= $trans->video_bitrate ? ' -b:v ' . $trans->video_bitrate . 'k' : '';
    $ffmpeg .= $trans->audio_bitrate ? ' -b:a ' . $trans->audio_bitrate . 'k' : '';
    $ffmpeg .= $trans->fps ? ' -r ' . $trans->fps : '';
    $ffmpeg .= $trans->minrate ? ' -minrate ' . $trans->minrate . 'k' : '';
    $ffmpeg .= $trans->maxrate ? ' -maxrate ' . $trans->maxrate . 'k' : '';
    $ffmpeg .= $trans->bufsize ? ' -bufsize ' . $trans->bufsize . 'k' : '';
    $ffmpeg .= $trans->aspect_ratio ? ' -aspect ' . $trans->aspect_ratio : '';
    $ffmpeg .= $trans->audio_sampling_rate ? ' -ar ' . $trans->audio_sampling_rate : '';
    $ffmpeg .= $trans->crf ? ' -crf ' . $trans->crf : '';
    $ffmpeg .= $trans->audio_channel ? ' -ac ' . $trans->audio_channel : '';
    $ffmpeg .= $trans->threads ? ' -threads ' . $trans->threads : '';
    $ffmpeg .= $trans->deinterlance ? ' -vf yadif' : '';
    $ffmpeg .= " output[HLS]";
    return $ffmpeg;
}


function start_stream($id)
{
    $stream = Stream::find($id);
    $setting = Setting::first();

    // Get streams path from settings or auto-detect for cleanup operations
    $streamsPath = $setting->streams_path ?: \App\Services\PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';

    // Helper function to clean up stream segments
    $cleanupStream = function($streamId) use ($streamsPath) {
        shell_exec("/bin/rm -rf " . $streamsPath . "/dash/" . $streamId . " 2>/dev/null");
        shell_exec("/bin/rm -rf " . $streamsPath . "/hls/" . $streamId . " 2>/dev/null");
        shell_exec("/bin/rm -f " . $streamsPath . "/hls/" . $streamId . "_*.m3u8 2>/dev/null");
        shell_exec("/bin/rm -f " . $streamsPath . "/hls/" . $streamId . "_*.ts 2>/dev/null");
    };

    // Helper function to extract codec info from stream info
    $extractCodecs = function($streaminfo, $stream) {
        $video = "";
        $audio = "";
        if (is_array($streaminfo)) {
            foreach ($streaminfo['streams'] as $info) {
                if ($video == '') {
                    $video = ($info['codec_type'] == 'video' ? $info['codec_name'] : '');
                }
                if ($audio == '') {
                    $audio = ($info['codec_type'] == 'audio' ? $info['codec_name'] : '');
                }
            }
            $stream->video_codec_name = $video;
            $stream->audio_codec_name = $audio;
        }
    };

    // Helper function to try starting with a specific URL
    $tryStartWithUrl = function($url, $urlNumber = null) use ($stream, $setting, $extractCodecs) {
        $checkstreamurl = shell_exec($setting->ffprobe_path . ' -analyzeduration 1000000 -probesize 9000000 -i "' . $url . '" -v quiet -print_format json -show_streams 2>&1');
        $streaminfo = json_decode($checkstreamurl, true);

        if ($streaminfo) {
            $pid = shell_exec(getTranscode($stream->id, $urlNumber));
            $stream->pid = trim($pid);
            $stream->state = 'running';
            $extractCodecs($streaminfo, $stream);
            return true;
        }
        return false;
    };

    $stream->checker = 0;

    if ($stream->restream) {
        // Restream mode - no PID, just mark as running
        $stream->pid = null;
        $stream->state = 'running';
    } else {
        // Try primary URL
        if ($tryStartWithUrl($stream->streamurl)) {
            // Success with primary URL
        } else {
            // Primary URL failed
            $stream->state = 'error';
            if (checkPid($stream->pid)) {
                shell_exec("kill -9 " . $stream->pid);
                $cleanupStream($stream->id);
            }

            // Try backup URL 2
            if ($stream->streamurl2) {
                $stream->checker = 2;
                if ($tryStartWithUrl($stream->streamurl2, 2)) {
                    // Success with backup URL 2
                } else {
                    // Backup URL 2 failed
                    $stream->state = 'error';
                    if (checkPid($stream->pid)) {
                        shell_exec("kill -9 " . $stream->pid);
                        $cleanupStream($stream->id);
                    }

                    // Try backup URL 3
                    if ($stream->streamurl3) {
                        $stream->checker = 3;
                        if ($tryStartWithUrl($stream->streamurl3, 3)) {
                            // Success with backup URL 3
                        } else {
                            // All URLs failed
                            $stream->state = 'error';
                            $stream->pid = null;
                        }
                    }
                }
            }
        }
    }
    $stream->save();
}


function generatEginxConfPort($port)
{
    ob_start();
    echo 'user  nginx;
worker_processes  auto;
worker_rlimit_nofile 655350;

events {
    worker_connections  65535;
    use epoll;
        accept_mutex on;
        multi_accept on;
}

http {
        include                   mime.types;
        default_type              application/octet-stream;
        sendfile                  on;
        tcp_nopush                on;
        tcp_nodelay               on;
        reset_timedout_connection on;
        gzip                      off;
        fastcgi_read_timeout      200;
        access_log                off;
        keepalive_timeout         10;
        client_max_body_size      999m;
        send_timeout              120s;
        sendfile_max_chunk        512k;
        lingering_close           off;
	server {
		listen ' . $port . ';
		root /home/fos-streaming/fos/www1/;
		server_tokens off;
		chunked_transfer_encoding off;
		rewrite ^/live/(.*)/(.*)/(.*)$ /stream.php?username=$1&password=$2&stream=$3 break;
		location ~ \.php$ {
		  try_files $uri =404;
		  fastcgi_index index.php;
		  include fastcgi_params;
		  fastcgi_buffering on;
		  fastcgi_buffers 96 32k;
		  fastcgi_buffer_size 32k;
		  fastcgi_max_temp_file_size 0;
		  fastcgi_keep_conn on;
		  fastcgi_param SCRIPT_FILENAME /home/fos-streaming/fos/www1/$fastcgi_script_name;
		  fastcgi_param SCRIPT_NAME $fastcgi_script_name;
		  fastcgi_pass 127.0.0.1:9002;
		}	
	}
	server {
		listen 7777;
		root /home/fos-streaming/fos/www/;
                index index.php index.html index.htm;
                server_tokens off;
                chunked_transfer_encoding off;
		location ~ \.php$ {
                        try_files $uri =404;
                        fastcgi_index index.php;
                        include fastcgi_params;
                        fastcgi_buffering on;
                        fastcgi_buffers 96 32k;
                        fastcgi_buffer_size 32k;
                        fastcgi_max_temp_file_size 0;
                        fastcgi_keep_conn on;
                        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
                        fastcgi_pass 127.0.0.1:9002;
		}
	}
}';
    $file = '/home/fos-streaming/fos/nginx/conf/nginx.conf';
    $current = ob_get_clean();
    file_put_contents($file, $current);
}
