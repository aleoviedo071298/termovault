import React, { createContext, useEffect, useState } from "react";
import { loginCognito } from "./cognito";

export interface User {
  email: string;
  groups: string[];
}

export interface AuthContextType {
  user: User | null;
  token: string | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

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
    const storedAccessToken = localStorage.getItem("access_token");
    const storedIdToken = localStorage.getItem("id_token");

    if (storedAccessToken && storedIdToken) {
      const claims = parseJwt(storedIdToken);
      if (claims && claims.exp * 1000 > Date.now()) {
        setToken(storedAccessToken);
        setUser({
          email: claims.email ?? claims["cognito:username"] ?? "",
          groups: claims["cognito:groups"] ?? []
        });
      } else {
        // Token expired
        localStorage.removeItem("access_token");
        localStorage.removeItem("id_token");
      }
    }
    setLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    setLoading(true);
    try {
      const response = await loginCognito(email, password);
      localStorage.setItem("access_token", response.access_token);
      localStorage.setItem("id_token", response.id_token);

      const claims = parseJwt(response.id_token);
      setToken(response.access_token);
      setUser({
        email: claims?.email ?? claims?.["cognito:username"] ?? email,
        groups: claims?.["cognito:groups"] ?? []
      });
    } catch (error) {
      logout();
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const logout = () => {
    localStorage.removeItem("access_token");
    localStorage.removeItem("id_token");
    setToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};
