<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->string('imei', 15)->nullable()->unique()->after('code');
            $table->foreign('imei')->references('imei')->on('devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->dropForeign(['imei']);
            $table->dropUnique(['imei']);
            $table->dropColumn('imei');
        });
    }
};
