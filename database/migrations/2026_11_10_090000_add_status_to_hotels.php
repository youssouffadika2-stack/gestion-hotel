<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            // pending = inscrit, en attente d'activation par l'administrateur de la plateforme
            // active = accès ouvert ; suspended = accès coupé
            // Les hôtels déjà existants restent actifs (valeur par défaut).
            $table->string('status', 20)->default('active')->after('currency');
            $table->timestamp('activated_at')->nullable()->after('status');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'activated_at']);
        });
    }
};
