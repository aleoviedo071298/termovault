import React from "react";
import { useAuth } from "./useAuth";

interface ProtectedRouteProps {
  children: React.ReactNode;
  allowedGroups?: string[];
  fallback: React.ReactNode;
}

export const ProtectedRoute: React.FC<ProtectedRouteProps> = ({
  children,
  allowedGroups,
  fallback
}) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="app-shell flex items-center justify-center min-h-screen text-slate-400">
        <div className="text-center">
          <p className="text-lg font-medium">Cargando sesión...</p>
        </div>
      </div>
    );
  }

  if (!user) {
    return <>{fallback}</>;
  }

  if (allowedGroups && allowedGroups.length > 0) {
    const hasGroup = user.groups.some((group) =>
      allowedGroups.map(g => g.toLowerCase()).includes(group.toLowerCase())
    );
    if (!hasGroup) {
      return (
        <div className="app-shell flex flex-col items-center justify-center min-h-screen text-slate-400 p-6 text-center">
          <h2 className="text-2xl font-bold text-rose-500 mb-2">Acceso Denegado</h2>
          <p className="max-w-md">No tienes los permisos de rol requeridos para ver esta sección. Tus roles: {user.groups.join(", ") || "ninguno"}.</p>
        </div>
      );
    }
  }

  return <>{children}</>;
};
