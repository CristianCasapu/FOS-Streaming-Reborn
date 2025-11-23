<?php
/**
 * Package Model
 *
 * Represents a subscription package with connection limits and bouquets
 */

class Package extends FosStreaming {

    protected $table = 'packages';

    protected $fillable = [
        'name',
        'description',
        'max_connections',
        'price',
        'duration_days',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'max_connections' => 'integer',
        'duration_days' => 'integer',
    ];

    /**
     * Get all subscriptions for this package
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all trials for this package
     */
    public function trials()
    {
        return $this->hasMany(Trial::class);
    }

    /**
     * Get all bouquets assigned to this package
     * Many-to-Many relationship through package_bouquet pivot table
     */
    public function bouquets()
    {
        return $this->belongsToMany(Bouquet::class, 'package_bouquet');
    }

    /**
     * Get all channels available in this package
     * This aggregates all channels from all bouquets in the package
     */
    public function getChannelsAttribute()
    {
        $channels = collect();

        foreach ($this->bouquets as $bouquet) {
            $channels = $channels->merge($bouquet->channels);
        }

        return $channels->unique('id')->sortBy('name');
    }

    /**
     * Get total number of channels in this package
     */
    public function getChannelCountAttribute()
    {
        return $this->channels->count();
    }

    /**
     * Get total number of bouquets in this package
     */
    public function getBouquetCountAttribute()
    {
        return $this->bouquets()->count();
    }

    /**
     * Get active subscriptions count
     */
    public function getActiveSubscriptionsCountAttribute()
    {
        return $this->subscriptions()
            ->where('is_active', 1)
            ->where('expire_date', '>', now())
            ->count();
    }

    /**
     * Get active trials count
     */
    public function getActiveTrialsCountAttribute()
    {
        return $this->trials()
            ->where('is_active', 1)
            ->where('expires_at', '>', now())
            ->count();
    }

    /**
     * Check if package has a specific bouquet
     */
    public function hasBouquet($bouquetId)
    {
        return $this->bouquets()->where('bouquets.id', $bouquetId)->exists();
    }

    /**
     * Assign bouquet to package
     */
    public function assignBouquet($bouquetId)
    {
        if (!$this->hasBouquet($bouquetId)) {
            $this->bouquets()->attach($bouquetId);
            return true;
        }
        return false;
    }

    /**
     * Remove bouquet from package
     */
    public function removeBouquet($bouquetId)
    {
        $this->bouquets()->detach($bouquetId);
    }

    /**
     * Sync bouquets (replace all with new set)
     */
    public function syncBouquets($bouquetIds)
    {
        $this->bouquets()->sync($bouquetIds);
    }

    /**
     * Scope: Only active packages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Packages with at least one bouquet
     */
    public function scopeWithBouquets($query)
    {
        return $query->has('bouquets');
    }
}
