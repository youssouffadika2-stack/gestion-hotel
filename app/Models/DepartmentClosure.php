<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentClosure extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'tenant_id', 'hotel_id', 'department_id', 'business_date', 'total', 'cash_expected',
        'cash_counted', 'operations', 'note', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Toujours stocker « Y-m-d » (comparaisons d'égalité fiables sur tous les moteurs). */
    public function setBusinessDateAttribute($value): void
    {
        $this->attributes['business_date'] = \Carbon\Carbon::parse($value)->toDateString();
    }
}
