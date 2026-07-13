# Guised Up — Full-Stack Developer Assessment

> **"Real people, real connections."**
> Built from India, for the world. 🇮🇳

---

## 📁 Project Structure

```
Guised_Up/
├── docs/
│   └── TSD.md                # Technical Solution Document
├── backend/                  # Laravel 11 PHP API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── PostController.php
│   │   │   ├── FeedController.php
│   │   │   ├── SearchController.php
│   │   │   └── InteractionController.php
│   │   ├── Models/           # User, Post, Interaction
│   │   └── Services/
│   │       ├── FeedRankingService.php   # Core ranking algorithm
│   │       └── EmbeddingService.php     # Laravel ↔ Python bridge
│   ├── database/
│   │   ├── migrations/       # users, posts, interactions, tokens
│   │   └── seeders/          # 5 test users + sample posts
│   └── routes/api.php
├── ml-service/               # Python FastAPI ML Microservice
│   ├── main.py               # /embed, /upsert, /search endpoints
│   ├── requirements.txt
│   └── .env.example
├── mobile/                   # React Native / Expo Frontend
│   └── src/
│       ├── screens/FeedScreen.js
│       ├── components/PostCard.js
│       └── services/api.js
├── sql/
│   └── queries.sql           # SQL Challenge queries
└── README.md
```

---

## ⚙️ Setup & Running

### Prerequisites

| Tool | Version |
|------|---------|
| PHP | ≥ 8.2 |
| Composer | latest |
| PostgreSQL | ≥ 14 |
| Python | ≥ 3.10 |
| Node.js | ≥ 18 |
| Expo Go | latest (App Store / Play Store) |

---

### 1. Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your PostgreSQL credentials:
```
DB_CONNECTION=pgsql
DB_DATABASE=guised_up
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Run migrations and seed:
```bash
php artisan migrate
php artisan db:seed
```

Start the server:
```bash
php artisan serve --host=0.0.0.0
# Runs on http://localhost:8000
```

**Seeded test credentials:**
| Email | Password |
|-------|----------|
| `priya@guisedup.com` | `password123` |
| `arjun@guisedup.com` | `password123` |
| `rahul@guisedup.com` | `password123` |
| `sneha@guisedup.com` | `password123` |
| `rohan@guisedup.com` | `password123` |

---

### 2. ML Service (Python / FastAPI)

```bash
cd ml-service
pip install -r requirements.txt
cp .env.example .env
python main.py
# Runs on http://localhost:8001
```

> **No Pinecone API key?** Leave `PINECONE_API_KEY` empty — the service automatically runs in **MOCK MODE** using in-memory vector storage. All features work identically for local development.

---

### 3. Mobile App (React Native / Expo)

```bash
cd mobile
npm install
npx expo start --lan
```

Scan the QR code with **Expo Go** on your phone, or press `w` to open in the web browser.

> **Important:** Update `src/services/api.js` → `BASE_URL` to point to your machine's local IP address (e.g. `http://192.168.x.x:8000/api`) for physical device testing.

---

## 🔑 API Endpoints

All protected endpoints require: `Authorization: Bearer <token>`

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `POST` | `/api/register` | ❌ | Register + get token |
| `POST` | `/api/login` | ❌ | Login + get token |
| `POST` | `/api/logout` | ✅ | Revoke token |
| `GET` | `/api/user` | ✅ | Logged-in user profile |
| `POST` | `/api/posts` | ✅ | Create a post |
| `GET` | `/api/feed?page=1` | ✅ | Personalized ranked feed |
| `GET` | `/api/search?q=query` | ✅ | Natural language semantic search |
| `POST` | `/api/interactions` | ✅ | Log view / reaction |

---

## 🤖 Feed Ranking Algorithm

The feed is sorted by a composite score:

```
score = authenticity_score × relationship_multiplier × time_multiplier
```

| Signal | Description |
|--------|-------------|
| **Authenticity Score** | Heuristic (0–1): penalizes hashtags, excessive emojis, and polished images |
| **Relationship Depth** | `1.0 + 0.1 × past_interactions` (capped at 3.0) — rewards authors you engage with |
| **Time Decay** | `exp(-λ × hours_old)` with λ = ln(2)/24 — 50% decay every 24 hours |

---

## 📄 Technical Solution Document

See [`docs/TSD.md`](docs/TSD.md) for the full design including system architecture, database schema, vector embedding strategy, and trade-offs.

---

## 📊 SQL Challenge

See [`sql/queries.sql`](sql/queries.sql) for all queries:
- Top 10 active users (last 7 days)
- Posts from most-interacted-with authors (last 30 days)
- High-view, zero-reaction posts
- Spam detection (>20 posts in 24 hours)

---

*Guised Up © 2026*
