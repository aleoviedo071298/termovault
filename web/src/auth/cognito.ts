const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api";

export interface AuthResponse {
  access_token: string;
  id_token: string;
  refresh_token: string | null;
  expires_in: number;
}

export interface AuthChallengeResponse {
  challenge: string;
  session: string | null;
  message: string;
}

export type LoginResult =
  | { kind: "success"; data: AuthResponse }
  | { kind: "challenge"; data: AuthChallengeResponse };

export async function loginCognito(
  email: string,
  password: string,
  options?: { session?: string | null; newPassword?: string }
): Promise<LoginResult> {
  const body: Record<string, string> = { email };
  if (password) body.password = password;
  if (options?.session) body.session = options.session;
  if (options?.newPassword) body.new_password = options.newPassword;

  const response = await fetch(`${API_URL}/auth/login`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json"
    },
    body: JSON.stringify(body)
  });

  if (response.status === 202) {
    const challenge = await response.json() as AuthChallengeResponse;
    return { kind: "challenge", data: challenge };
  }

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.message ?? "Error de autenticación");
  }

  const data = await response.json() as AuthResponse;
  return { kind: "success", data };
}

