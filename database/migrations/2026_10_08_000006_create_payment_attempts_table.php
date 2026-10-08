<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60);
            $table->string('status', 40)->index();
            $table->decimal('amount', 14, 2);
            $table->string('provider_reference', 150)->nullable()->index();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('request_id', 150)->nullable()->index();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
