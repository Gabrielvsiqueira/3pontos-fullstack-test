<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captures', function (Blueprint $table): void {
            $table->id();
            $table->string('network_id', 64)->unique();
            $table->foreignId('purchase_id')->constrained();
            $table->unsignedInteger('sequence');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->boolean('final');
            $table->timestampTz('occurred_at');
            $table->jsonb('payload');
            $table->timestamps();

            // Chave natural: uma reemissão com id novo cai aqui (Decisão 10).
            $table->unique(['purchase_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captures');
    }
};
