<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->string('courier', 100);
            $table->string('tracking_number', 150)->index();
            $table->string('tracking_url', 500)->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['courier', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
