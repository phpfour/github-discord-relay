<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_routes', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // github | linear
            $table->string('scope'); // repo | org | project | team | global
            $table->string('match_value')->nullable(); // null for global scope
            $table->text('discord_webhook_url');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_routes');
    }
};
