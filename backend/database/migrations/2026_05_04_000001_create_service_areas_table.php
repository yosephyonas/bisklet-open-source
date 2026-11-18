<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('polygon');
            $table->string('fill_color', 16)->default('#178F89');
            $table->string('stroke_color', 16)->default('#0F746F');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_areas');
    }
};
