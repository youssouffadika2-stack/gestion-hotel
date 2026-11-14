<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Appel interne de l'administration générale (admin_hotel) : « cet hôtel vient d'être activé ».
 * Protégé par un secret partagé (en-tête X-Bridge-Secret), jamais par la session.
 */
class BridgeController extends Controller
{
    public function access(Request $request): JsonResponse
    {
        $secret = (string) config('hotelpro.bridge_secret');
        abort_if($secret === '', 503, 'Secret non configuré.');
        abort_unless(hash_equals($secret, (string) $request->header('X-Bridge-Secret')), 403);

        $data = $request->validate([
            'vitrine_id' => 'required|integer',
            'event' => 'required|in:activated,password_reset',
            'password' => 'nullable|string|min:8|max:100',
        ]);

        $hotel = Hotel::where('vitrine_id', $data['vitrine_id'])->first();
        if (! $hotel) {
            return response()->json(['sent' => false, 'message' => "Hôtel introuvable dans l'espace de gestion."], 404);
        }
        if (! $hotel->isActive()) {
            return response()->json(['sent' => false, 'message' => "L'hôtel n'est pas actif : aucun e-mail envoyé."], 409);
        }

        $owner = $hotel->users()->wherePivot('role', 'owner')->orderBy('users.id')->first();
        if (! $owner) {
            return response()->json(['sent' => false, 'message' => "Cet hôtel n'a pas de compte administrateur."], 409);
        }

        $sent = app(Notifier::class)->hotelAccess($hotel, $owner->email, $data['password'] ?? null, $data['event']);

        return response()->json([
            'sent' => $sent,
            'message' => $sent ? "E-mail envoyé à {$owner->email}." : "Envoi impossible (voir storage/logs/laravel.log).",
        ]);
    }
}
