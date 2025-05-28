<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProviderInvitation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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
        'assigned_facilities' => 'json',
        'assigned_roles' => 'json',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function invitedByUser()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }
}
