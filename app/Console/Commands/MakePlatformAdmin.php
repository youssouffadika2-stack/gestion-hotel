<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakePlatformAdmin extends Command
{
    protected $signature = 'hotelpro:platform-admin {email : E-mail de connexion} {--name=Administrateur HOTELPRO : Nom affiché} {--password= : Mot de passe (généré si omis)}';

    protected $description = "Crée (ou promeut) un administrateur général de la plateforme, qui active les hôtels inscrits";

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: bin2hex(random_bytes(6));
        if (strlen($password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first() ?? new User(['name' => $this->option('name'), 'email' => $email]);
        $user->password = Hash::make($password);
        $user->email_verified_at ??= now();
        $user->forceFill(['is_platform_admin' => true])->save(); // jamais mass-assignable

        $this->info("Administrateur de la plateforme prêt : {$email}");
        $this->line("Mot de passe : {$password}");
        $this->line('Espace : '.url('/plateforme/hotels'));

        return self::SUCCESS;
    }
}
