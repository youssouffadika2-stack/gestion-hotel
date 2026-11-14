<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // staff = saisie par l'hôtel ; online = demande du client depuis la page publique
            $table->string('source', 20)->default('staff')->after('status');
            $table->text('guest_message')->nullable()->after('source');       // message / demandes du client
            $table->unsignedSmallInteger('guests_count')->nullable()->after('guest_message');
            $table->timestamp('read_at')->nullable()->after('guests_count');  // null = demande non lue par l'hôtel
            $table->index(['hotel_id', 'source', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['hotel_id', 'source', 'read_at']);
            $table->dropColumn(['source', 'guest_message', 'guests_count', 'read_at']);
        });
    }
};
