# Quick Test Guide - Stream Import System

**5-Minute Quick Test** 🚀

---

## ✅ Pre-Test Checklist

```bash
# 1. Check PM2 workers are running
pm2 list

# 2. Verify transcode profiles exist
mysql -u fos_dev -pfos_dev_password fos_dev -e "SELECT COUNT(*) FROM transcodes;"
# Expected: 21

# 3. Check FFprobe is installed
ffprobe -version
```

---

## 🧪 Quick Test: Import 3 Streams

### Step 1: Create Test M3U File

Create `test_quick.m3u`:
```m3u
#EXTM3U
#EXTINF:-1 group-title="Sports",Test Stream 1
http://devimages.apple.com/iphone/samples/bipbop/bipbopall.m3u8
#EXTINF:-1 group-title="News",Test Stream 2
http://devimages.apple.com/iphone/samples/bipbop/gear1/prog_index.m3u8
#EXTINF:-1 group-title="Movies",Test Stream 3
http://devimages.apple.com/iphone/samples/bipbop/gear2/prog_index.m3u8
```

### Step 2: Access Admin UI

1. Open browser: `http://localhost:7777/admin#/login`
2. Login with your credentials
3. Navigate to: **Streams** → **Manage Streams**

### Step 3: Import via UI

1. Click **"Import Streams"** button
2. Upload `test_quick.m3u8` file
3. Options:
   - ✅ Import Live Streams Only
   - ✅ Auto-match Categories
4. Click **"Start Import"**
5. Wait for confirmation

### Step 4: Monitor Progress

**Terminal 1 - Watch Workers**:
```bash
pm2 logs --lines 50
```

**Terminal 2 - Check Database**:
```bash
# Watch stream count
watch -n 2 "mysql -u fos_dev -pfos_dev_password fos_dev -e 'SELECT COUNT(*) FROM streams;'"
```

### Step 5: Verify Results

```bash
# Check streams were created
mysql -u fos_dev -pfos_dev_password fos_dev -e "
SELECT id, name, status, analysis_status
FROM streams
ORDER BY id DESC
LIMIT 5;
"

# Expected output:
# - 3 streams created
# - status = 0 (stopped)
# - analysis_status = 'pending' or 'completed'

# Check categories
mysql -u fos_dev -pfos_dev_password fos_dev -e "SELECT * FROM categories;"

# Expected: Sports, News, Movies
```

---

## 🎯 Expected Timeline

- **0-5s**: Job created, returned to UI
- **5-10s**: stream-import-worker processes job, creates streams
- **10-40s**: ffprobe-worker analyzes streams (3 streams × 10-15s each)
- **Total**: ~40-60 seconds for complete import + analysis

---

## ✅ Success Indicators

### In UI:
- ✅ "Import successful" message
- ✅ Streams appear in list
- ✅ Status shows "STOPPED" (red badge)
- ✅ Categories assigned correctly

### In Database:
```bash
mysql -u fos_dev -pfos_dev_password fos_dev -e "
SELECT
    (SELECT COUNT(*) FROM streams) as total_streams,
    (SELECT COUNT(*) FROM categories) as total_categories,
    (SELECT COUNT(*) FROM streams WHERE status=0) as stopped_streams,
    (SELECT COUNT(*) FROM streams WHERE analysis_status='completed') as analyzed_streams;
"
```

**Expected**:
- total_streams: 3
- total_categories: 3
- stopped_streams: 3
- analyzed_streams: 3 (after ~1 minute)

### In PM2 Logs:
```
[INFO] Processing import job job_xxx
[INFO] Created stream: Test Stream 1 (ID: 1)
[INFO] Created category: Sports (ID: 1)
[INFO] Queued 3 streams for FFprobe analysis
[INFO] Import job completed: 3 imported, 0 failed

[INFO] Analyzing stream 1: Test Stream 1
[INFO] ✓ Success (8.2s) - HD | H264 | AAC | Health: 95/100
```

---

## 🐛 If Something Goes Wrong

### Workers Not Processing
```bash
pm2 restart all
pm2 logs --err --lines 50
```

### No Streams Created
```bash
# Check job queue
ls -la storage/jobs/stream-import/

# Check worker logs
pm2 logs stream-import-worker --lines 50
```

### Analysis Stuck at 'pending'
```bash
# Check ffprobe queue
ls -la storage/jobs/ffprobe-analysis/

# Restart ffprobe worker
pm2 restart ffprobe-worker

# Check FFprobe is working
ffprobe -version
```

---

## 📊 View Results in UI

1. **Streams List**: `http://localhost:7777/admin#/streams`
   - See all imported streams
   - Status badges (STOPPED/RUNNING/ERROR)
   - Analysis status

2. **Stream Details**: Click on any stream
   - View technical information
   - Video: codec, resolution, FPS, bitrate
   - Audio: codec, channels, sample rate
   - Health score
   - FFprobe profile recommendation

3. **Categories**: Check sidebar or categories page
   - Should see: Sports, News, Movies
   - Each with assigned stream count

---

## 🎉 Next Steps After Success

1. **Test Larger Import**
   - Try 10-20 streams
   - Monitor performance

2. **Test VOD Exclusion**
   - Add VOD entries to M3U
   - Verify they're skipped

3. **Test Category Matching**
   - Import stream with existing category
   - Verify no duplicates

4. **Test Error Handling**
   - Add invalid stream URL
   - Check error logging

---

## 📞 Need Help?

- **Docs**: [docs/guides/STREAM_IMPORT_TESTING_PLAN.md](docs/guides/STREAM_IMPORT_TESTING_PLAN.md)
- **Summary**: [STREAM_IMPORT_TEST_SUMMARY.md](STREAM_IMPORT_TEST_SUMMARY.md)
- **PM2**: `pm2 logs --help`
- **Database**: `mysql --help`

---

**Total Time**: 5 minutes
**Difficulty**: Easy
**Status**: Ready to test! 🚀
