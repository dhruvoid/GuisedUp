<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->string('image_url')->nullable();
            // authenticity_score: 0.0 (highly polished) to 1.0 (raw/genuine)
            // Derived from heuristics at creation time
            $table->float('authenticity_score')->default(0.5);
            // pinecone_vector_id stores the ID used in Pinecone for this post's embedding
            $table->string('pinecone_vector_id')->nullable();
            $table->timestamps();

            // Indexes for efficient feed queries
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
