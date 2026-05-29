import { AlertCircle, ArrowRight, LockKeyhole, RadioTower, ShieldCheck, ThermometerSun } from "lucide-react";
import React, { useState } from "react";
import { useAuth } from "../auth/useAuth";

function normalizeLoginMessage(message: string): string {
  const text = message.toLowerCase();
  if (text.includes("incorrect") || text.includes("notauthorized") || text.includes("authentication failed")) {
    return "Email o contraseña incorrectos.";
  }
  if (text.includes("password is required")) {
    return "Ingresa tu contraseña para continuar.";
  }
  if (text.includes("network") || text.includes("failed to fetch")) {
    return "No se pudo conectar con el servidor local.";
  }
  return message;
}

export const Login: React.FC = () => {
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [challengeSession, setChallengeSession] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email || !password) {
      setError("Ingresa tu email y contraseña para continuar.");
      return;
    }
    if (challengeSession && newPassword.trim().length < 8) {
      setError("La nueva contraseña debe tener al menos 8 caracteres.");
      return;
    }

    setError(null);
    setIsSubmitting(true);

    try {
      const challenge = await login(
        email,
        password,
        challengeSession ? { session: challengeSession, newPassword } : undefined
      );

      if (challenge?.challenge === "NEW_PASSWORD_REQUIRED") {
        setChallengeSession(challenge.session ?? null);
        setError(null);
      }
    } catch (err) {
      setError(normalizeLoginMessage(err instanceof Error ? err.message : "Error al iniciar sesión."));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="login-frame login-frame-v2">
      <section className="login-command-surface">
        <div className="login-intel-panel">
          <div className="login-brand-lockup">
            <div className="brand-mark" aria-hidden="true">
              <ThermometerSun size={28} strokeWidth={1.8} />
            </div>
            <div>
              <span>TermoVault</span>
              <strong>Inspecciones Termográficas</strong>
            </div>
          </div>

          <div className="login-headline">
            <h1>Gestión de informes termográficos</h1>
            <p>Acceso al sistema para el registro y revisión de reportes técnicos de termografía.</p>
          </div>
        </div>

        <div className={`login-card login-card-v2 ${challengeSession ? "is-challenge" : ""}`}>
          <header className="login-header">
            <div>
              <p>{challengeSession ? "Primer ingreso" : "Acceso"}</p>
              <h2>{challengeSession ? "Establecer nueva contraseña" : "Iniciar Sesión"}</h2>
              <span>
                {challengeSession
                  ? "Cognito requiere actualizar la contraseña inicial para activar tu acceso."
                  : "Usa tus credenciales asignadas para continuar."}
              </span>
            </div>
          </header>

          {error && (
            <div className="login-error" role="alert">
              <AlertCircle size={18} aria-hidden="true" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="login-form">
            <div className="form-group">
              <label htmlFor="email">Correo electronico</label>
              <input
                id="email"
                type="email"
                placeholder="nombre@empresa.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={isSubmitting}
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="password">Contraseña</label>
              <input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={isSubmitting}
                required
              />
            </div>

            {challengeSession ? (
              <section className="password-challenge-panel">
                <div>
                  <strong>Validacion requerida</strong>
                  <p>Defini una contraseña permanente. Debe tener al menos 8 caracteres.</p>
                </div>
                <div className="form-group">
                  <label htmlFor="newPassword">Nueva contraseña</label>
                  <input
                    id="newPassword"
                    type="password"
                    placeholder="Minimo 8 caracteres"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    disabled={isSubmitting}
                    required
                  />
                </div>
              </section>
            ) : null}

            <button type="submit" className="login-button login-button-v2" disabled={isSubmitting}>
              {isSubmitting
                ? "Iniciando sesión..."
                : challengeSession
                  ? "Establecer contraseña"
                  : "Ingresar"}
              <ArrowRight size={17} />
            </button>
          </form>
        </div>
      </section>
    </main>
  );
};

export default Login;
