<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient', 191);
            $table->string('provider', 64)->nullable();
            $table->string('subject', 255);
            $table->json('payload')->nullable();
            $table->longText('response')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('trigger_type', 64)->nullable();
            $table->string('model_type', 191)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('provider');
            $table->index('trigger_type');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_mail_logs');
    }
};
