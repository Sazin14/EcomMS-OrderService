<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 100);
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->timestamps();

            $table->index('variant_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
