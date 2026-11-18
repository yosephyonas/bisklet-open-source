<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->decimal('last_latitude', 10, 7)->nullable()->after('metadata');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('last_longitude');
            $table->timestamp('location_updated_at')->nullable()->after('location_accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->dropColumn([
                'last_latitude',
                'last_longitude',
                'location_accuracy',
                'location_updated_at',
            ]);
        });
    }
};
