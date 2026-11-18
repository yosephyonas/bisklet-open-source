<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('lock_code')->nullable()->unique();
            $table->string('status')->default('available');
        });

        // Register lock_code for the currently known device.
        DB::table('devices')
            ->where('imei', '862205059174740')
            ->update(['lock_code' => '1738908531']);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['lock_code']);
            $table->dropColumn(['lock_code', 'status']);
        });
    }
};
