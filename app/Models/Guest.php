<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use BelongsToHotel;

    protected $fillable = ['tenant_id', 'hotel_id', 'name', 'phone', 'email'];

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
