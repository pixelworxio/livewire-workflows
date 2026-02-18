<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name')->index();
            $table->string('user_key')->index();
            $table->string('current_step')->nullable();
            $table->json('history')->nullable();
            $table->json('metadata')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['workflow_name', 'user_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};
