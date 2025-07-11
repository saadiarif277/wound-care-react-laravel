<?php

namespace App\Models\Users\Provider;

use App\Models\User;
use App\Models\Users\Provider\ProviderCredential;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class ProviderProfile extends Model
{
    protected $table = 'provider_profiles';
    protected $primaryKey = 'provider_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider_id',
        'azure_provider_fhir_id',
        'last_profile_update',
        'profile_completion_percentage',
        'verification_status',
        'notification_preferences',
        'practice_preferences',
        'workflow_settings',
        'professional_bio',
        'specializations',
        'languages_spoken',
        'professional_photo_path',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
        'two_factor_enabled',
        'created_by',
        'updated_by',
        // Provider-specific fields
        'npi',
        'tax_id',
        'ptan',
        'specialty',
        'primary_specialty',
        'credentials',
        'dea_number',
        'state_license_number',
        'license_state',
        'medicaid_number',
        'practice_name',
        'phone',
        'fax',
    ];

    protected $casts = [
        'last_profile_update' => 'datetime',
        'profile_completion_percentage' => 'integer',
        'notification_preferences' => 'array',
        'practice_preferences' => 'array',
        'workflow_settings' => 'array',
        'specializations' => 'array',
        'languages_spoken' => 'array',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'profile_completion_percentage' => 0,
        'verification_status' => 'pending',
        'two_factor_enabled' => false,
    ];

    protected static function boot()
    {
        parent::boot();

        // No need to generate UUID since we use provider_id as primary key
    }

    /**
     * Get the provider (user) that owns this profile.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id', 'id');
    }

    /**
     * Get the user who created this profile.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the user who last updated this profile.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    /**
     * Get all credentials for this provider.
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(ProviderCredential::class, 'provider_id', 'provider_id');
    }

    /**
     * Get active credentials only.
     */
    public function activeCredentials(): HasMany
    {
        return $this->credentials()->where('is_active', true);
    }

    /**
     * Get credentials expiring soon.
     */
    public function expiringCredentials(int $days = 30): HasMany
    {
        return $this->activeCredentials()
            ->where('expiration_date', '<=', now()->addDays($days))
            ->where('expiration_date', '>', now());
    }

    /**
     * Get expired credentials.
     */
    public function expiredCredentials(): HasMany
    {
        return $this->credentials()
            ->where('expiration_date', '<', now());
    }

    /**
     * Calculate profile completion percentage.
     */
    public function calculateCompletionPercentage(): int
    {
        $fields = [
            'professional_bio' => 10,
            'specializations' => 15,
            'languages_spoken' => 5,
            'professional_photo_path' => 10,
        ];

        $preferences = [
            'notification_preferences' => 10,
            'practice_preferences' => 15,
            'workflow_settings' => 10,
        ];

        $score = 0;
        $maxScore = array_sum($fields) + array_sum($preferences);

        // Check basic fields
        foreach ($fields as $field => $points) {
            if (!empty($this->$field)) {
                if (in_array($field, ['specializations', 'languages_spoken'])) {
                    if (is_array($this->$field) && count($this->$field) > 0) {
                        $score += $points;
                    }
                } else {
                    $score += $points;
                }
            }
        }

        // Check preferences
        foreach ($preferences as $field => $points) {
            $value = $this->$field;
            if (is_array($value) && count($value) > 0) {
                $score += $points;
            }
        }

        // Add credential completion (25 points)
        $credentialScore = $this->calculateCredentialCompletionScore();
        $score += $credentialScore;
        $maxScore += 25;

        return min(100, round(($score / $maxScore) * 100));
    }

    /**
     * Calculate credential completion score.
     */
    private function calculateCredentialCompletionScore(): int
    {
        $requiredCredentials = ['medical_license', 'npi_number'];
        $optionalCredentials = ['board_certification', 'dea_registration', 'malpractice_insurance'];

        $score = 0;
        $credentials = $this->activeCredentials()
            ->where('verification_status', 'verified')
            ->pluck('credential_type')
            ->toArray();

        // Required credentials (15 points)
        foreach ($requiredCredentials as $type) {
            if (in_array($type, $credentials)) {
                $score += 7.5;
            }
        }

        // Optional credentials (10 points)
        $optionalCount = count(array_intersect($optionalCredentials, $credentials));
        $score += min(10, $optionalCount * 3.33);

        return round($score);
    }

    /**
     * Update profile completion percentage.
     */
    public function updateCompletionPercentage(): void
    {
        $this->profile_completion_percentage = $this->calculateCompletionPercentage();
        $this->last_profile_update = now();
        $this->save();
    }

    /**
     * Check if profile is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if profile needs attention.
     */
    public function needsAttention(): bool
    {
        return in_array($this->verification_status, ['documents_required', 'rejected', 'suspended']);
    }

    /**
     * Get verification status color for UI.
     */
    public function getVerificationStatusColor(): string
    {
        return match ($this->verification_status) {
            'verified' => 'green',
            'under_review', 'verification_in_progress' => 'yellow',
            'documents_required' => 'blue',
            'rejected', 'suspended' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get verification status label.
     */
    public function getVerificationStatusLabel(): string
    {
        return match ($this->verification_status) {
            'pending' => 'Pending Verification',
            'documents_required' => 'Documents Required',
            'under_review' => 'Under Review',
            'verification_in_progress' => 'Verification in Progress',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
            default => 'Unknown Status',
        };
    }

    /**
     * Get default notification preferences.
     */
    public static function getDefaultNotificationPreferences(): array
    {
        return [
            'email' => [
                'credential_expiry' => true,
                'profile_updates' => true,
                'system_notifications' => true,
                'marketing' => false,
            ],
            'sms' => [
                'urgent_alerts' => true,
                'credential_expiry' => false,
                'system_notifications' => false,
            ],
            'in_app' => [
                'all_notifications' => true,
            ],
            'frequency' => [
                'credential_reminders' => 'weekly',
                'digest' => 'daily',
            ],
        ];
    }

    /**
     * Get default practice preferences.
     */
    public static function getDefaultPracticePreferences(): array
    {
        return [
            'default_protocols' => [],
            'preferred_products' => [],
            'documentation_templates' => [],
            'clinical_decision_support' => true,
            'auto_recommendations' => true,
        ];
    }

    /**
     * Get default workflow settings.
     */
    public static function getDefaultWorkflowSettings(): array
    {
        return [
            'dashboard_layout' => 'default',
            'quick_actions' => ['new_request', 'view_patients', 'check_credentials'],
            'default_reports' => ['monthly_summary', 'credential_status'],
            'auto_save_frequency' => 300, // seconds
        ];
    }

    /**
     * Scope for verified providers.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope for providers needing attention.
     */
    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('verification_status', ['documents_required', 'rejected', 'suspended']);
    }

    /**
     * Scope for incomplete profiles.
     */
    public function scopeIncomplete($query, int $threshold = 80)
    {
        return $query->where('profile_completion_percentage', '<', $threshold);
    }

    /**
     * Get primary specialty (accessor for backward compatibility)
     */
    public function getPrimarySpecialtyAttribute()
    {
        return $this->attributes['specialty'] ?? $this->attributes['primary_specialty'] ?? null;
    }

    /**
     * Get the provider's full credentials string
     */
    public function getCredentialsAttribute($value)
    {
        // If credentials field exists, return it
        if (!empty($value)) {
            return $value;
        }
        
        // Otherwise, try to build from provider User model
        if ($this->provider && !empty($this->provider->credentials)) {
            if (is_array($this->provider->credentials)) {
                return implode(', ', $this->provider->credentials);
            }
            return $this->provider->credentials;
        }
        
        return null;
    }
}
