import { AlertCircle, ThermometerSun } from "lucide-react";
import React, { useState } from "react";
import { useAuth } from "../auth/useAuth";

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
      setError("Por favor ingresa tu email y contraseña.");
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
        setError("Debes definir una nueva contraseña para completar el primer ingreso.");
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al iniciar sesión.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="login-frame">
      <div className="login-card animate-fade-in">
        <header className="login-header">
          <div className="brand-mark" aria-hidden="true">
            <ThermometerSun size={28} strokeWidth={1.8} />
          </div>
          <h2>TermoVault</h2>
          <p>Iniciar sesión en la plataforma</p>
        </header>

        {error && (
          <div className="login-error mb-4" role="alert">
            <AlertCircle size={18} aria-hidden="true" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="login-form">
          <div className="form-group">
            <label htmlFor="email">Correo electrónico</label>
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
            <label htmlFor="password">Contraseña temporal/actual</label>
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
            <div className="form-group">
              <label htmlFor="newPassword">Nueva contraseña</label>
              <input
                id="newPassword"
                type="password"
                placeholder="Nueva contraseña"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                disabled={isSubmitting}
                required
              />
            </div>
          ) : null}

          <button type="submit" className="login-button" disabled={isSubmitting}>
            {isSubmitting
              ? "Ingresando..."
              : challengeSession
                ? "Actualizar contraseña e ingresar"
                : "Ingresar"}
          </button>
        </form>
      </div>
    </div>
  );
};

export default Login;

