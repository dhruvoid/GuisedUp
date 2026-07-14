/**
 * FeedScreen.js — Guised Up Main Feed Screen
 *
 * Features:
 *   - Personalized "RealConnections" feed (GET /api/feed)
 *   - Infinite scroll — loads next page when user reaches bottom
 *   - Natural language search bar (GET /api/search)
 *   - Seamless transition between feed and search results
 *   - Loading skeleton, empty, and error states
 *   - Pull-to-refresh
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  View,
  Text,
  FlatList,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  StyleSheet,
  SafeAreaView,
  StatusBar,
  RefreshControl,
  Animated,
  Keyboard,
} from 'react-native';
import { getFeed, searchPosts, logInteraction } from '../services/api';
import PostCard from '../components/PostCard';

// ── Design Tokens ─────────────────────────────────────────────────────────────
const COLORS = {
  background: '#0F0F14',
  card: '#1A1A24',
  cardBorder: '#2A2A3A',
  accent: '#7C5CFC',
  accentLight: '#9B7DFF',
  text: '#F0EEF8',
  textSecondary: '#8A88A8',
  inputBg: '#1E1E2E',
  inputBorder: '#3A3A5A',
  white: '#FFFFFF',
  error: '#FC5C7D',
  success: '#5CFC8A',
};

// ── Skeleton Loader ───────────────────────────────────────────────────────────
function SkeletonCard() {
  const opacity = useRef(new Animated.Value(0.3)).current;

  useEffect(() => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(opacity, { toValue: 0.8, duration: 800, useNativeDriver: true }),
        Animated.timing(opacity, { toValue: 0.3, duration: 800, useNativeDriver: true }),
      ])
    ).start();
  }, [opacity]);

  return (
    <Animated.View style={[styles.skeletonCard, { opacity }]}>
      <View style={styles.skeletonHeader}>
        <View style={styles.skeletonAvatar} />
        <View style={styles.skeletonHeaderText}>
          <View style={[styles.skeletonLine, { width: '50%' }]} />
          <View style={[styles.skeletonLine, { width: '30%', marginTop: 6 }]} />
        </View>
      </View>
      <View style={[styles.skeletonLine, { width: '100%', marginBottom: 8 }]} />
      <View style={[styles.skeletonLine, { width: '80%' }]} />
    </Animated.View>
  );
}

// ── Empty State ───────────────────────────────────────────────────────────────
function EmptyState({ message, emoji = '🌿' }) {
  return (
    <View style={styles.emptyContainer}>
      <Text style={styles.emptyEmoji}>{emoji}</Text>
      <Text style={styles.emptyTitle}>Nothing here yet</Text>
      <Text style={styles.emptyMessage}>{message}</Text>
    </View>
  );
}

// ── Error State ───────────────────────────────────────────────────────────────
function ErrorState({ message, onRetry }) {
  return (
    <View style={styles.emptyContainer}>
      <Text style={styles.emptyEmoji}>⚠️</Text>
      <Text style={styles.emptyTitle}>Something went wrong</Text>
      <Text style={styles.emptyMessage}>{message}</Text>
      {onRetry && (
        <TouchableOpacity style={styles.retryButton} onPress={onRetry}>
          <Text style={styles.retryButtonText}>Try again</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

// ── Main Screen ───────────────────────────────────────────────────────────────
export default function FeedScreen() {
  // Feed state
  const [posts, setPosts]             = useState([]);
  const [page, setPage]               = useState(1);
  const [hasMore, setHasMore]         = useState(true);
  const [loading, setLoading]         = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing]   = useState(false);
  const [feedError, setFeedError]     = useState(null);

  // Search state
  const [searchQuery, setSearchQuery]         = useState('');
  const [searchResults, setSearchResults]     = useState([]);
  const [isSearching, setIsSearching]         = useState(false);
  const [searchLoading, setSearchLoading]     = useState(false);
  const [searchError, setSearchError]         = useState(null);

  const searchDebounceRef = useRef(null);
  const flatListRef       = useRef(null);

  // ── Feed loading ──────────────────────────────────────────────────────────

  const loadFeed = useCallback(async (pageNum = 1, append = false) => {
    try {
      if (pageNum === 1) setLoading(true);
      else setLoadingMore(true);
      setFeedError(null);

      const data = await getFeed(pageNum);

      setPosts((prev) => (append ? [...prev, ...data.data] : data.data));
      setHasMore(!!data.next_page_url);
      setPage(pageNum);

      // Log view for newly loaded posts
      data.data.forEach((post) => {
        logInteraction(post.id, 'view').catch(() => {});
      });
    } catch (err) {
      setFeedError(err.message);
    } finally {
      setLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadFeed(1);
  }, [loadFeed]);

  const handleRefresh = useCallback(() => {
    setRefreshing(true);
    setPosts([]);
    setPage(1);
    setHasMore(true);
    loadFeed(1);
  }, [loadFeed]);

  const handleEndReached = useCallback(() => {
    if (!loadingMore && hasMore && !isSearching) {
      loadFeed(page + 1, true);
    }
  }, [loadingMore, hasMore, isSearching, page, loadFeed]);

  // ── Search logic ──────────────────────────────────────────────────────────

  const handleSearchChange = (text) => {
    setSearchQuery(text);

    if (!text.trim()) {
      setIsSearching(false);
      setSearchResults([]);
      setSearchError(null);
      return;
    }

    setIsSearching(true);

    // Debounce — wait 500ms after user stops typing
    clearTimeout(searchDebounceRef.current);
    searchDebounceRef.current = setTimeout(async () => {
      try {
        setSearchLoading(true);
        setSearchError(null);
        const data = await searchPosts(text.trim());
        setSearchResults(data.data);
      } catch (err) {
        setSearchError(err.message);
      } finally {
        setSearchLoading(false);
      }
    }, 500);
  };

  const handleClearSearch = () => {
    Keyboard.dismiss();
    setSearchQuery('');
    setIsSearching(false);
    setSearchResults([]);
    setSearchError(null);
  };

  // ── Render helpers ────────────────────────────────────────────────────────

  const displayData   = isSearching ? searchResults : posts;
  const isInitLoading = loading && posts.length === 0 && !isSearching;

  const renderPost = ({ item }) => (
    <PostCard post={item} onReaction={() => {}} />
  );

  const renderFooter = () => {
    if (loadingMore) {
      return (
        <View style={styles.footerLoader}>
          <ActivityIndicator size="small" color={COLORS.accent} />
        </View>
      );
    }
    if (!hasMore && posts.length > 0 && !isSearching) {
      return (
        <View style={styles.footerEnd}>
          <Text style={styles.footerEndText}>You're all caught up ✨</Text>
        </View>
      );
    }
    return null;
  };

  const renderEmpty = () => {
    if (isInitLoading) return null;

    if (isSearching) {
      if (searchLoading) return <ActivityIndicator color={COLORS.accent} style={{ marginTop: 40 }} />;
      if (searchError)   return <ErrorState message={searchError} />;
      if (!searchResults.length)
        return <EmptyState emoji="🔍" message={`No posts found for "${searchQuery}"`} />;
    } else {
      if (feedError) return <ErrorState message={feedError} onRetry={() => loadFeed(1)} />;
      if (!posts.length) return <EmptyState emoji="🌿" message="The feed is empty. Be the first to post." />;
    }
    return null;
  };

  // ── Header (sticky) ───────────────────────────────────────────────────────

  const listHeader = (
    <View style={styles.headerContainer}>
      {/* App header */}
      <View style={styles.appHeader}>
        <View>
          <Text style={styles.appTitle}>Guised Up</Text>
          <Text style={styles.appSubtitle}>Real people, real connections</Text>
        </View>
        <View style={styles.liveIndicator}>
          <View style={styles.liveDot} />
          <Text style={styles.liveText}>Live</Text>
        </View>
      </View>

      {/* Search bar */}
      <View style={styles.searchContainer}>
        <Text style={styles.searchIcon}>🔍</Text>
        <TextInput
          style={styles.searchInput}
          placeholder="Search posts naturally…"
          placeholderTextColor={COLORS.textSecondary}
          value={searchQuery}
          onChangeText={handleSearchChange}
          returnKeyType="search"
          autoCorrect={false}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
            <Text style={styles.clearButtonText}>✕</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Section label */}
      <Text style={styles.sectionLabel}>
        {isSearching ? `Results for "${searchQuery}"` : '✨ Your Feed'}
      </Text>
    </View>
  );

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.background} />

      {isInitLoading ? (
        <View style={styles.container}>
          {listHeader}
          {[1, 2, 3].map((i) => (
            <SkeletonCard key={i} />
          ))}
        </View>
      ) : (
        <FlatList
          ref={flatListRef}
          data={displayData}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderPost}
          ListHeaderComponent={listHeader}
          ListFooterComponent={renderFooter}
          ListEmptyComponent={renderEmpty}
          onEndReached={handleEndReached}
          onEndReachedThreshold={0.3}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={handleRefresh}
              tintColor={COLORS.accent}
              colors={[COLORS.accent]}
            />
          }
          style={styles.list}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
        />
      )}
    </SafeAreaView>
  );
}

