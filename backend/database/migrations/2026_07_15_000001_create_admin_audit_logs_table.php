<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('description')->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('method', 12)->nullable()->index();
            $table->string('path')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
