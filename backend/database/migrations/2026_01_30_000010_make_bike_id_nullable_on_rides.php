<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['bike_id']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedBigInteger('bike_id')->nullable()->change();
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->foreign('bike_id')->references('id')->on('bikes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['bike_id']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedBigInteger('bike_id')->nullable(false)->change();
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->foreign('bike_id')->references('id')->on('bikes')->cascadeOnDelete();
        });
    }
};
