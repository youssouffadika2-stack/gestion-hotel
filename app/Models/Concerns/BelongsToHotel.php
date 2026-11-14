<?php

namespace App\Models\Concerns;

use App\Models\Hotel;
use App\Services\CurrentHotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isolation multi-tenant : toute requête sur un modèle utilisant ce trait est
 * automatiquement limitée à l'hôtel actif (validé côté serveur par CurrentHotel).
 *
 * Fail-closed : un utilisateur connecté SANS hôtel actif ne voit aucune donnée.
 * Seuls les contextes sans utilisateur (console, seeders, jobs) ne sont pas filtrés.
 */
trait BelongsToHotel
{
    public static function bootBelongsToHotel(): void
    {
        static::addGlobalScope('hotel', function (Builder $builder) {
            $table = $builder->getModel()->getTable();
            $hotelId = app(CurrentHotel::class)->id();

            if ($hotelId !== null) {
                $builder->where("{$table}.hotel_id", $hotelId);
            } elseif (Auth::check()) {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (! $model->hotel_id && ($hotel = app(CurrentHotel::class)->get())) {
                $model->hotel_id = $hotel->id;
            }
            if (! $model->tenant_id && $model->hotel_id) {
                $model->tenant_id = Hotel::query()->whereKey($model->hotel_id)->value('tenant_id');
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
