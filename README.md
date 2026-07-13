# Guised Up — Full-Stack Developer Assessment

> **"Real people, real connections."**
> Built from India, for the world. 🇮🇳

---

## 📁 Project Structure

```
Guised_Up/
├── docs/
│   └── TSD.md                # Technical Solution Document (Part A)
├── backend/                  # Laravel PHP API (Part B)
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
│   │   ├── migrations/       # users, posts, interactions
│   │   └── seeders/          # 2 test users + sample posts
│   ├── routes/api.php
│   └── tests/
│       ├── Unit/FeedRankingServiceTest.php
│       └── Feature/PostApiTest.php
├── ml-service/               # Python FastAPI ML service (Part B)
│   ├── main.py               # Embed, upsert, search endpoints
│   ├── requirements.txt
│   └── .env.example
├── mobile/                   # React Native / Expo (Part C)
│   └── src/
│       ├── screens/FeedScreen.js
│       ├── components/PostCard.js
│       └── services/api.js
├── sql/
│   └── queries.sql           # SQL Challenge (Part D)
└── README.md
```

---

## ⚙️ Setup & Running

### Prerequisites

| Tool | Version | Install |
|------|---------|---------|
| PHP | ≥ 8.2 | [php.net](https://php.net) |
| Composer | latest | [getcomposer.org](https://getcomposer.org) |
| MySQL / PostgreSQL | ≥ 8.0 | [mysql.com](https://mysql.com) |
| Python | ≥ 3.10 | [python.org](https://python.org) |
| Node.js | ≥ 18 | [nodejs.org](https://nodejs.org) |
| Expo Go | latest | App Store / Play Store |

---

### 1. Clone & Set Up the Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:
```
DB_DATABASE=guised_up
DB_USERNAME=root
DB_PASSWORD=your_password
```

Run migrations and seed:
```bash
php artisan migrate
php artisan db:seed
```

Start the backend:
```bash
php artisan serve
# Runs on http://localhost:8000
```

**Test credentials (seeded):**
- `priya@guisedup.com` / `password123`
- `arjun@guisedup.com` / `password123`

---

### 2. Set Up the ML Service (Python)

```bash
cd ml-service
python -m pip install -r requirements.txt
cp .env.example .env
```

> **No Pinecone API key?** Leave `PINECONE_API_KEY` empty — the service auto-detects and runs in **MOCK MODE** using in-memory vector storage. Everything works the same way for local development.

Start the ML service:
```bash
python main.py
# Runs on http://localhost:8001
```

---

### 3. Run the React Native App (Expo)

```bash
cd mobile
npm install
npx expo start
```

Scan the QR code with **Expo Go** on your phone, or press `a` for Android emulator / `i` for iOS simulator.

> Update `src/services/api.js` → `BASE_URL` to point to your local Laravel server IP (use your machine's local IP, not `localhost`, for physical devices).

---

## 🧪 Running Tests

```bash
cd backend
php artisan test
```

Tests cover:
- `FeedRankingServiceTest` — Unit tests for authenticity scoring, time decay math (half-life verification), relationship depth multiplier, and score capping
- `PostApiTest` — Feature tests for authentication, validation, and response structure

---

## 🔑 API Endpoints

All protected endpoints require: `Authorization: Bearer <token>`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register + get token |
| POST | `/api/login` | Login + get token |
| POST | `/api/logout` | Revoke token |
| POST | `/api/posts` | Create a post |
| GET | `/api/feed?page=1` | Personalized ranked feed |
| GET | `/api/search?q=query` | Natural language search |
| POST | `/api/interactions` | Log view/reaction/reply |

---

## 🤖 AI Tools Used

| Tool | How Used |
|------|----------|
| **Antigravity (DeepMind)** | Architected the entire system from the brief, wrote TSD, planned the implementation, generated all code |
| **Cursor / Copilot** | Code completion and boilerplate acceleration |
| **Claude** | Algorithmic reasoning (feed ranking math, time decay function) |

---

## 📄 Technical Solution Document

See [`docs/TSD.md`](docs/TSD.md) for the full Technical Solution Document including:
- System architecture diagram
- Database schema
- Vector embedding strategy
- API design & auth strategy
- Feed ranking algorithm (plain English + pseudocode)
- Trade-offs & assumptions

---

## 📊 SQL Challenge

See [`sql/queries.sql`](sql/queries.sql) for all 4 queries:
- D1: Top 10 active users (last 7 days)
- D2: Posts from most-interacted-with authors (last 30 days)
- D3: High-view, zero-reaction posts
- D4: Spam detection (>20 posts in 24h)

---

*Guised Up © 2026 — Do not distribute*
