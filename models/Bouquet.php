<?php
/**
 * Bouquet Model
 *
 * Represents a bouquet (group) of TV channels
 */

class Bouquet extends FosStreaming {

    protected $table = 'bouquets';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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
     * Get all channels in this bouquet
     * Many-to-Many relationship through bouquet_channel pivot table
     */
    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'bouquet_channel')
                    ->withPivot('sort_order')
                    ->orderBy('bouquet_channel.sort_order');
    }

    /**
     * Get channel count
     */
    public function getChannelCountAttribute()
    {
        return $this->channels()->count();
    }

    /**
     * Get package count
     */
    public function getPackageCountAttribute()
    {
        return $this->packages()->count();
    }

    /**
     * Check if bouquet has a specific channel
     */
    public function hasChannel($channelId)
    {
        return $this->channels()->where('channels.id', $channelId)->exists();
    }

    /**
     * Assign channel to bouquet
     */
    public function assignChannel($channelId, $sortOrder = 0)
    {
        if (!$this->hasChannel($channelId)) {
            $this->channels()->attach($channelId, ['sort_order' => $sortOrder]);
            return true;
        }
        return false;
    }

    /**
     * Remove channel from bouquet
     */
    public function removeChannel($channelId)
    {
        $this->channels()->detach($channelId);
    }

    /**
     * Update channel sort order in bouquet
     */
    public function updateChannelOrder($channelId, $sortOrder)
    {
        $this->channels()->updateExistingPivot($channelId, ['sort_order' => $sortOrder]);
    }

    /**
     * Sync channels (replace all with new set)
     * $channelsWithOrder = [channelId => ['sort_order' => order], ...]
     */
    public function syncChannels($channelsWithOrder)
    {
        $this->channels()->sync($channelsWithOrder);
    }

    /**
     * Reorder all channels in bouquet
     * $channelIds = [channelId1, channelId2, ...] in desired order
     */
    public function reorderChannels($channelIds)
    {
        $order = 0;
        foreach ($channelIds as $channelId) {
            $this->channels()->updateExistingPivot($channelId, ['sort_order' => $order]);
            $order++;
        }
    }

    /**
     * Get all streams from channels in this bouquet
     */
    public function getStreamsAttribute()
    {
        return $this->channels->map(function($channel) {
            return $channel->stream;
        })->filter();
    }

    /**
     * Scope: Only active bouquets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Bouquets with at least one channel
     */
    public function scopeWithChannels($query)
    {
        return $query->has('channels');
    }

    /**
     * Scope: Order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
