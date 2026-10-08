<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('network_authorization_id', 64)->unique();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->char('month', 7)->nullable();
            $table->bigInteger('authorized_cents')->default(0);
            $table->bigInteger('captured_cents')->default(0);
            $table->bigInteger('held_cents')->default(0);
            $table->boolean('closed')->default(false);
            $table->boolean('over_capture')->default(false);
            $table->boolean('over_purchase_limit')->default(false);
            $table->boolean('captured_when_declined')->default(false);
            $table->boolean('captured_after_cancellation')->default(false);
            $table->timestamps();

            $table->index(['card_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
