# High-Level Design (HLD) / Technical Solution Document



### Components:
- **Mobile Client (React Native):** Handles the UI, infinite scrolling, and user interactions.
- **Main Backend (Laravel PHP):** Acts as the primary API, managing authentication (Sanctum), business logic, relational data storage, and orchestrating calls to the Vector DB and Python service.
- **Python ML Service (FastAPI/Flask):** A lightweight internal service. Laravel sends text/image data here. Python generates vector embeddings (using e.g., `sentence-transformers/all-MiniLM-L6-v2`) and returns them to Laravel.
- **Primary Database (MySQL/PostgreSQL):** The source of truth. Stores users, posts (text, image URLs, metadata), and interactions (views, replies, reactions).
- **File Storage (AWS S3 or Local disk):** Stores the actual image files uploaded by users. The database only stores the URL/path to the image.
- **Vector Database (Pinecone):** Stores post embeddings and their metadata for semantic search and feed retrieval.

---

## 2. Database Schema Design (Relational)

We will use **PostgreSQL**. Below is the core schema.

### `users`
- `id` (PK, UUID or BIGINT)
- `name` (VARCHAR)
- `email` (VARCHAR, Unique)
- `password` (VARCHAR)
- `created_at`, `updated_at`

### `posts`
- `id` (PK, UUID or BIGINT)
- `user_id` (FK -> users.id, Index)
- `content` (TEXT)
- `image_url` (VARCHAR, Nullable)
- `authenticity_score` (FLOAT, Default: 1.0) - *Derived from ML analysis of text/image*
- `created_at`, `updated_at` (Index on created_at for time-decay)

### `interactions`
- `id` (PK, BIGINT)
- `user_id` (FK -> users.id, Index)
- `post_id` (FK -> posts.id, Index)
- `type` (ENUM: 'view', 'reaction', 'reply')
- `created_at` (Index)

**Indexes Strategy:**
- `posts(user_id, created_at DESC)` for efficient retrieval of a user's posts.
- `interactions(user_id, post_id)` to quickly check if a user interacted with a specific post.
- `interactions(user_id, type, created_at)` to calculate relationship depth.

---

## 3. Vector Embeddings Strategy

**Vector Database Choice: Pinecone**
- **Why Pinecone?** It provides a fully managed, serverless vector database that is incredibly easy to integrate via API. It handles high-throughput similarity search with very low latency, which is essential for a real-time social feed. It also supports metadata filtering, allowing us to filter by time or author during the semantic search.
- **Embedding Model:** `sentence-transformers/all-MiniLM-L6-v2` (open-source, fast, 384 dimensions) running on the Python service. If API credits/hosting is an issue for the submission, we can mock it using a local hashing function or a deterministic pseudo-random vector generator, but Pinecone offers a free tier that is perfect for this.

**Workflow:**
1. Post is created in Laravel.
2. Laravel sends post text to Python service.
3. Python returns a 384-dimensional vector.
4. Laravel saves the post to MySQL and upserts the vector to Pinecone with metadata `(post_id, author_id, timestamp)`.

---

## 4. API Design & Auth Strategy

**Auth Strategy (Laravel Sanctum):**
We are using Laravel Sanctum for API token authentication. 
- **Login/Registration:** When a user logs in, Sanctum generates a plain-text API token.
- **Client Storage:** The React Native app stores this token securely (e.g., using `SecureStore` or `Keychain`).
- **Requests:** Every API request to protected endpoints must include the header: `Authorization: Bearer <token>`.
- **Stateless:** This token-based approach is stateless on the server side, ensuring scalability and easy integration with the mobile app.

| Method | Endpoint | Request Body/Query | Response |
|--------|----------|-------------------|----------|
| POST | `/api/posts` | `{ "content": "Hello", "image_url": "http..." }` | `{ "id": 1, "status": "created" }` |
| GET | `/api/feed` | `?page=1` | `{ "data": [{post objects}], "next_page_url": "..." }` |
| GET | `/api/search` | `?q=funny cats` | `{ "data": [{post objects}] }` |
| POST | `/api/interactions`| `{ "post_id": 1, "type": "reaction" }` | `{ "status": "logged" }` |

---

## 5. Feed Ranking Algorithm

### Plain English
To build the "RealConnections" feed, we need to balance four signals. When a user requests their feed, we don't just pull the latest rows. Instead, we:
1. Fetch a broad candidate set of recent posts (e.g., from the last 7 days).
2. For each post, we calculate a **Final Score** based on:
   - **Relationship Depth:** Has the current user interacted heavily with this post's author in the past? (Multiplier increases if they have).
   - **Authenticity:** Base score assigned at creation (less polished = higher score).
   - **Time Decay:** The older the post, the lower the multiplier. An exponential decay function ensures fresh content surfaces naturally.
   - **Semantic Similarity (Optional for general feed, heavily weighted for search/discovery):** How closely does this post match the user's historical interaction embeddings?
3. We sort the candidate set by this Final Score in descending order and paginate the results.

### Pseudocode

```python
def get_personalized_feed(current_user, page):
    # 1. Fetch candidate posts (e.g., last 7 days)
    candidates = fetch_recent_posts(days=7)
    
    # 2. Fetch relationship depth scores for the current user
    # Dict mapping author_id -> interaction_weight
    user_affinity = calculate_user_affinity(current_user.id) 
    
    ranked_posts = []
    
    for post in candidates:
        # Authenticity Signal (0.0 to 1.0)
        auth_score = post.authenticity_score 
        
        # Relationship Depth (e.g., 1.0 base + 0.1 per interaction)
        affinity_multiplier = user_affinity.get(post.author_id, 1.0)
        
        # Time Decay (Half-life of 24 hours)
        hours_old = (now() - post.created_at).hours
        time_multiplier = math.exp(-0.693 * (hours_old / 24))
        
        # Final Ranking Score
        final_score = auth_score * affinity_multiplier * time_multiplier
        
        ranked_posts.append({
            "post": post,
            "score": final_score
        })
        
    # 3. Sort and Paginate
    ranked_posts.sort(key=lambda x: x.score, descending=True)
    return paginate(ranked_posts, page, per_page=20)
```

---

## 6. AI Agentic Tools Strategy

As required by the brief, this project is built using AI agentic tools to move fast (targeting 80%+ efficiency).

- **Antigravity (DeepMind Agent):** Used as the primary reasoning engine to architect the system, design the database schema, write the Technical Solution Document, and structure the project plan. It reads project briefs autonomously and outputs structured documentation.
- **Cursor / GitHub Copilot (Code Generation):** To be used during execution for boilerplate generation (Laravel migrations, controllers), autocompleting React Native styling (using standard StyleSheet logic rather than defaults), and writing standard SQL queries.
- **Claude (Problem Solving):** For complex logic, such as refining the math in the feed ranking algorithm (time decay functions) or debugging any React Native/Python integration issues.

---

## 7. Trade-offs & Assumptions

- **Synchronous vs Asynchronous ML:** Generating embeddings synchronously during post creation adds latency. *Trade-off:* For this assessment, synchronous is acceptable for simplicity, but in production, we would use a queue (e.g., Laravel Horizon) to process embeddings asynchronously.
- **Candidate Generation:** Fetching *all* recent posts and ranking them in-memory is fine for a small user base (the scope of the assessment). For millions of users, we would need to pre-compute feeds or use Pinecone's vector search to retrieve the initial candidate set.
- **Authenticity Scoring:** Since we don't have a complex ML model to judge "polish", we assume `authenticity_score` is a randomly generated baseline or derived from a basic heuristic (e.g., shorter text / no image = slightly higher authenticity).

---


