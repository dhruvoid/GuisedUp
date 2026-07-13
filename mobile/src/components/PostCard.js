/**
 * PostCard.js — Guised Up Post Card Component
 *
 * Displays a single post with:
 *   - Avatar placeholder (initials-based)
 *   - Username and time ago
 *   - Post content
 *   - Optional image
 *   - Reaction button with animated press feedback
 */

import React, { useState, useRef } from 'react';
import {
  View,
  Text,
  Image,
  TouchableOpacity,
  StyleSheet,
  Animated,
} from 'react-native';
import { logInteraction } from '../services/api';

const COLORS = {
  background: '#0F0F14',
  card: '#1A1A24',
  cardBorder: '#2A2A3A',
  accent: '#7C5CFC',
  accentLight: '#9B7DFF',
  text: '#F0EEF8',
  textSecondary: '#8A88A8',
  reaction: '#FF6B9D',
  reactionBg: '#2A1A24',
  white: '#FFFFFF',
};

// Generate a deterministic color from a name string
function nameToColor(name = '') {
  const colors = ['#7C5CFC', '#FC5C7D', '#5CF0FC', '#FCC05C', '#5CFC8A'];
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return colors[Math.abs(hash) % colors.length];
}

function Avatar({ name, avatarUrl, size = 44 }) {
  const initials = name
    ? name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2)
    : '?';
  const color = nameToColor(name);

  if (avatarUrl) {
    return (
      <Image
        source={{ uri: avatarUrl }}
        style={[styles.avatar, { width: size, height: size, borderRadius: size / 2 }]}
      />
    );
  }

  return (
    <View
      style={[
        styles.avatar,
        {
          width: size,
          height: size,
          borderRadius: size / 2,
          backgroundColor: color,
          alignItems: 'center',
          justifyContent: 'center',
        },
      ]}
    >
      <Text style={[styles.avatarText, { fontSize: size * 0.35 }]}>{initials}</Text>
    </View>
  );
}

export default function PostCard({ post, onReaction }) {
  const [reacted, setReacted]         = useState(false);
  const [reactionCount, setReactionCount] = useState(post.reaction_count ?? 0);
  const [loading, setLoading]         = useState(false);
  const scaleAnim = useRef(new Animated.Value(1)).current;

  const handleReaction = async () => {
    if (loading) return;

    // Optimistic UI update
    const next = !reacted;
    setReacted(next);
    setReactionCount((c) => c + (next ? 1 : -1));

    // Bounce animation
    Animated.sequence([
      Animated.spring(scaleAnim, { toValue: 1.4, useNativeDriver: true, speed: 40 }),
      Animated.spring(scaleAnim, { toValue: 1, useNativeDriver: true, speed: 20 }),
    ]).start();

    try {
      setLoading(true);
      await logInteraction(post.id, 'reaction');
      onReaction?.(post.id);
    } catch {
      // Revert on failure
      setReacted(!next);
      setReactionCount((c) => c + (next ? -1 : 1));
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.card}>
      {/* Header */}
      <View style={styles.header}>
        <Avatar name={post.author?.name} avatarUrl={post.author?.avatar_url} />
        <View style={styles.headerInfo}>
          <Text style={styles.authorName}>{post.author?.name ?? 'Unknown'}</Text>
          <Text style={styles.timeAgo}>{post.time_ago}</Text>
        </View>
        {/* Authenticity indicator dot */}
        <View
          style={[
            styles.authenticityDot,
            {
              backgroundColor:
                (post.authenticity_score ?? 0) > 0.65
                  ? '#5CFC8A'
                  : (post.authenticity_score ?? 0) > 0.4
                  ? '#FCC05C'
                  : '#FC5C7D',
            },
          ]}
        />
      </View>

      {/* Content */}
      <Text style={styles.content}>{post.content}</Text>

      {/* Optional Image */}
      {post.image_url ? (
        <Image
          source={{ uri: post.image_url }}
          style={styles.postImage}
          resizeMode="cover"
        />
      ) : null}

      {/* Footer */}
      <View style={styles.footer}>
        {/* Reaction button */}
        <TouchableOpacity
          style={[styles.reactionButton, reacted && styles.reactionButtonActive]}
          onPress={handleReaction}
          activeOpacity={0.8}
        >
          <Animated.Text
            style={[styles.reactionEmoji, { transform: [{ scale: scaleAnim }] }]}
          >
            {reacted ? '❤️' : '🤍'}
          </Animated.Text>
          <Text style={[styles.reactionCount, reacted && styles.reactionCountActive]}>
            {reactionCount}
          </Text>
        </TouchableOpacity>

        {/* View count */}
        <Text style={styles.viewCount}>
          👁 {post.view_count ?? 0}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.card,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.cardBorder,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 12,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  avatar: {
    marginRight: 12,
  },
  avatarText: {
    color: COLORS.white,
    fontWeight: '700',
  },
  headerInfo: {
    flex: 1,
  },
  authorName: {
    color: COLORS.text,
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  timeAgo: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
  authenticityDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  content: {
    color: COLORS.text,
    fontSize: 15,
    lineHeight: 22,
    marginBottom: 12,
  },
  postImage: {
    width: '100%',
    height: 220,
    borderRadius: 12,
    marginBottom: 12,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  reactionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#1E1E2E',
    borderRadius: 20,
    paddingVertical: 6,
    paddingHorizontal: 14,
    gap: 6,
  },
  reactionButtonActive: {
    backgroundColor: COLORS.reactionBg,
  },
  reactionEmoji: {
    fontSize: 16,
  },
  reactionCount: {
    color: COLORS.textSecondary,
    fontSize: 13,
    fontWeight: '600',
  },
  reactionCountActive: {
    color: COLORS.reaction,
  },
  viewCount: {
    color: COLORS.textSecondary,
    fontSize: 13,
  },
});
