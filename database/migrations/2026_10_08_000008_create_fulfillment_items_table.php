<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 100);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_items');
    }
};
