<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('method', 40);
            $table->string('status', 40)->default('pending')->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('transaction_reference', 150)->nullable()->index();
            $table->timestampTz('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
