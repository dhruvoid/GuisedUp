<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['view', 'reply', 'reaction']);
            $table->timestamps();

            // Composite index for relationship-depth queries
            $table->index(['user_id', 'post_id']);
            // Index for time-scoped queries (spam detection, activity ranking)
            $table->index(['user_id', 'type', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
