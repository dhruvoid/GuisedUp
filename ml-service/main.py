"""
Guised Up — ML Service
======================
FastAPI microservice for:
  1. /embed   — Generate vector embeddings for post text
  2. /upsert  — Store a post embedding in Pinecone
  3. /search  — Query Pinecone for semantically similar posts

Stack:
  - FastAPI (HTTP server)
  - sentence-transformers (embedding model: all-MiniLM-L6-v2, 384 dims)
  - Pinecone (vector database)

If PINECONE_API_KEY is not set, the service operates in MOCK MODE:
  - Embeddings are still generated via sentence-transformers
  - Vector storage and search are simulated in-memory (for local development)
"""

import os
import math
from typing import Optional

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from dotenv import load_dotenv

# ── Load environment variables ─────────────────────────────────────────────
load_dotenv()

PINECONE_API_KEY   = os.getenv("PINECONE_API_KEY", "")
PINECONE_INDEX     = os.getenv("PINECONE_INDEX_NAME", "guised-up-posts")
MOCK_MODE          = not bool(PINECONE_API_KEY)

# ── Initialize app ─────────────────────────────────────────────────────────
app = FastAPI(title="Guised Up ML Service", version="1.0.0")

# ── Load sentence-transformers model (once at startup) ─────────────────────
print("Loading embedding model (sentence-transformers/all-MiniLM-L6-v2)...")
try:
    from sentence_transformers import SentenceTransformer
    model = SentenceTransformer("sentence-transformers/all-MiniLM-L6-v2")
    print("[OK] Embedding model loaded.")
except ImportError:
    model = None
    print("[WARN] sentence-transformers not installed - using hash-based mock embeddings.")

# ── Pinecone setup ──────────────────────────────────────────────────────────
pinecone_index = None
# In-memory mock store: dict[vector_id → {embedding, post_id, metadata}]
mock_store: dict[str, dict] = {}

if not MOCK_MODE:
    try:
        from pinecone import Pinecone, ServerlessSpec
        pc = Pinecone(api_key=PINECONE_API_KEY)
        existing = [idx.name for idx in pc.list_indexes()]
        if PINECONE_INDEX not in existing:
            pc.create_index(
                name=PINECONE_INDEX,
                dimension=384,
                metric="cosine",
                spec=ServerlessSpec(cloud="aws", region="us-east-1"),
            )
        pinecone_index = pc.Index(PINECONE_INDEX)
        print(f"[OK] Connected to Pinecone index '{PINECONE_INDEX}'.")
    except Exception as e:
        print(f"[WARN] Pinecone init failed - switching to mock mode. Error: {e}")
        MOCK_MODE = True
else:
    print("[INFO] Running in MOCK MODE (no Pinecone API key). Vectors stored in-memory.")


# ── Utility Functions ───────────────────────────────────────────────────────

def get_embedding(text: str) -> list[float]:
    """Generate a 384-dim embedding. Falls back to hash-mock if model unavailable."""
    if model:
        embedding = model.encode(text, normalize_embeddings=True).tolist()
        return embedding
    else:
        return _hash_mock_embedding(text)


def _hash_mock_embedding(text: str) -> list[float]:
    """Deterministic mock embedding from MD5 hash (for testing without GPU)."""
    import hashlib, struct
    dims = 384
    digest = hashlib.md5(text.encode()).digest()
    seed = struct.unpack("<I", digest[:4])[0]

    import random
    rng = random.Random(seed)
    vec = [rng.uniform(-1, 1) for _ in range(dims)]

    # L2 normalize
    magnitude = math.sqrt(sum(v * v for v in vec))
    if magnitude > 0:
        vec = [v / magnitude for v in vec]
    return vec


# ── Request / Response Models ───────────────────────────────────────────────

class EmbedRequest(BaseModel):
    text: str

class EmbedResponse(BaseModel):
    embedding: list[float]
    model: str
    dimensions: int

class UpsertRequest(BaseModel):
    post_id: int
    author_id: int
    embedding: list[float]
    created_at: str

class UpsertResponse(BaseModel):
    vector_id: str
    status: str

class SearchRequest(BaseModel):
    query: str
    top_k: int = 10

class SearchResponse(BaseModel):
    post_ids: list[int]
    query: str


# ── Endpoints ───────────────────────────────────────────────────────────────

@app.get("/health")
def health():
    return {
        "status": "ok",
        "mock_mode": MOCK_MODE,
        "model_loaded": model is not None,
    }


@app.post("/embed", response_model=EmbedResponse)
def embed(req: EmbedRequest):
    """
    Generate a vector embedding for the given text.
    Called by Laravel when a new post is created.
    """
    if not req.text.strip():
        raise HTTPException(status_code=422, detail="Text cannot be empty.")

    embedding = get_embedding(req.text)

    return EmbedResponse(
        embedding=embedding,
        model="all-MiniLM-L6-v2" if model else "mock-hash",
        dimensions=len(embedding),
    )


@app.post("/upsert", response_model=UpsertResponse)
def upsert(req: UpsertRequest):
    """
    Store a post's embedding in Pinecone (or mock store).
    Called by Laravel after post creation.
    """
    vector_id = f"post-{req.post_id}"

    metadata = {
        "post_id":    req.post_id,
        "author_id":  req.author_id,
        "created_at": req.created_at,
    }

    if not MOCK_MODE and pinecone_index:
        pinecone_index.upsert(vectors=[{
            "id":       vector_id,
            "values":   req.embedding,
            "metadata": metadata,
        }])
    else:
        # Store in-memory mock
        mock_store[vector_id] = {
            "embedding": req.embedding,
            "metadata":  metadata,
        }

    return UpsertResponse(vector_id=vector_id, status="upserted")


@app.post("/search", response_model=SearchResponse)
def search(req: SearchRequest):
    """
    Semantic search: embed the query and find similar posts.
    Returns ordered list of post_ids (most similar first).
    """
    if not req.query.strip():
        raise HTTPException(status_code=422, detail="Query cannot be empty.")

    query_embedding = get_embedding(req.query)

    if not MOCK_MODE and pinecone_index:
        results = pinecone_index.query(
            vector=query_embedding,
            top_k=req.top_k,
            include_metadata=True,
        )
        post_ids = [
            int(match["metadata"]["post_id"])
            for match in results["matches"]
            if "post_id" in match.get("metadata", {})
        ]
    else:
        # Mock: compute cosine similarity against in-memory store
        def cosine_sim(a: list[float], b: list[float]) -> float:
            dot   = sum(x * y for x, y in zip(a, b))
            mag_a = math.sqrt(sum(x * x for x in a))
            mag_b = math.sqrt(sum(x * x for x in b))
            if mag_a == 0 or mag_b == 0:
                return 0.0
            return dot / (mag_a * mag_b)

        scored = [
            (cosine_sim(query_embedding, item["embedding"]), item["metadata"]["post_id"])
            for item in mock_store.values()
        ]
        scored.sort(key=lambda x: x[0], reverse=True)
        post_ids = [int(pid) for _, pid in scored[: req.top_k]]

    return SearchResponse(post_ids=post_ids, query=req.query)


# ── Entry point ─────────────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8001, reload=True)
