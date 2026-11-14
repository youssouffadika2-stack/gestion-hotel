<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\HotelProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * S'inscrire = ouvrir un compte HOTELPRO : l'inscrit devient l'administrateur
     * (propriétaire) de son hôtel et pourra ensuite donner des accès à son personnel.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'hotel_name' => ['required', 'string', 'min:2', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ], [], [
            'hotel_name' => "nom de l'hôtel",
            'city' => 'ville',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            app(HotelProvisioner::class)->registerOwner($user, trim($input['hotel_name']), $input['city'] ?? null);

            return $user;
        });
    }
}
