const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api";

export interface AuthResponse {
  access_token: string;
  id_token: string;
  refresh_token: string | null;
  expires_in: number;
}

export async function loginCognito(email: string, password: string): Promise<AuthResponse> {
  const response = await fetch(`${API_URL}/auth/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json"
    },
    body: JSON.stringify({ email, password })
  });

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.message ?? "Error de autenticación");
  }

  return response.json() as Promise<AuthResponse>;
}
