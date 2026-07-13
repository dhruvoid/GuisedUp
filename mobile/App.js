/**
 * App.js — Guised Up Mobile App Entry Point
 *
 * Renders the FeedScreen as the root screen.
 * In a full app this would include a Navigator with auth flow.
 */

import React, { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator } from 'react-native';
import FeedScreen from './src/screens/FeedScreen';
import { login } from './src/services/api';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [loginError, setLoginError] = useState(null);

  useEffect(() => {
    const autoLogin = async () => {
      try {
        setLoginError(null);
        await login('priya@guisedup.com', 'password123');
        setIsAuthenticated(true);
      } catch (err) {
        console.error('Auto-login failed:', err.message);
        setLoginError(err.message);
      }
    };
    autoLogin();
  }, []);

  if (!isAuthenticated) {
    return (
      <View style={{ flex: 1, backgroundColor: '#0F0F14', justifyContent: 'center', alignItems: 'center', padding: 20 }}>
        {loginError ? (
          <>
            <Text style={{ fontSize: 32, marginBottom: 10 }}>⚠️</Text>
            <Text style={{ color: '#FC5C7D', textAlign: 'center', fontWeight: 'bold' }}>Login Failed</Text>
            <Text style={{ color: '#F0EEF8', textAlign: 'center', marginTop: 10 }}>{loginError}</Text>
          </>
        ) : (
          <>
            <ActivityIndicator size="large" color="#7C5CFC" />
            <Text style={{ color: '#F0EEF8', marginTop: 12 }}>Authenticating test user...</Text>
          </>
        )}
      </View>
    );
  }

  return <FeedScreen />;
}
