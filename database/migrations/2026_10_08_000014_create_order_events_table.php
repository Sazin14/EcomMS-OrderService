<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80)->index();
            $table->string('description', 500);
            $table->string('actor_type', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
