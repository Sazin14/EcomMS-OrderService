<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 100);
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id']);
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
