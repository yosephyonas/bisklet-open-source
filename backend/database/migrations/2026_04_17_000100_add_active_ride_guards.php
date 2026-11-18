<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedBigInteger('active_user_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('active_bike_id')->nullable()->after('bike_id');
            $table->string('active_imei', 15)->nullable()->after('imei');
        });

        DB::statement("
            UPDATE rides
            SET active_user_id = user_id,
                active_bike_id = bike_id,
                active_imei = imei
            WHERE status = 'active'
        ");

        Schema::table('rides', function (Blueprint $table) {
            $table->unique('active_user_id', 'rides_active_user_unique');
            $table->unique('active_bike_id', 'rides_active_bike_unique');
            $table->unique('active_imei', 'rides_active_imei_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropUnique('rides_active_user_unique');
            $table->dropUnique('rides_active_bike_unique');
            $table->dropUnique('rides_active_imei_unique');
            $table->dropColumn([
                'active_user_id',
                'active_bike_id',
                'active_imei',
            ]);
        });
    }
};
