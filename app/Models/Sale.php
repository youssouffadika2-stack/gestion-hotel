<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'tenant_id', 'hotel_id', 'department_id', 'service_id', 'label', 'quantity', 'unit_price',
        'amount', 'method', 'sold_at', 'business_date', 'created_by',
    ];

    protected function casts(): array
    {
        return ['sold_at' => 'datetime', 'business_date' => 'date', 'amount' => 'integer', 'unit_price' => 'integer', 'quantity' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Toujours stocker « Y-m-d » (comparaisons d'égalité fiables sur tous les moteurs). */
    public function setBusinessDateAttribute($value): void
    {
        $this->attributes['business_date'] = \Carbon\Carbon::parse($value)->toDateString();
    }
}
