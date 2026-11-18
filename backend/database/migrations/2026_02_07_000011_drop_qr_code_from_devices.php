<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'qr_code')) {
                $table->dropUnique(['qr_code']);
                $table->dropColumn('qr_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'qr_code')) {
                $table->string('qr_code')->nullable()->unique()->after('lock_code');
            }
        });
    }
};
