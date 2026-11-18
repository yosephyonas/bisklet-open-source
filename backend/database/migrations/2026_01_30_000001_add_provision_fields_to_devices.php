<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('target_host')->nullable();
            $table->unsignedInteger('target_port')->nullable();
            $table->string('provision_status')->default('pending');
            $table->dateTime('last_provisioned_at')->nullable();
            $table->foreignId('last_provisioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['last_provisioned_by_user_id']);
            $table->dropColumn([
                'target_host',
                'target_port',
                'provision_status',
                'last_provisioned_at',
                'last_provisioned_by_user_id',
            ]);
        });
    }
};
