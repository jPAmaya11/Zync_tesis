<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_mail_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('daily_limit')->default(100);
            $table->unsignedInteger('used_today')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->string('status', 32)->default('online');
            $table->json('config')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'priority']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_mail_providers');
    }
};
