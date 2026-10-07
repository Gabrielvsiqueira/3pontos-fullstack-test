<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->char('month', 7)->nullable();
            $table->string('type');
            $table->string('reference');
            $table->timestampTz('occurred_at');
            $table->bigInteger('limit_delta_cents');
            $table->bigInteger('balance_delta_cents');
            $table->bigInteger('held_delta_cents');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['source_type', 'source_id']);
            $table->index(['card_id', 'month', 'id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION transactions_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'transactions is append-only: % is not allowed', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER transactions_append_only
                BEFORE UPDATE OR DELETE ON transactions
                FOR EACH ROW EXECUTE FUNCTION transactions_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        DB::unprepared('DROP FUNCTION IF EXISTS transactions_append_only()');
    }
};
