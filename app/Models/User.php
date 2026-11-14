<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Hôtels auxquels l'utilisateur a accès (avec son rôle dans le pivot). */
    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class)->withPivot('role')->withTimestamps();
    }

    /** Services (Réception, Restaurant…) auxquels l'employé est affecté. */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /** Rôle de l'utilisateur dans un hôtel donné (null = aucun accès). */
    public function roleIn(Hotel $hotel): ?string
    {
        // Mémorisé pour la durée de la requête (le menu interroge les droits plusieurs fois).
        return $this->roleCache[$hotel->id] ??= $this->hotels()->whereKey($hotel->id)->first()?->pivot->role;
    }

    private array $roleCache = [];

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
        ];
    }
}
