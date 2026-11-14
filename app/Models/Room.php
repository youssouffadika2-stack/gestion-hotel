<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use BelongsToHotel;

    public const AVAILABLE = 'available';
    public const OCCUPIED = 'occupied';
    public const MAINTENANCE = 'maintenance';
    public const OUT_OF_SERVICE = 'out_of_service';

    protected $fillable = ['tenant_id', 'hotel_id', 'room_type_id', 'number', 'status'];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Chambres libres sur [$in, $out[ : ni en maintenance, ni déjà réservées.
     * $ignoreReservationId permet de modifier une réservation sans qu'elle se bloque elle-même.
     */
    public function scopeAvailableBetween(Builder $query, string $in, string $out, ?int $ignoreReservationId = null): Builder
    {
        return $query
            ->whereNotIn('status', [self::MAINTENANCE, self::OUT_OF_SERVICE])
            ->whereDoesntHave('reservations', function ($q) use ($in, $out, $ignoreReservationId) {
                $q->withoutGlobalScopes()->whereIn('status', [Reservation::PENDING, Reservation::CONFIRMED, Reservation::CHECKED_IN])
                    ->where('check_in', '<', $out)
                    ->where('check_out', '>', $in)
                    ->when($ignoreReservationId, fn ($q) => $q->whereKeyNot($ignoreReservationId));
            });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }
}
