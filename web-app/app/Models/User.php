<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'position_title',
        'profile_photo_path',
        'organization_id',
        'role',
        'specialties',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
            'specialties' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(DemandTask::class, 'assigned_to');
    }

    public function createdDemands(): HasMany
    {
        return $this->hasMany(Demand::class, 'created_by');
    }

    public function clientDemands(): HasMany
    {
        return $this->hasMany(Demand::class, 'client_user_id');
    }

    public function activeTimeEntry(): HasOne
    {
        return $this->hasOne(TaskTimeEntry::class)->whereNull('ended_at');
    }

    public function matchesSpecialty(string $responsibilityProfile): bool
    {
        $profile = Str::lower(Str::ascii(trim($responsibilityProfile)));

        if ($profile === '') {
            return false;
        }

        return collect($this->specialties ?? [])
            ->contains(fn (string $specialty): bool => Str::lower(Str::ascii(trim($specialty))) === $profile);
    }
}
