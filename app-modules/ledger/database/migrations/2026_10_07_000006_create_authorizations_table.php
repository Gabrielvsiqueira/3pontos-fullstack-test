<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained();
            $table->string('card_token');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->char('mcc', 4);
            $table->string('merchant_name');
            $table->string('merchant_city');
            $table->char('merchant_country', 2);
            $table->timestampTz('occurred_at');
            $table->string('decision');
            $table->string('reason')->nullable();
            $table->jsonb('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorizations');
    }
};
