<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lien avec l'administration générale (base « hotels ») : hôtel et offre de catalogue d'origine.
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedBigInteger('vitrine_id')->nullable()->unique()->after('currency');
        });
        Schema::table('room_types', function (Blueprint $table) {
            $table->unsignedBigInteger('vitrine_catalogue_id')->nullable()->unique()->after('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', fn (Blueprint $t) => $t->dropColumn('vitrine_catalogue_id'));
        Schema::table('hotels', fn (Blueprint $t) => $t->dropColumn('vitrine_id'));
    }
};
