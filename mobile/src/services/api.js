/**
 * api.js — Guised Up API Service Layer
 *
 * Centralises all HTTP calls to the Laravel backend.
 * Automatically injects the Bearer token from AsyncStorage.
 */

// ── Change this to your Laravel server URL ──────────────────────────────────
const BASE_URL = 'http://192.168.31.221:8000/api';

// ── Token helpers ────────────────────────────────────────────────────────────
let memoryToken = null;

export const saveToken = async (token) => {
  memoryToken = token;
};

export const getToken = async () => {
  return memoryToken;
};

export const removeToken = async () => {
  memoryToken = null;
};

// ── Core fetch wrapper ───────────────────────────────────────────────────────

async function request(path, options = {}) {
  const token = await getToken();

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(`${BASE_URL}${path}`, {
    ...options,
    headers,
  });

  const data = await response.json();

  if (!response.ok) {
    const message =
      data?.message || data?.error || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return data;
}

// ── Auth ─────────────────────────────────────────────────────────────────────

export const login = async (email, password) => {
  const data = await request('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  await saveToken(data.token);
  return data;
};

export const register = async (name, email, password) => {
  const data = await request('/register', {
    method: 'POST',
    body: JSON.stringify({ name, email, password, password_confirmation: password }),
  });
  await saveToken(data.token);
  return data;
};

export const logout = async () => {
  await request('/logout', { method: 'POST' });
  await removeToken();
};

// ── Feed ─────────────────────────────────────────────────────────────────────

export const getFeed = (page = 1) =>
  request(`/feed?page=${page}`);

// ── Search ───────────────────────────────────────────────────────────────────

export const searchPosts = (query) =>
  request(`/search?q=${encodeURIComponent(query)}`);

// ── Posts ────────────────────────────────────────────────────────────────────

export const createPost = (content, imageUrl = null) =>
  request('/posts', {
    method: 'POST',
    body: JSON.stringify({ content, image_url: imageUrl }),
  });

// ── Interactions ─────────────────────────────────────────────────────────────

export const logInteraction = (postId, type) =>
  request('/interactions', {
    method: 'POST',
    body: JSON.stringify({ post_id: postId, type }),
  });
