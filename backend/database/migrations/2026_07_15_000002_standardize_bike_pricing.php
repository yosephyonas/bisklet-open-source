<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rate = (float) config('pricing.rides.per_minute_rate', 0.5);
        $unlockFee = (float) config('pricing.rides.unlock_fee', 5);

        Schema::table('bikes', function (Blueprint $table) use ($rate, $unlockFee) {
            $table->decimal('price_per_minute', 8, 2)->default($rate)->change();
            $table->decimal('unlock_fee', 8, 2)->default($unlockFee)->change();
        });

        DB::table('bikes')->update([
            'price_per_minute' => $rate,
            'unlock_fee' => $unlockFee,
        ]);
    }

    public function down(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->decimal('price_per_minute', 8, 2)->default(0)->change();
            $table->decimal('unlock_fee', 8, 2)->default(0)->change();
        });
    }
};