// ── Styles ────────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  list: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  listContent: {
    paddingBottom: 40,
  },

  // ── Header ──────────────────────────────────────────────────────────────
  headerContainer: {
    paddingTop: 8,
    paddingBottom: 8,
  },
  appHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 12,
  },
  appTitle: {
    color: COLORS.text,
    fontSize: 26,
    fontWeight: '800',
    letterSpacing: -0.5,
  },
  appSubtitle: {
    color: COLORS.textSecondary,
    fontSize: 12,
    marginTop: 2,
  },
  liveIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#1E1E2E',
    borderRadius: 20,
    paddingHorizontal: 10,
    paddingVertical: 5,
    gap: 6,
  },
  liveDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: COLORS.success,
  },
  liveText: {
    color: COLORS.success,
    fontSize: 12,
    fontWeight: '700',
  },

  // ── Search ───────────────────────────────────────────────────────────────
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.inputBg,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: COLORS.inputBorder,
    marginHorizontal: 16,
    marginVertical: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    gap: 10,
  },
  searchIcon: {
    fontSize: 16,
  },
  searchInput: {
    flex: 1,
    color: COLORS.text,
    fontSize: 15,
    padding: 0,
  },
  clearButton: {
    padding: 4,
  },
  clearButtonText: {
    color: COLORS.textSecondary,
    fontSize: 13,
    fontWeight: '700',
  },
  sectionLabel: {
    color: COLORS.textSecondary,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    paddingHorizontal: 20,
    paddingVertical: 8,
  },

  // ── Skeleton ─────────────────────────────────────────────────────────────
  skeletonCard: {
    backgroundColor: COLORS.card,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.cardBorder,
    padding: 16,
    marginHorizontal: 16,
    marginBottom: 12,
  },
  skeletonHeader: {
    flexDirection: 'row',
    marginBottom: 14,
  },
  skeletonAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.cardBorder,
    marginRight: 12,
  },
  skeletonHeaderText: {
    flex: 1,
    justifyContent: 'center',
  },
  skeletonLine: {
    height: 12,
    borderRadius: 6,
    backgroundColor: COLORS.cardBorder,
    marginBottom: 4,
  },

  // ── Empty / Error ─────────────────────────────────────────────────────────
  emptyContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 80,
    paddingHorizontal: 40,
  },
  emptyEmoji: {
    fontSize: 48,
    marginBottom: 16,
  },
  emptyTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  emptyMessage: {
    color: COLORS.textSecondary,
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 22,
  },
  retryButton: {
    marginTop: 20,
    backgroundColor: COLORS.accent,
    borderRadius: 12,
    paddingHorizontal: 24,
    paddingVertical: 12,
  },
  retryButtonText: {
    color: COLORS.white,
    fontWeight: '700',
    fontSize: 14,
  },

  // ── Footer ────────────────────────────────────────────────────────────────
  footerLoader: {
    paddingVertical: 20,
    alignItems: 'center',
  },
  footerEnd: {
    paddingVertical: 24,
    alignItems: 'center',
  },
  footerEndText: {
    color: COLORS.textSecondary,
    fontSize: 13,
  },
});
