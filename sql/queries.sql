-- ============================================================
-- Guised Up — SQL Challenge Queries
-- Part D: Raw SQL Queries
-- ============================================================

-- D1: Top 10 most active users in the last 7 days,
--     ranked by total interactions (views + replies + reactions)
SELECT
    u.id AS user_id,
    u.name,
    u.email,
    COUNT(i.id) AS total_interactions
FROM users u
INNER JOIN interactions i ON i.user_id = u.id
WHERE i.created_at >= NOW() - INTERVAL 7 DAY
GROUP BY u.id, u.name, u.email
ORDER BY total_interactions DESC
LIMIT 10;

-- ============================================================

-- D2: For a given user_id (replace ? with the actual user_id),
--     return all posts from users they interact with most,
--     ordered by interaction frequency descending,
--     limited to posts from the last 30 days.
SELECT
    p.id AS post_id,
    p.user_id AS author_id,
    p.content,
    p.image_url,
    p.created_at,
    aff.interaction_count
FROM posts p
INNER JOIN (
    SELECT
        po.user_id AS author_id,
        COUNT(i.id) AS interaction_count
    FROM interactions i
    INNER JOIN posts po ON po.id = i.post_id
    WHERE i.user_id = ?          -- <-- replace with target user_id
      AND i.created_at >= NOW() - INTERVAL 30 DAY
    GROUP BY po.user_id
) AS aff ON aff.author_id = p.user_id
WHERE p.created_at >= NOW() - INTERVAL 30 DAY
ORDER BY aff.interaction_count DESC;

-- ============================================================

-- D3: Find posts viewed more than 100 times but with zero reactions.
--     Returns: post_id, author_id, view_count, created_at
SELECT
    p.id AS post_id,
    p.user_id AS author_id,
    SUM(CASE WHEN i.type = 'view' THEN 1 ELSE 0 END) AS view_count,
    p.created_at
FROM posts p
LEFT JOIN interactions i ON i.post_id = p.id
GROUP BY p.id, p.user_id, p.created_at
HAVING
    view_count > 100
    AND SUM(CASE WHEN i.type = 'reaction' THEN 1 ELSE 0 END) = 0
ORDER BY view_count DESC;

-- ============================================================

-- D4: Detect potential spam — users who have created more than 20 posts
--     in the last 24 hours. Includes their email and post count.
SELECT
    u.id AS user_id,
    u.name,
    u.email,
    COUNT(p.id) AS post_count_24h
FROM users u
INNER JOIN posts p ON p.user_id = u.id
WHERE p.created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY u.id, u.name, u.email
HAVING post_count_24h > 20
ORDER BY post_count_24h DESC;
