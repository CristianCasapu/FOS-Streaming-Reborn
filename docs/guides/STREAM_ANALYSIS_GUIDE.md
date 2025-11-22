# Stream Analysis with FFprobe

Complete guide to automatic stream analysis and profiling in FOS-Streaming v70.

## Overview

FOS-Streaming v70 includes a sophisticated stream analysis system that automatically tests and profiles imported streams using FFprobe. This system:

- ✅ Analyzes video/audio codecs, resolutions, bitrates
- ✅ Determines optimal playback profiles
- ✅ Calculates stream health scores
- ✅ Provides recommended settings for each stream
- ✅ Runs automatically after import
- ✅ Can be scheduled via cron for continuous monitoring

## Table of Contents

1. [How It Works](#how-it-works)
2. [Database Schema](#database-schema)
3. [FFprobe Profiles](#ffprobe-profiles)
4. [API Endpoints](#api-endpoints)
5. [Background Jobs](#background-jobs)
6. [Usage Examples](#usage-examples)
7. [Troubleshooting](#troubleshooting)

---

## How It Works

### Automatic Analysis Flow

```
1. Stream Import
   ↓
2. Stream Created in Database (analysis_status = 'pending')
   ↓
3. Background Analysis Triggered
   ↓
4. FFprobe Analyzes Stream
   ↓
5. Results Saved to Database
   ↓
6. Technical Summary Available in UI
```

### What Gets Analyzed

**Container/Format Information:**
- Format name (mpegts, hls, rtmp, etc.)
- Total bitrate
- Duration (for VOD)
- File size (for VOD)

**Video Stream:**
- Codec (h264, h265, vp9, etc.)
- Profile (High, Main, Baseline)
- Resolution (width x height)
- Frame rate (FPS)
- Video bitrate
- Pixel format
- Aspect ratio

**Audio Stream:**
- Codec (aac, mp3, ac3, etc.)
- Channels (Stereo, 5.1, etc.)
- Sample rate
- Audio bitrate
- Language

**Health Metrics:**
- Health score (0-100)
- Stream accessibility
- Response time
- Uptime percentage

---

## Database Schema

### New Columns in `streams` Table

```sql
-- Analysis Status
analysis_status         ENUM('pending', 'analyzing', 'completed', 'failed')
last_analyzed          DATETIME
analysis_error         TEXT

-- Stream Type & Format
stream_type            ENUM('live', 'vod', 'radio')
container_format       VARCHAR(50)
duration               DECIMAL(12,3)
bitrate                INT UNSIGNED
file_size              BIGINT UNSIGNED

-- Video Characteristics
video_codec            VARCHAR(50)
video_profile          VARCHAR(50)
video_width            INT UNSIGNED
video_height           INT UNSIGNED
video_fps              DECIMAL(8,3)
video_bitrate          INT UNSIGNED
pixel_format           VARCHAR(50)
aspect_ratio           VARCHAR(20)

-- Audio Characteristics
audio_codec            VARCHAR(50)
audio_channels         TINYINT UNSIGNED
audio_sample_rate      INT UNSIGNED
audio_bitrate          INT UNSIGNED
audio_language         VARCHAR(10)

-- Health & Quality
health_score           TINYINT UNSIGNED
packet_loss            DECIMAL(5,2)
average_response_time  INT UNSIGNED
uptime_percentage      DECIMAL(5,2)

-- FFprobe Profile
ffprobe_profile        VARCHAR(100)
ffprobe_raw_json       LONGTEXT
recommended_settings   JSON
```

### Running the Migration

```bash
mysql -u fos_dev -p fos_streaming < database/migrations/add_stream_analysis_fields.sql
```

---

## FFprobe Profiles

The system automatically determines the best profile for each stream based on its characteristics:

| Profile | Resolution | Use Case |
|---------|-----------|----------|
| `uhd_4k` | 3840x2160+ | 4K Ultra HD content |
| `fhd_hevc` | 1920x1080+ | Full HD with HEVC/H.265 codec |
| `fhd_h264` | 1920x1080+ | Full HD with H.264 codec |
| `hd_720p` | 1280x720+ | HD 720p content |
| `sd_high` | 720x576+ | Standard definition high quality |
| `sd_standard` | <720x576 | Standard definition |
| `audio_only` | No video | Radio/audio streams |

### Recommended Settings Per Profile

Each profile includes optimized settings:

```javascript
// 4K UHD
{
  buffer_size: '20M',
  max_analyze_duration: 10000000,
  max_probe_size: 10000000,
  hwaccel: 'auto'  // For HEVC
}

// Full HD
{
  buffer_size: '10M',
  max_analyze_duration: 8000000,
  max_probe_size: 8000000
}

// HD/SD
{
  buffer_size: '5M',
  max_analyze_duration: 5000000,
  max_probe_size: 5000000
}
```

---

## API Endpoints

### Analyze Single Stream

```http
GET /admin/api/streams.php?action=analyze&id=123
```

**Response:**
```json
{
  "success": true,
  "message": "Stream analyzed successfully",
  "data": {
    "analysis_status": "completed",
    "technical_summary": {
      "resolution": "1920x1080",
      "quality": "Full HD",
      "fps": "30 fps",
      "video_codec": "H264",
      "audio_codec": "AAC",
      "audio_channels": "Stereo",
      "bitrate": "4.50 Mbps",
      "duration": "Live",
      "container": "MPEGTS",
      "profile": "fhd_h264",
      "health_score": 95
    }
  }
}
```

### Analyze Multiple Streams (Batch)

```http
POST /admin/api/streams.php?action=analyze_batch
Content-Type: application/json

{
  "ids": [123, 124, 125]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Batch analysis completed",
  "data": {
    "analyzed": 2,
    "failed": 1,
    "skipped": 0
  }
}
```

### Check Stream Accessibility

Quick lightweight check if stream is accessible:

```http
GET /admin/api/streams.php?action=check_accessibility&id=123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "accessible": true,
    "url": "http://example.com/stream.m3u8"
  }
}
```

### Get Technical Information

```http
GET /admin/api/streams.php?action=get_technical_info&id=123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "HD Sports Channel",
    "analysis_status": "completed",
    "analysis_status_label": {
      "label": "success",
      "text": "Analyzed"
    },
    "last_analyzed": "2025-11-22 14:30:00",
    "technical_summary": { ... },
    "recommended_settings": {
      "buffer_size": "10M",
      "max_analyze_duration": 8000000,
      "max_probe_size": 8000000
    }
  }
}
```

---

## Background Jobs

### Manual Analysis Script

Run the analysis script manually:

```bash
# Analyze up to 10 pending streams
php scripts/analyze-streams.php

# Analyze up to 50 streams
php scripts/analyze-streams.php --limit=50

# Analyze specific stream
php scripts/analyze-streams.php --stream-id=123

# Force re-analyze all streams
php scripts/analyze-streams.php --force --limit=100

# Verbose output
php scripts/analyze-streams.php --verbose
```

### Cron Job Setup

Add to crontab for automatic analysis:

```bash
# Edit crontab
crontab -e

# Analyze 50 streams every hour
0 * * * * cd /home/fos-streaming/fos/www && php scripts/analyze-streams.php --limit=50 >> /var/log/fos-analysis.log 2>&1

# Analyze pending streams every 30 minutes
*/30 * * * * cd /home/fos-streaming/fos/www && php scripts/analyze-streams.php --limit=20 >> /var/log/fos-analysis.log 2>&1

# Full re-analysis daily at 3 AM
0 3 * * * cd /home/fos-streaming/fos/www && php scripts/analyze-streams.php --force --limit=500 >> /var/log/fos-analysis.log 2>&1
```

### Monitoring Logs

```bash
# Watch live analysis
tail -f /var/log/fos-analysis.log

# View recent analysis
tail -100 /var/log/fos-analysis.log

# Search for failures
grep "ERROR" /var/log/fos-analysis.log
```

---

## Usage Examples

### Frontend Integration

The import wizard automatically triggers analysis:

```javascript
// Import streams
const response = await streamsAPI.create({
  name: 'My Stream',
  stream_source: 'http://example.com/stream.m3u8',
  cat_id: 5
});

const streamId = response.data.data.id;

// Analysis is triggered automatically in background
// Check status later
const techInfo = await streamsAPI.getTechnicalInfo(streamId);
console.log(techInfo.data.technical_summary);
```

### Manual Trigger from UI

```javascript
// Analyze single stream
await streamsAPI.analyze(streamId);

// Analyze multiple selected streams
const selectedIds = [123, 124, 125];
await streamsAPI.analyzeBatch(selectedIds);

// Check if stream is accessible first
const check = await streamsAPI.checkAccessibility(streamId);
if (check.data.accessible) {
  await streamsAPI.analyze(streamId);
}
```

### PHP Backend Usage

```php
use App\Services\FFprobeService;

// Initialize service
$ffprobeService = new FFprobeService();

// Analyze a stream
$analysis = $ffprobeService->analyzeStream('http://example.com/stream.m3u8', true);

// Update stream with results
$stream = Stream::find(123);
$stream->updateFromAnalysis($analysis);

// Get technical summary
$summary = $stream->getTechnicalSummary();
print_r($summary);
```

---

## Troubleshooting

### FFprobe Not Found

**Error:** `FFprobe not found at: /usr/bin/ffprobe`

**Solution:**
```bash
# Install FFmpeg (includes FFprobe)
sudo apt-get update
sudo apt-get install ffmpeg

# Or set custom path in .env
FFPROBE_PATH=/usr/local/bin/ffprobe
```

### Analysis Timeout

**Error:** `FFprobe failed: Timeout`

**Solution:**
Increase timeout in FFprobeService.php:
```php
private int $timeout = 60; // Increase to 60 seconds
```

### Permission Denied

**Error:** `Permission denied`

**Solution:**
```bash
# Ensure www-data user can execute ffprobe
sudo chmod +x /usr/bin/ffprobe

# Check FFprobe is accessible
sudo -u www-data ffprobe -version
```

### Analysis Always Fails for Live Streams

**Problem:** Live streams fail with "Duration not found"

**Solution:**
This is normal for live streams. The system automatically sets `duration` to NULL for live content. Check the `stream_type` field.

### High CPU Usage

**Problem:** Analysis script uses too much CPU

**Solution:**
```bash
# Reduce batch size
php scripts/analyze-streams.php --limit=10

# Or add delay between streams (edit script)
usleep(1000000); // 1 second delay
```

### Database Errors

**Error:** `Unknown column 'analysis_status'`

**Solution:**
Run the migration:
```bash
mysql -u fos_dev -p fos_streaming < database/migrations/add_stream_analysis_fields.sql
```

---

## Best Practices

### 1. Analysis Frequency

- **New streams:** Analyze immediately after import
- **Active streams:** Re-analyze every 24 hours
- **Failed streams:** Retry after 1 hour
- **VOD content:** Analyze once (doesn't change)

### 2. Batch Size

- **Manual testing:** 1-10 streams
- **Hourly cron:** 20-50 streams
- **Daily full scan:** 200-500 streams
- **Maximum:** 50 streams per API call

### 3. Resource Management

```bash
# Low-resource servers (1-2GB RAM)
php scripts/analyze-streams.php --limit=5

# Medium servers (4-8GB RAM)
php scripts/analyze-streams.php --limit=25

# High-resource servers (16GB+ RAM)
php scripts/analyze-streams.php --limit=100
```

### 4. Monitoring

Always check logs for issues:
```bash
# Count successful analyses today
grep "Success" /var/log/fos-analysis.log | grep "$(date +%Y-%m-%d)" | wc -l

# Count failures today
grep "Failed" /var/log/fos-analysis.log | grep "$(date +%Y-%m-%d)" | wc -l
```

---

## Performance Optimization

### 1. FFprobe Configuration

Edit `app/Services/FFprobeService.php`:

```php
// For faster analysis (less accurate)
private int $analyzeDuration = 5;  // Reduce from 10 to 5 seconds

// For more accurate analysis (slower)
private int $analyzeDuration = 15; // Increase to 15 seconds
```

### 2. Database Indexes

Already created by migration:
```sql
CREATE INDEX idx_analysis_status ON streams (analysis_status);
CREATE INDEX idx_stream_type ON streams (stream_type);
CREATE INDEX idx_video_codec ON streams (video_codec);
CREATE INDEX idx_health_score ON streams (health_score);
```

### 3. Parallel Processing

For advanced users, modify the script to use parallel processing:
```php
// Use PHP's pcntl_fork() or symfony/process
// Not included by default to avoid complexity
```

---

## FFprobe Command Reference

### Commands Used by the System

**Full Analysis:**
```bash
ffprobe -v quiet \
  -print_format json \
  -show_format \
  -show_streams \
  -show_error \
  -analyzeduration 10000000 \
  -probesize 10000000 \
  -timeout 30000000 \
  "http://example.com/stream.m3u8"
```

**Quick Check:**
```bash
ffprobe -v quiet \
  -read_intervals %+1 \
  -show_entries format=duration \
  -print_format json \
  "http://example.com/stream.m3u8"
```

### Manual Testing

Test a stream manually:
```bash
# Full analysis
ffprobe -v quiet -print_format json -show_format -show_streams \
  "http://example.com/stream.m3u8" | jq .

# Check video codec
ffprobe -v quiet -select_streams v:0 -show_entries stream=codec_name \
  -of default=noprint_wrappers=1:nokey=1 "http://example.com/stream.m3u8"

# Check resolution
ffprobe -v quiet -select_streams v:0 -show_entries stream=width,height \
  -of json "http://example.com/stream.m3u8" | jq .
```

---

## Advanced Features

### Custom Health Score Calculation

Modify in `app/Services/FFprobeService.php`:

```php
private function calculateHealthScore(array $probeData): int
{
    $score = 100;

    // Your custom logic
    if (!$hasVideo) $score -= 50;
    if (!$hasAudio) $score -= 20;
    if ($bitrate < 1000000) $score -= 10; // Low bitrate penalty

    return max(0, $score);
}
```

### Custom Profiles

Add new profiles in `determineProfile()` method:

```php
// 8K content
if ($width >= 7680) {
    return 'uhd_8k';
}

// 1440p content
if ($width >= 2560) {
    return 'qhd_1440p';
}
```

---

## Support

For issues or questions:
- GitHub Issues: https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues
- Documentation: `/docs/guides/`
- FFmpeg Docs: https://ffmpeg.org/ffprobe.html

---

**Last Updated:** 2025-11-22
**Version:** 70.0.0
**Status:** Production Ready
