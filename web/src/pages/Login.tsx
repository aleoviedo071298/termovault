import { AlertCircle, ArrowRight } from "lucide-react";
import React, { useState } from "react";
import { useAuth } from "../auth/useAuth";
import { Field, Input } from "../components/ui/Field";
import { Button } from "../components/ui/Button";
import { Checkbox } from "../components/ui/Field";

// ─────────────────────────────────────────────────────────────────────────
// LÓGICA DE NEGOCIO INTACTA — NO MODIFICAR
// ─────────────────────────────────────────────────────────────────────────
// Esta función y los handlers asociados (handleSubmit, useState, login)
// se mantienen exactamente como estaban antes del rediseño visual.

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
  const [remember, setRemember] = useState(true);

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
    <main className="tv-login">
      {/* ─── Panel verde izquierdo (branding) ─── */}
      <aside className="tv-login__panel">
        <div className="tv-login__brand">
          <div className="tv-login__brand-mark" aria-hidden="true">TV</div>
          <div className="tv-login__brand-text">
            <span className="tv-login__brand-name">TermoVault</span>
            <span className="tv-login__brand-sub">Inspecciones termográficas</span>
          </div>
        </div>

        <div className="tv-login__headline">
          <h1 className="tv-login__title">Gestión de informes termográficos</h1>
          <p className="tv-login__lead">
            Acceso al sistema para el registro y revisión de reportes técnicos de termografía.
          </p>
        </div>

        <p className="tv-login__footnote">
          Plataforma segura y confiable para la gestión de inspecciones termográficas.
        </p>
      </aside>

      {/* ─── Panel derecho (formulario) ─── */}
      <section className="tv-login__form-side">
        <div className="tv-login__form-wrap">
          <header>
            <div className="tv-login__eyebrow">
              {challengeSession ? "Primer ingreso" : "Acceso"}
            </div>
            <h2 className="tv-login__h2">
              {challengeSession ? "Establecer nueva contraseña" : "Iniciar Sesión"}
            </h2>
            <p className="tv-login__sub">
              {challengeSession
                ? "Cognito requiere actualizar la contraseña inicial para activar tu acceso."
                : "Usa tus credenciales asignadas para continuar."}
            </p>
          </header>

          {error && (
            <div className="tv-login__alert" role="alert">
              <AlertCircle size={16} aria-hidden="true" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="tv-login__form">
            <Field label="Correo electrónico" required>
              {(id) => (
                <Input
                  id={id}
                  type="email"
                  placeholder="nombre@empresa.com"
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={isSubmitting}
                  required
                />
              )}
            </Field>

            <Field label="Contraseña" required>
              {(id) => (
                <Input
                  id={id}
                  type="password"
                  placeholder="••••••••"
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  disabled={isSubmitting}
                  required
                />
              )}
            </Field>

            {challengeSession && (
              <div className="tv-login__challenge">
                <div>
                  <div className="tv-login__challenge-title">Validación requerida</div>
                  <p className="tv-login__challenge-desc">
                    Definí una contraseña permanente. Debe tener al menos 8 caracteres.
                  </p>
                </div>
                <Field label="Nueva contraseña" required>
                  {(id) => (
                    <Input
                      id={id}
                      type="password"
                      placeholder="Mínimo 8 caracteres"
                      autoComplete="new-password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      disabled={isSubmitting}
                      required
                    />
                  )}
                </Field>
              </div>
            )}

            {!challengeSession && (
              <div className="tv-login__row">
                <Checkbox
                  label="Recordarme"
                  checked={remember}
                  onChange={(e) => setRemember(e.target.checked)}
                />
                {/* Link visual — la funcionalidad de recuperación no está
                    implementada todavía en backend; queda como anchor sin
                    impacto funcional. */}
                <span className="tv-login__link" aria-disabled="true">¿Olvidaste tu contraseña?</span>
              </div>
            )}

            <Button
              type="submit"
              variant="primary"
              full
              disabled={isSubmitting}
              rightIcon={<ArrowRight size={16} />}
            >
              {isSubmitting
                ? "Iniciando sesión..."
                : challengeSession
                  ? "Establecer contraseña"
                  : "Ingresar"}
            </Button>
          </form>
        </div>
      </section>
    </main>
  );
};

export default Login;
