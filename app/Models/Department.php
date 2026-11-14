<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToHotel;

    public const TYPES = [
        'reception' => 'Réception / hébergement',
        'restaurant' => 'Restaurant',
        'bar' => 'Bar',
        'laundry' => 'Blanchisserie',
        'spa' => 'Spa / bien-être',
        'other' => 'Autre service',
    ];

    protected $fillable = ['tenant_id', 'hotel_id', 'name', 'type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function isReception(): bool
    {
        return $this->type === 'reception';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
