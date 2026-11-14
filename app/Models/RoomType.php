<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    use BelongsToHotel;

    protected $fillable = ['tenant_id', 'hotel_id', 'name', 'base_price'];

    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class);
    }
}
