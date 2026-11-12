"use client";

import { useQueryClient } from "@tanstack/react-query";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { apiFetch, ApiError } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type { User } from "@/lib/api/types";
import { buildDevToken, findDevAccount } from "@/lib/auth/dev-token";
import { AUTH_DEV_MODE } from "@/lib/config";
import { getFirebaseAuth } from "@/lib/firebase/client";

const TOKEN_KEY = "zentro.token";
const USER_KEY = "zentro.user";
const COOKIE = "zentro_auth";

interface AuthContextValue {
  user: User | null;
  token: string | null;
  loading: boolean;
  isDev: boolean;
  isSeller: boolean;
  loginDev: (uid: string) => Promise<void>;
  loginFirebase: (email: string, password: string) => Promise<void>;
  registerFirebase: (name: string, email: string, password: string) => Promise<void>;
  resetPassword: (email: string) => Promise<void>;
  logout: () => void;
  refresh: () => Promise<void>;
  can: (permission: string) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function setCookie() {
  document.cookie = `${COOKIE}=1; path=/; max-age=86400; samesite=lax`;
}

function clearCookie() {
  document.cookie = `${COOKIE}=; path=/; max-age=0; samesite=lax`;
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const queryClient = useQueryClient();

  const persist = useCallback((nextUser: User, nextToken: string) => {
    sessionStorage.setItem(TOKEN_KEY, nextToken);
    sessionStorage.setItem(USER_KEY, JSON.stringify(nextUser));
    setCookie();
    setToken(nextToken);
    setUser(nextUser);
  }, []);

  const clear = useCallback(() => {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    clearCookie();
    setToken(null);
    setUser(null);
    queryClient.clear();
  }, [queryClient]);

  const refreshWith = useCallback(
    async (activeToken: string) => {
      try {
        const me = await apiFetch<User>(endpoints.auth.me, { token: activeToken });

        // Sesi bisa sudah dihapus (logout) saat request ini berjalan; jangan
        // hidupkan kembali user yang sudah keluar.
        if (sessionStorage.getItem(TOKEN_KEY) !== activeToken) {
          return;
        }

        setUser(me);
        sessionStorage.setItem(USER_KEY, JSON.stringify(me));
        setCookie();
      } catch (error) {
        // Hanya sesi yang benar-benar tidak valid yang dihapus.
        // Gangguan jaringan/5xx tidak boleh memaksa logout.
        if (error instanceof ApiError && error.status === 401) {
          clear();
        }
      }
    },
    [clear],
  );

  useEffect(() => {
    const storedToken = sessionStorage.getItem(TOKEN_KEY);
    const storedUser = sessionStorage.getItem(USER_KEY);

    if (storedToken && storedUser) {
      try {
        // Hydration sesi dari sessionStorage (sumber eksternal).
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setToken(storedToken);
        setUser(JSON.parse(storedUser) as User);
        void refreshWith(storedToken);
      } catch {
        clear();
      }
    } else {
      // Tidak ada sesi: buang cookie penanda yang mungkin tertinggal agar
      // guard route tidak membenturkan pengguna ke halaman lain.
      clearCookie();
    }

    setLoading(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const sync = useCallback(
    async (idToken: string) => {
      const nextUser = await apiFetch<User>(endpoints.auth.sync, {
        method: "POST",
        json: { id_token: idToken },
      });

      persist(nextUser, idToken);
    },
    [persist],
  );

  const loginDev = useCallback(
    async (uid: string) => {
      if (!AUTH_DEV_MODE) throw new Error("Mode demo tidak aktif.");
      await sync(buildDevToken(findDevAccount(uid)));
    },
    [sync],
  );

  const loginFirebase = useCallback(
    async (email: string, password: string) => {
      const auth = await getFirebaseAuth();

      if (!auth) throw new Error("Firebase belum dikonfigurasi.");

      const { signInWithEmailAndPassword } = await import("firebase/auth");
      const credential = await signInWithEmailAndPassword(auth, email, password);
      const idToken = await credential.user.getIdToken();

      await sync(idToken);
    },
    [sync],
  );

  const registerFirebase = useCallback(
    async (name: string, email: string, password: string) => {
      const auth = await getFirebaseAuth();

      if (!auth) throw new Error("Firebase belum dikonfigurasi.");

      const { createUserWithEmailAndPassword, updateProfile } = await import(
        "firebase/auth"
      );
      const credential = await createUserWithEmailAndPassword(auth, email, password);

      if (name) await updateProfile(credential.user, { displayName: name });

      const idToken = await credential.user.getIdToken();

      await sync(idToken);
    },
    [sync],
  );

  const resetPassword = useCallback(async (email: string) => {
    const auth = await getFirebaseAuth();

    if (!auth) throw new Error("Firebase belum dikonfigurasi.");

    const { sendPasswordResetEmail } = await import("firebase/auth");
    await sendPasswordResetEmail(auth, email);
  }, []);

  const logout = useCallback(() => {
    void (async () => {
      const auth = await getFirebaseAuth();

      if (auth) {
        const { signOut } = await import("firebase/auth");
        await signOut(auth).catch(() => undefined);
      }

      clear();
    })();
  }, [clear]);

  const refresh = useCallback(async () => {
    if (token) await refreshWith(token);
  }, [token, refreshWith]);

  const can = useCallback(
    (permission: string) => {
      if (!user) return false;
      if (user.roles.includes("super_admin")) return true;

      return user.permissions.includes(permission);
    },
    [user],
  );

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      loading,
      isDev: AUTH_DEV_MODE,
      isSeller: Boolean(user?.roles.includes("seller")),
      loginDev,
      loginFirebase,
      registerFirebase,
      resetPassword,
      logout,
      refresh,
      can,
    }),
    [
      user,
      token,
      loading,
      loginDev,
      loginFirebase,
      registerFirebase,
      resetPassword,
      logout,
      refresh,
      can,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) throw new Error("useAuth harus dipakai di dalam AuthProvider");

  return context;
}
