<?php

namespace App\Models\Users\Provider;

use App\Models\User;
use App\Models\Users\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'assigned_facilities',
        'assigned_roles',
        'status',
        'sent_at',
        'opened_at',
        'accepted_at',
        'expires_at',
        'created_user_id',
    ];

    protected $casts = [
        'assigned_facilities' => 'array',
        'assigned_roles' => 'array',
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

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function invitedByUser()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    /**
     * Check if the invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the invitation is active (sent but not expired)
     */
    public function isActive(): bool
    {
        return $this->status === 'sent' && !$this->isExpired();
    }

    /**
     * Generate a secure invitation URL
     */
    public function getInvitationUrl(): string
    {
        return route('auth.provider-invitation.show', ['token' => $this->invitation_token]);
    }
}
