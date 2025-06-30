<?php

namespace App\Models\Users\Provider;

<<<<<<< HEAD
use App\Models\User;
use App\Models\Users\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
=======
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Users\Organization\Organization;
>>>>>>> origin/provider-side
use Illuminate\Support\Str;

class ProviderInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'invitation_token',
        'organization_id',
        'invited_by_user_id',
<<<<<<< HEAD
=======
        'created_user_id',
        'invitation_type',
        'organization_name',
        'metadata',
>>>>>>> origin/provider-side
        'assigned_facilities',
        'assigned_roles',
        'status',
        'sent_at',
        'opened_at',
        'accepted_at',
        'expires_at',
<<<<<<< HEAD
        'created_user_id',
=======
>>>>>>> origin/provider-side
    ];

    protected $casts = [
        'assigned_facilities' => 'array',
        'assigned_roles' => 'array',
<<<<<<< HEAD
=======
        'metadata' => 'array',
>>>>>>> origin/provider-side
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->invitation_token)) {
                $model->invitation_token = bin2hex(random_bytes(32));
            }
        });
    }

<<<<<<< HEAD
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function invitedBy()
=======
    /**
     * Get the organization that owns the invitation.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function invitedBy(): BelongsTo
>>>>>>> origin/provider-side
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

<<<<<<< HEAD
    public function invitedByUser()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function createdUser()
=======
    /**
     * Get the user created from this invitation.
     */
    public function createdUser(): BelongsTo
>>>>>>> origin/provider-side
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    /**
<<<<<<< HEAD
=======
     * Check if the invitation is for creating a new organization
     */
    public function isOrganizationInvitation(): bool
    {
        return $this->invitation_type === 'organization';
    }

    /**
     * Check if the invitation is for adding a provider to existing organization
     */
    public function isProviderInvitation(): bool
    {
        return $this->invitation_type === 'provider';
    }

    /**
>>>>>>> origin/provider-side
     * Check if the invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
<<<<<<< HEAD
     * Check if the invitation is active (sent but not expired)
     */
    public function isActive(): bool
    {
        return $this->status === 'sent' && !$this->isExpired();
=======
     * Check if the invitation is still valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && in_array($this->status, ['pending', 'sent', 'opened']);
    }

    /**
     * Mark the invitation as opened
     */
    public function markAsOpened(): void
    {
        if ($this->status === 'pending' || $this->status === 'sent') {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Mark the invitation as accepted
     */
    public function markAsAccepted(int $userId = null): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'created_user_id' => $userId,
        ]);
    }

    /**
     * Scope to get only valid invitations
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
                     ->whereIn('status', ['pending', 'sent', 'opened']);
    }

    /**
     * Scope to get only organization invitations
     */
    public function scopeOrganizationInvitations($query)
    {
        return $query->where('invitation_type', 'organization');
    }

    /**
     * Scope to get only provider invitations
     */
    public function scopeProviderInvitations($query)
    {
        return $query->where('invitation_type', 'provider');
>>>>>>> origin/provider-side
    }

    /**
     * Generate a secure invitation URL
     */
    public function getInvitationUrl(): string
    {
        return route('auth.provider-invitation.show', ['token' => $this->invitation_token]);
    }
}
