const API_URL = import.meta.env.VITE_API_URL ?? "/api";

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

function normalizeAuthError(message: string): string {
  const text = message.toLowerCase();
  if (text.includes("incorrect") || text.includes("notauthorized") || text.includes("authentication failed")) {
    return "Email o contraseña incorrectos.";
  }
  if (text.includes("usernotfound") || text.includes("user does not exist")) {
    return "No existe un usuario registrado con ese email.";
  }
  if (text.includes("password") && text.includes("reset")) {
    return "Debes restablecer tu contraseña antes de ingresar.";
  }
  if (text.includes("too many") || text.includes("limitexceeded")) {
    return "Demasiados intentos. Espera unos minutos y vuelve a probar.";
  }
  if (text.includes("network") || text.includes("failed to fetch")) {
    return "No se pudo conectar con el servidor. Verifica que la app esté levantada.";
  }
  return message || "No se pudo iniciar sesión. Verifica tus datos.";
}

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
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  if (response.status === 202) {
    const challenge = await response.json() as AuthChallengeResponse;
    return { kind: "challenge", data: challenge };
  }

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({} as { message?: string }));
    throw new Error(normalizeAuthError(errorData.message ?? "Error de autenticacion"));
  }

  const data = await response.json() as AuthResponse;
  return { kind: "success", data };
}
