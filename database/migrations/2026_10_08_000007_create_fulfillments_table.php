<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('unfulfilled')->index();
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->string('tracking_number', 150)->nullable()->index();
            $table->string('courier', 100)->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();

            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
