<?php
/**
 * Subscriber Model
 *
 * Represents a subscriber with subscriptions, trials, and activity tracking
 * Subscribers are customers who can have multiple subscriptions
 */

class Subscriber extends FosStreaming {

    protected $table = 'subscribers';

    protected $fillable = [
        'username',
        'password',
        'email',
        'phone',
        'country',
        'city',
        'address',
        'postal_code',
        'notes',
        'enabled'
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected $hidden = [
        'password'
    ];

    /**
     * Get all subscriptions for this subscriber
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'subscriber_id');
    }

    /**
     * Get trial for this subscriber (one trial per subscriber)
     */
    public function trial()
    {
        return $this->hasOne(Trial::class, 'subscriber_id');
    }

    /**
     * Get all activities for this subscriber
     */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    /**
     * Alias for activities (for backward compatibility)
     */
    public function activity()
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    /**
     * Get reseller that manages this subscriber
     */
    public function reseller()
    {
        return $this->belongsToMany(
            Reseller::class,
            'reseller_subscribers',
            'subscriber_id',
            'reseller_id'
        )->withTimestamps();
    }

    /**
     * Get categories assigned to this subscriber
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get last stream
     */
    public function laststream()
    {
        return $this->hasOne(Stream::class, 'id', 'last_stream');
    }

    /**
     * Get category names as comma-separated string
     */
    public function getCategoryNamesAttribute()
    {
        $return = "";
        $prefix = '';
        foreach($this->categories as $category)
        {
            $return .= $prefix . ' ' . $category->name . '';
            $prefix = ', ';
        }

        return $return;
    }

    /**
     * Check if subscriber has an active trial
     */
    public function hasActiveTrial()
    {
        return $this->trial && $this->trial->isValid();
    }

    /**
     * Check if subscriber has active subscriptions
     */
    public function hasActiveSubscriptions()
    {
        return $this->subscriptions()
            ->where('is_active', 1)
            ->where('expire_date', '>', now())
            ->exists();
    }

    /**
     * Get all active subscriptions
     */
    public function getActiveSubscriptionsAttribute()
    {
        return $this->subscriptions()
            ->where('is_active', 1)
            ->where('expire_date', '>', now())
            ->get();
    }

    /**
     * Get total number of active subscriptions
     */
    public function getActiveSubscriptionsCountAttribute()
    {
        return $this->active_subscriptions->count();
    }

    /**
     * Check if subscriber has any valid access (trial or subscription)
     */
    public function hasValidAccess()
    {
        return $this->hasActiveTrial() || $this->hasActiveSubscriptions();
    }

    /**
     * Get all channels subscriber has access to
     * (from active subscriptions and trial)
     */
    public function getAccessibleChannelsAttribute()
    {
        $channels = collect();

        // Add channels from active subscriptions
        foreach ($this->active_subscriptions as $subscription) {
            $channels = $channels->merge($subscription->channels);
        }

        // Add channels from active trial
        if ($this->hasActiveTrial()) {
            $channels = $channels->merge($this->trial->channels);
        }

        return $channels->unique('id');
    }

    /**
     * Get all bouquets subscriber has access to
     */
    public function getAccessibleBouquetsAttribute()
    {
        $bouquets = collect();

        // Add bouquets from active subscriptions
        foreach ($this->active_subscriptions as $subscription) {
            $bouquets = $bouquets->merge($subscription->bouquets);
        }

        // Add bouquets from active trial
        if ($this->hasActiveTrial()) {
            $bouquets = $bouquets->merge($this->trial->bouquets);
        }

        return $bouquets->unique('id');
    }

    /**
     * Check if subscriber can access a specific channel
     */
    public function canAccessChannel($channelId)
    {
        return $this->accessible_channels->contains('id', $channelId);
    }

    /**
     * Check if subscriber can access a specific bouquet
     */
    public function canAccessBouquet($bouquetId)
    {
        return $this->accessible_bouquets->contains('id', $bouquetId);
    }

    /**
     * Get full name (username for now, can be enhanced with first_name, last_name)
     */
    public function getFullNameAttribute()
    {
        return $this->username;
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->postal_code,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope: Only enabled subscribers
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', 1);
    }

    /**
     * Scope: Subscribers with active subscriptions
     */
    public function scopeWithActiveSubscriptions($query)
    {
        return $query->whereHas('subscriptions', function($q) {
            $q->where('is_active', 1)
              ->where('expire_date', '>', now());
        });
    }

    /**
     * Scope: Subscribers with active trials
     */
    public function scopeWithActiveTrials($query)
    {
        return $query->whereHas('trial', function($q) {
            $q->where('is_active', 1)
              ->where('expires_at', '>', now());
        });
    }

    /**
     * Scope: Search by username, email, phone
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }
}
