<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use SoftDeletes;

    protected $table = 'resellers';

    protected $fillable = [
        'username',
        'email',
        'password',
        'company_name',
        'contact_name',
        'phone',
        'address',
        'commission_rate',
        'credit_balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
        'max_subscribers',
        'max_packages',
        'can_create_packages',
        'can_set_prices',
        'custom_domain',
        'logo_url',
        'favicon_url',
        'brand_name',
        'theme_settings',
        'api_key',
        'api_secret',
        'api_enabled',
        'api_rate_limit',
        'is_active',
        'is_verified',
        'verified_at',
        'last_login',
        'status',
        'notes',
    ];

    protected $hidden = [
        'password',
        'api_secret',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'max_subscribers' => 'integer',
        'max_packages' => 'integer',
        'can_create_packages' => 'boolean',
        'can_set_prices' => 'boolean',
        'theme_settings' => 'array',
        'api_enabled' => 'boolean',
        'api_rate_limit' => 'integer',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get subscribers managed by this reseller
     */
    public function subscribers()
    {
        return $this->belongsToMany(
            Subscriber::class,
            'reseller_subscribers',
            'reseller_id',
            'subscriber_id'
        )->withTimestamps();
    }

    /**
     * Get transactions for this reseller
     */
    public function transactions()
    {
        return $this->hasMany(ResellerTransaction::class, 'reseller_id');
    }

    /**
     * Get active subscribers
     */
    public function activeSubscribers()
    {
        return $this->subscribers()->where('enabled', 1);
    }

    /**
     * Scope for active resellers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Scope for verified resellers
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Check if reseller can add more subscribers
     */
    public function canAddSubscriber()
    {
        return $this->subscribers()->count() < $this->max_subscribers;
    }

    /**
     * Check if reseller can create packages
     */
    public function canCreatePackage()
    {
        if (!$this->can_create_packages) {
            return false;
        }

        // Count custom packages created by this reseller
        $packageCount = Package::where('reseller_id', $this->id)->count();
        return $packageCount < $this->max_packages;
    }

    /**
     * Add commission
     */
    public function addCommission($amount, $subscriptionId, $description = null)
    {
        $balanceBefore = $this->credit_balance;
        $balanceAfter = $balanceBefore + $amount;

        $transaction = $this->transactions()->create([
            'type' => 'commission',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'subscription_id' => $subscriptionId,
            'description' => $description ?? "Commission earned",
            'status' => 'completed',
        ]);

        $this->update([
            'credit_balance' => $balanceAfter,
            'total_earned' => $this->total_earned + $amount,
        ]);

        return $transaction;
    }

    /**
     * Process withdrawal
     */
    public function withdraw($amount, $reference, $description = null)
    {
        if ($amount > $this->credit_balance) {
            throw new \Exception('Insufficient balance');
        }

        $balanceBefore = $this->credit_balance;
        $balanceAfter = $balanceBefore - $amount;

        $transaction = $this->transactions()->create([
            'type' => 'withdrawal',
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'description' => $description ?? "Withdrawal",
            'status' => 'pending',
        ]);

        $this->update([
            'credit_balance' => $balanceAfter,
            'total_withdrawn' => $this->total_withdrawn + $amount,
        ]);

        return $transaction;
    }

    /**
     * Generate unique API key
     */
    public function generateApiKey()
    {
        $this->api_key = bin2hex(random_bytes(32));
        $this->api_secret = bin2hex(random_bytes(32));
        $this->save();

        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
        ];
    }

    /**
     * Get available balance
     */
    public function getAvailableBalanceAttribute()
    {
        // Subtract pending withdrawals
        $pendingWithdrawals = $this->transactions()
            ->where('type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');

        return $this->credit_balance + $pendingWithdrawals; // amount is negative
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCountAttribute()
    {
        return $this->subscribers()->count();
    }

    /**
     * Get active subscriber count
     */
    public function getActiveSubscriberCountAttribute()
    {
        return $this->activeSubscribers()->count();
    }

    /**
     * Get monthly earnings
     */
    public function getMonthlyEarningsAttribute()
    {
        return $this->transactions()
            ->where('type', 'commission')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
    }

    /**
     * Hash password before saving
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_ARGON2ID);
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * Update last login
     */
    public function updateLastLogin()
    {
        $this->update(['last_login' => now()]);
    }
}

/**
 * Reseller Transaction Model
 */
class ResellerTransaction extends Model
{
    protected $table = 'reseller_transactions';

    protected $fillable = [
        'reseller_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'subscription_id',
        'reference',
        'description',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reseller
     */
    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    /**
     * Get the related subscription
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Mark as completed
     */
    public function markCompleted()
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark as failed
     */
    public function markFailed()
    {
        $this->update(['status' => 'failed']);

        // Refund if withdrawal
        if ($this->type === 'withdrawal') {
            $this->reseller->update([
                'credit_balance' => $this->balance_before,
                'total_withdrawn' => $this->reseller->total_withdrawn + $this->amount,
            ]);
        }
    }
}
