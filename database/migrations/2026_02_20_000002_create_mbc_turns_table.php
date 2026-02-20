<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mbc_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('mbc_sessions')->cascadeOnDelete();
            $table->unsignedInteger('turn_number');
            $table->string('type', 20);
            $table->json('content');
            $table->json('tool_calls')->nullable();
            $table->json('tool_results')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('stop_reason', 50)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['session_id', 'turn_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mbc_turns');
    }
};
