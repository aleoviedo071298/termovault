import React, { createContext, useEffect, useState } from "react";
import { loginCognito } from "./cognito";
import { tokenManager } from "./TokenManager";

export interface User {
  email: string;
  groups: string[];
}

export interface AuthContextType {
  user: User | null;
  token: string | null;
  loading: boolean;
  login: (email: string, password: string, options?: { session?: string | null; newPassword?: string }) => Promise<{ challenge?: string; session?: string | null } | null>;
  logout: () => void;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
const API_URL = import.meta.env.VITE_API_URL ?? "/api";

type MePayload = {
  groups?: string[];
  local_role?: string | null;
};

function parseJwt(token: string) {
  try {
    const base64Url = token.split(".")[1];
    const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
    const jsonPayload = decodeURIComponent(
      window.atob(base64)
        .split("")
        .map((c) => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2))
        .join("")
    );
    return JSON.parse(jsonPayload);
  } catch (e) {
    return null;
  }
}

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const storedAccessToken = tokenManager.getToken();
    const storedIdToken = tokenManager.getIdToken();

    async function bootstrap() {
      if (!storedAccessToken || !storedIdToken) {
        setLoading(false);
        return;
      }

      const claims = parseJwt(storedIdToken);
      if (claims && claims.exp * 1000 > Date.now()) {
        let groups: string[] = claims["cognito:groups"] ?? [];
        try {
          const res = await fetch(`${API_URL}/auth/me`, {
            headers: {
              Accept: "application/json",
              Authorization: `Bearer ${storedAccessToken}`
            }
          });
          if (res.ok) {
            const me = await res.json() as MePayload;
            groups = me.local_role ? [me.local_role] : (me.groups ?? groups);
          }
        } catch {}

        setToken(storedAccessToken);
        setUser({
          email: claims.email ?? claims["cognito:username"] ?? "",
          groups
        });
      } else {
        // Token expired
        tokenManager.clearToken();
      }
      setLoading(false);
    }

    void bootstrap();
  }, []);

  const login = async (
    email: string,
    password: string,
    options?: { session?: string | null; newPassword?: string }
  ) => {
    setLoading(true);
    try {
      const result = await loginCognito(email, password, options);
      if (result.kind === "challenge") {
        return {
          challenge: result.data.challenge,
          session: result.data.session
        };
      }

      const response = result.data;
      tokenManager.setToken(response.access_token, response.expires_in);
      tokenManager.setIdToken(response.id_token);

      const claims = parseJwt(response.id_token);
      let groups: string[] = claims?.["cognito:groups"] ?? [];
      try {
        const res = await fetch(`${API_URL}/auth/me`, {
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${response.access_token}`
          }
        });

        if (!res.ok) {
          let msg = `No se pudo validar la sesión (${res.status})`;
          try {
            const err = await res.json() as { message?: string };
            if (err?.message) msg = err.message;
          } catch {}
          throw new Error(msg);
        }

        const me = await res.json() as MePayload;
        groups = me.local_role ? [me.local_role] : (me.groups ?? groups);
      } catch (err) {
        throw err instanceof Error ? err : new Error("No se pudo validar la sesión");
      }

      setToken(response.access_token);
      setUser({
        email: claims?.email ?? claims?.["cognito:username"] ?? email,
        groups
      });
      return null;
    } catch (error) {
      logout();
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    tokenManager.clearToken();
    setToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};
