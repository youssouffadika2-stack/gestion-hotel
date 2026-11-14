<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services de l'hôtel qui font chacun leur « point » quotidien : Réception, Restaurant, Bar…
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20)->default('other'); // reception, restaurant, bar, laundry, spa, other
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'name']);
        });

        // Quels employés travaillent dans quels services.
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['department_id', 'user_id']);
        });

        // Le catalogue (plats, boissons, prestations) est rattaché à un service.
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('hotel_id')->constrained()->nullOnDelete();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 150);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20)->default('cash'); // cash, mobile_money, card, transfer
            $table->dateTime('sold_at');
            $table->date('business_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'department_id', 'business_date']);
            $table->index(['hotel_id', 'sold_at']);
        });

        // Point d'un service pour une journée.
        Schema::create('department_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('cash_expected')->default(0);
            $table->unsignedBigInteger('cash_counted')->nullable();
            $table->unsignedInteger('operations')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at');
            $table->timestamps();
            $table->unique(['department_id', 'business_date']);
        });

        // Point total de l'hôtel pour une journée (seulement possible quand tous les services sont clôturés).
        Schema::create('day_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedBigInteger('total_revenue')->default(0);
            $table->unsignedBigInteger('total_expenses')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at');
            $table->timestamps();
            $table->unique(['hotel_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closures');
        Schema::dropIfExists('department_closures');
        Schema::dropIfExists('sales');
        Schema::table('services', fn (Blueprint $t) => $t->dropConstrainedForeignId('department_id'));
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
    }
};
