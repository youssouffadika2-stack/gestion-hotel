<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use BelongsToHotel;

    protected $fillable = ['tenant_id', 'hotel_id', 'name', 'unit', 'quantity', 'alert_threshold'];

    public function isLow(): bool
    {
        return $this->quantity <= $this->alert_threshold;
    }
}
