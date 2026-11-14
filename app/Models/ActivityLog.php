<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use BelongsToHotel;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'hotel_id', 'user_id', 'type', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
