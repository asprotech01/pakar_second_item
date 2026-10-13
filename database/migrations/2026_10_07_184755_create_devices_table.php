<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_api_id')->unique();
            $table->string('name');
            $table->string('manufacturer_name')->nullable();
            $table->string('device_type')->nullable();
            $table->json('model_numbers')->nullable();
            $table->json('colors')->nullable();
            $table->json('storage')->nullable();
            $table->json('screen_resolution')->nullable();
            $table->json('weight')->nullable();
            $table->json('thickness')->nullable();
            $table->json('release_date')->nullable();
            $table->json('camera')->nullable();
            $table->json('battery_capacity')->nullable();
            $table->json('hardware')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
