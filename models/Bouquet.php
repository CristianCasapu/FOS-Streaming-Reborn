<?php
/**
 * Bouquet Model
 *
 * Represents a bouquet (group) of streams
 * Directly references streams via stream_ids JSON column
 */

class Bouquet extends FosStreaming {

    protected $table = 'bouquets';

    protected $fillable = [
        'name',
        'description',
        'stream_ids',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'stream_ids' => 'array', // Automatically cast JSON to array
    ];

    /**
     * Get all packages that include this bouquet
     * Many-to-Many relationship through package_bouquet pivot table
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_bouquet');
    }

    /**
     * Get all streams in this bouquet
     * Returns a collection of Stream models based on stream_ids JSON array
     */
    public function streams()
    {
        if (empty($this->stream_ids)) {
            return collect();
        }

        return Stream::whereIn('id', $this->stream_ids)
            ->orderByRaw('FIELD(id, ' . implode(',', $this->stream_ids) . ')')
            ->get();
    }

    /**
     * Get stream count
     */
    public function getStreamCountAttribute()
    {
        return is_array($this->stream_ids) ? count($this->stream_ids) : 0;
    }

    /**
     * Get package count
     */
    public function getPackageCountAttribute()
    {
        return $this->packages()->count();
    }

    /**
     * Check if bouquet has a specific stream
     */
    public function hasStream($streamId)
    {
        return is_array($this->stream_ids) && in_array($streamId, $this->stream_ids);
    }

    /**
     * Add stream to bouquet
     */
    public function addStream($streamId)
    {
        if (!$this->hasStream($streamId)) {
            $streamIds = $this->stream_ids ?? [];
            $streamIds[] = $streamId;
            $this->stream_ids = $streamIds;
            return $this->save();
        }
        return false;
    }

    /**
     * Remove stream from bouquet
     */
    public function removeStream($streamId)
    {
        if ($this->hasStream($streamId)) {
            $streamIds = $this->stream_ids ?? [];
            $streamIds = array_values(array_filter($streamIds, function($id) use ($streamId) {
                return $id != $streamId;
            }));
            $this->stream_ids = $streamIds;
            return $this->save();
        }
        return false;
    }

    /**
     * Set streams (replace all with new set)
     * $streamIds = [streamId1, streamId2, ...]
     */
    public function setStreams(array $streamIds)
    {
        $this->stream_ids = array_values(array_unique($streamIds));
        return $this->save();
    }

    /**
     * Reorder all streams in bouquet
     * $streamIds = [streamId1, streamId2, ...] in desired order
     */
    public function reorderStreams(array $streamIds)
    {
        // Only keep stream IDs that are already in the bouquet
        $existingIds = $this->stream_ids ?? [];
        $reordered = array_values(array_intersect($streamIds, $existingIds));

        $this->stream_ids = $reordered;
        return $this->save();
    }

    /**
     * Add multiple streams at once
     * $streamIds = [streamId1, streamId2, ...]
     */
    public function addStreams(array $streamIds)
    {
        $currentIds = $this->stream_ids ?? [];
        $newIds = array_unique(array_merge($currentIds, $streamIds));
        $this->stream_ids = array_values($newIds);
        return $this->save();
    }

    /**
     * Remove multiple streams at once
     * $streamIds = [streamId1, streamId2, ...]
     */
    public function removeStreams(array $streamIds)
    {
        $currentIds = $this->stream_ids ?? [];
        $remaining = array_values(array_diff($currentIds, $streamIds));
        $this->stream_ids = $remaining;
        return $this->save();
    }

    /**
     * Get active streams only
     */
    public function getActiveStreamsAttribute()
    {
        if (empty($this->stream_ids)) {
            return collect();
        }

        return Stream::whereIn('id', $this->stream_ids)
            ->where('running', 1)
            ->orderByRaw('FIELD(id, ' . implode(',', $this->stream_ids) . ')')
            ->get();
    }

    /**
     * Get running stream count
     */
    public function getRunningStreamCountAttribute()
    {
        if (empty($this->stream_ids)) {
            return 0;
        }

        return Stream::whereIn('id', $this->stream_ids)
            ->where('running', 1)
            ->count();
    }

    /**
     * Scope: Only active bouquets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Bouquets with at least one stream
     */
    public function scopeWithStreams($query)
    {
        return $query->whereNotNull('stream_ids')
            ->where('stream_ids', '!=', '[]')
            ->where('stream_ids', '!=', 'null');
    }

    /**
     * Scope: Order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get detailed information about streams in this bouquet
     */
    public function getStreamsWithDetailsAttribute()
    {
        if (empty($this->stream_ids)) {
            return collect();
        }

        $streams = $this->streams();

        return $streams->map(function($stream, $index) {
            return [
                'id' => $stream->id,
                'name' => $stream->stream_display_name ?? $stream->name ?? "Stream {$stream->id}",
                'running' => $stream->running == 1,
                'category_id' => $stream->cat_id,
                'category_name' => $stream->category ? $stream->category->name : null,
                'sort_order' => $index,
                'streamurl' => $stream->streamurl,
            ];
        });
    }

    /**
     * Validate stream IDs before saving
     */
    public function validateStreamIds()
    {
        if (empty($this->stream_ids)) {
            return true;
        }

        // Check if all stream IDs exist
        $existingCount = Stream::whereIn('id', $this->stream_ids)->count();
        return $existingCount === count($this->stream_ids);
    }

    /**
     * Clean up invalid stream IDs
     * Removes stream IDs that don't exist in the streams table
     */
    public function cleanupInvalidStreamIds()
    {
        if (empty($this->stream_ids)) {
            return true;
        }

        $validIds = Stream::whereIn('id', $this->stream_ids)->pluck('id')->toArray();
        $this->stream_ids = array_values($validIds);
        return $this->save();
    }
}
