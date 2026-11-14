<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use BelongsToHotel;

    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CHECKED_IN = 'checked_in';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'En attente',
        self::CONFIRMED => 'Confirmée',
        self::CHECKED_IN => 'En séjour',
        self::COMPLETED => 'Terminée',
        self::CANCELLED => 'Annulée',
    ];

    protected $fillable = [
        'tenant_id', 'hotel_id', 'code', 'guest_id', 'room_id',
        'check_in', 'check_out', 'total_amount', 'status', 'created_by',
        'source', 'guest_message', 'hotel_message', 'guests_count', 'read_at',
    ];

    public const SOURCE_STAFF = 'staff';
    public const SOURCE_ONLINE = 'online';

    public function isOnline(): bool
    {
        return $this->source === self::SOURCE_ONLINE;
    }

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_amount' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
