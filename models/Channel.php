<?php
/**
 * Channel Model
 *
 * Represents a TV channel linked to a stream
 */

class Channel extends FosStreaming {

    protected $table = 'channels';

    protected $fillable = [
        'stream_id',
        'name',
        'description',
        'category_id',
        'logo_url',
        'epg_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stream_id' => 'integer',
        'category_id' => 'integer',
    ];

    /**
     * Get the stream associated with this channel
     */
    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * Get the category of this channel
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all bouquets that include this channel
     * Many-to-Many relationship through bouquet_channel pivot table
     */
    public function bouquets()
    {
        return $this->belongsToMany(Bouquet::class, 'bouquet_channel')
                    ->withPivot('sort_order');
    }

    /**
     * Get bouquet count
     */
    public function getBouquetCountAttribute()
    {
        return $this->bouquets()->count();
    }

    /**
     * Get packages that include this channel (through bouquets)
     */
    public function getPackagesAttribute()
    {
        $packages = collect();

        foreach ($this->bouquets as $bouquet) {
            $packages = $packages->merge($bouquet->packages);
        }

        return $packages->unique('id');
    }

    /**
     * Check if channel is running (via stream status)
     */
    public function getIsRunningAttribute()
    {
        return $this->stream && $this->stream->running == 1;
    }

    /**
     * Get stream URL
     */
    public function getStreamUrlAttribute()
    {
        return $this->stream ? $this->stream->stream_url : null;
    }

    /**
     * Get category name
     */
    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->name : 'Uncategorized';
    }

    /**
     * Scope: Only active channels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Channels with active streams
     */
    public function scopeWithActiveStreams($query)
    {
        return $query->whereHas('stream', function($q) {
            $q->where('running', 1);
        });
    }

    /**
     * Scope: Channels by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: Channels in specific bouquet
     */
    public function scopeInBouquet($query, $bouquetId)
    {
        return $query->whereHas('bouquets', function($q) use ($bouquetId) {
            $q->where('bouquets.id', $bouquetId);
        });
    }

    /**
     * Static: Create channel from stream
     */
    public static function createFromStream($streamId, $categoryId = null)
    {
        $stream = Stream::find($streamId);

        if (!$stream) {
            return null;
        }

        $channel = new static();
        $channel->stream_id = $stream->id;
        $channel->name = $stream->stream_display_name ?: "Channel {$stream->id}";
        $channel->description = $stream->notes ?? null;
        $channel->category_id = $categoryId ?? $stream->category_id ?? null;
        $channel->is_active = $stream->running == 1;
        $channel->save();

        return $channel;
    }

    /**
     * Static: Sync all channels from streams
     * Creates channels for streams that don't have channels yet
     */
    public static function syncFromStreams()
    {
        $streams = Stream::whereNotIn('id', function($query) {
            $query->select('stream_id')->from('channels');
        })->get();

        $created = 0;
        foreach ($streams as $stream) {
            $channel = static::createFromStream($stream->id);
            if ($channel) {
                $created++;
            }
        }

        return $created;
    }
}
