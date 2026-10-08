<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('card_id')->constrained();
            $table->char('month', 7);
            $table->bigInteger('limit_delta_cents')->default(0);
            $table->timestamps();

            $table->unique(['card_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_months');
    }
};
