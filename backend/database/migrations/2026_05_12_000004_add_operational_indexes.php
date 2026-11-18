<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->index(['status', 'started_at'], 'rides_status_started_idx');
            $table->index(['status', 'ended_at'], 'rides_status_ended_idx');
            $table->index(['user_id', 'status', 'started_at'], 'rides_user_status_started_idx');
            $table->index(['bike_id', 'status'], 'rides_bike_status_idx');
            $table->index(['imei', 'status'], 'rides_imei_status_idx');
            $table->index(['billing_status', 'created_at'], 'rides_billing_created_idx');
        });

        Schema::table('bikes', function (Blueprint $table) {
            $table->index(['status', 'is_active'], 'bikes_status_active_idx');
            $table->index(['station_id', 'status'], 'bikes_station_status_idx');
            $table->index(['last_latitude', 'last_longitude'], 'bikes_location_idx');
            $table->index(['location_updated_at'], 'bikes_location_updated_idx');
        });

        Schema::table('lock_events', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'lock_events_type_created_idx');
        });

        Schema::table('lock_locations', function (Blueprint $table) {
            $table->index(['is_valid', 'captured_at'], 'lock_locations_valid_captured_idx');
        });

        Schema::table('scan_sessions', function (Blueprint $table) {
            $table->index(['scan_token_hash', 'expires_at'], 'scan_sessions_token_expires_idx');
            $table->index(['user_id', 'imei', 'used_at'], 'scan_sessions_user_imei_used_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scan_sessions', function (Blueprint $table) {
            $table->dropIndex('scan_sessions_token_expires_idx');
            $table->dropIndex('scan_sessions_user_imei_used_idx');
        });

        Schema::table('lock_locations', function (Blueprint $table) {
            $table->dropIndex('lock_locations_valid_captured_idx');
        });

        Schema::table('lock_events', function (Blueprint $table) {
            $table->dropIndex('lock_events_type_created_idx');
        });

        Schema::table('bikes', function (Blueprint $table) {
            $table->dropIndex('bikes_status_active_idx');
            $table->dropIndex('bikes_station_status_idx');
            $table->dropIndex('bikes_location_idx');
            $table->dropIndex('bikes_location_updated_idx');
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex('rides_status_started_idx');
            $table->dropIndex('rides_status_ended_idx');
            $table->dropIndex('rides_user_status_started_idx');
            $table->dropIndex('rides_bike_status_idx');
            $table->dropIndex('rides_imei_status_idx');
            $table->dropIndex('rides_billing_created_idx');
        });
    }
};
