<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToHotel;

    protected $fillable = ['tenant_id', 'hotel_id', 'label', 'category', 'amount', 'spent_at', 'created_by'];

    protected function casts(): array
    {
        return ['spent_at' => 'date', 'amount' => 'integer'];
    }
}
