<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingService
 *
 * Acts as the bridge between Laravel and the Python ML microservice.
 * Responsible for:
 *   1. Requesting embeddings for post content
 *   2. Performing vector search via the Python service (which queries Pinecone)
 *
 * If the Python service is unavailable, it falls back to a deterministic
 * mock embedding using a hash — as described in the TSD.
 */
class EmbeddingService
{
    private string $mlServiceUrl;

    public function __construct()
    {
        $this->mlServiceUrl = config('services.ml_service.url', 'http://localhost:8001');
    }

    /**
     * Get a 384-dimensional vector embedding for the given text.
     * Calls the Python FastAPI /embed endpoint.
     *
     * @param  string $text
     * @return array<float>
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->mlServiceUrl}/embed", [
                'text' => $text,
            ]);

            if ($response->successful()) {
                return $response->json('embedding');
            }
        } catch (\Exception $e) {
            Log::warning('ML service unavailable, using mock embedding.', ['error' => $e->getMessage()]);
        }

        // Fallback: deterministic mock embedding via hash
        return $this->mockEmbedding($text);
    }

    /**
     * Search Pinecone for the top-k most similar posts to the given query.
     * Returns an ordered list of post IDs.
     *
     * @param  string $query
     * @param  int    $topK
     * @return array<int>  List of post IDs from the DB
     */
    public function search(string $query, int $topK = 10): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->mlServiceUrl}/search", [
                'query' => $query,
                'top_k' => $topK,
            ]);

            if ($response->successful()) {
                return $response->json('post_ids', []);
            }
        } catch (\Exception $e) {
            Log::warning('ML search service unavailable.', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Generate a deterministic mock 384-dim embedding from text hash.
     * This is the "mock" described in the TSD for environments without
     * the Python service running.
     *
     * The mock is seeded by the text, so identical texts always produce
     * identical vectors — useful for testing.
     *
     * @param  string $text
     * @return array<float>
     */
    private function mockEmbedding(string $text): array
    {
        $hash  = md5($text);
        $seed  = hexdec(substr($hash, 0, 8));
        $dims  = 384;
        $vector = [];

        mt_srand($seed);
        for ($i = 0; $i < $dims; $i++) {
            $vector[] = (mt_rand(-1000, 1000)) / 1000.0;
        }

        // L2-normalize the vector (unit vector)
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        if ($magnitude > 0) {
            $vector = array_map(fn($v) => $v / $magnitude, $vector);
        }

        return $vector;
    }
}
