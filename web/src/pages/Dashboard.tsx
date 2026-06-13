import { AlertCircle, Plus, RefreshCw } from "lucide-react";
import React, { useEffect, useMemo, useState } from "react";
import { getDashboardOverview, type DashboardOverview } from "../api/dashboard";
import { useAuth } from "../auth/useAuth";
import { FilterBar } from "../components/dashboard/FilterBar";
import { KPIGrid } from "../components/dashboard/KPIGrid";
import { ReportTable } from "../components/dashboard/ReportTable";
import type { DashboardFiltersState, DashboardRole, KpiCardData } from "../components/dashboard/types";
import { InspectionDetailModal } from "../components/InspectionDetailModal";
import { InspectionModal } from "../components/InspectionModal";
import { Button } from "../components/ui/Button";
import { Badge } from "../components/ui/Badge";

interface Props {
  onOpenElementosGestion: () => void;
  onOpenAdminUsuarios: () => void;
}

function resolveRole(groups: string[], isOwnerSupervisor: boolean): DashboardRole {
  if (groups.includes("admin")) return "admin";
  if (groups.includes("supervisor")) return isOwnerSupervisor ? "supervisor-owner" : "supervisor-contratista";
  return "tecnico";
}

function isDashboardRole(value: string | null): value is DashboardRole {
  return value === "admin" || value === "supervisor-owner" || value === "supervisor-contratista" || value === "tecnico";
}

function normalizeCriticidad(value: string): string {
  return value.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
}

export default function Dashboard({ onOpenElementosGestion, onOpenAdminUsuarios }: Props) {
  const { user, logout } = useAuth();
  const groups = user?.groups ?? [];

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<DashboardOverview | null>(null);
  const [filters, setFilters] = useState<DashboardFiltersState>({
    estado: "",
    q: "",
    fecha_desde: "",
    fecha_hasta: "",
    criticidad: "",
  });

  const [inspectionModalOpen, setInspectionModalOpen] = useState(false);
  const [inspectionElementId, setInspectionElementId] = useState<number | null>(null);
  const [inspectionDetailOpen, setInspectionDetailOpen] = useState(false);
  const [inspectionDetailId, setInspectionDetailId] = useState<number | null>(null);
  const roleCacheKey = user?.email ? `termovault:last-dashboard-role:${user.email}` : null;
  const [cachedRole, setCachedRole] = useState<DashboardRole | null>(() => {
    const cached = user?.email ? window.localStorage.getItem(`termovault:last-dashboard-role:${user.email}`) : null;
    return isDashboardRole(cached) ? cached : null;
  });

  const resolvedRole = data ? resolveRole(groups, Boolean(data.scope.is_owner_supervisor)) : null;
  const role = resolvedRole ?? cachedRole ?? resolveRole(groups, false);
  const canManageElements = role === "admin" || role === "supervisor-owner";
  const canManageUsers = role === "admin";
  const canReviewReports = role === "admin" || role === "supervisor-owner";

  async function loadDashboard() {
    try {
      setLoading(true);
      setError(null);
      const params: Record<string, string> = {};
      if (filters.estado) params.estado = filters.estado;
      if (filters.fecha_desde) params.fecha_desde = filters.fecha_desde;
      if (filters.fecha_hasta) params.fecha_hasta = filters.fecha_hasta;
      setData(await getDashboardOverview(params));
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo cargar el dashboard.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadDashboard();
  }, [filters.estado, filters.fecha_desde, filters.fecha_hasta]);

  useEffect(() => {
    if (!resolvedRole || !roleCacheKey) return;
    setCachedRole(resolvedRole);
    window.localStorage.setItem(roleCacheKey, resolvedRole);
  }, [resolvedRole, roleCacheKey]);

  const roleLabel = role === "admin"
    ? "Panel Administrador"
    : role === "supervisor-owner"
      ? `Panel Supervisor${data?.scope.assigned_yacimiento_names?.length ? `: ${data.scope.assigned_yacimiento_names[0]}` : ""}`
      : role === "supervisor-contratista"
        ? "Panel Supervisor Contratista"
        : "Panel Tecnico";

  const roleText = {
    admin: {
      eyebrow: "Administración",
      title: "Panel general",
      subtitle: "Informes, criticidad, usuarios y cobertura en un solo lugar.",
    },
    "supervisor-owner": {
      eyebrow: "Supervisión",
      title: "Panel de yacimiento",
      subtitle: "Informes recibidos, pendientes de revisión y novedades a seguir.",
    },
    "supervisor-contratista": {
      eyebrow: "Contratista",
      title: "Panel de cuadrillas",
      subtitle: "Actividad de tus técnicos, cobertura del mes y documentación.",
    },
    tecnico: {
      eyebrow: "Técnico",
      title: "Mis inspecciones",
      subtitle: "Registrá termografías y seguí el estado de tus informes.",
    },
  } satisfies Record<DashboardRole, { eyebrow: string; title: string; subtitle: string }>;

  const cards = useMemo<KpiCardData[]>(() => {
    if (!data) return [];
    if (role === "tecnico") {
      return [
        { label: "Total enviados", value: data.stats.total_informes, tone: "operativo", trend: "Historico personal" },
        { label: "Enviados este mes", value: data.stats.informes_mes, tone: "operativo", trend: "Produccion mensual" },
        { label: "Con observaciones", value: data.stats.mis_observados, tone: "critico", trend: "Requieren seguimiento" },
        { label: "Con adjuntos", value: data.stats.informes_con_archivos, tone: "global", trend: "Trazabilidad documental" },
      ];
    }
    if (role === "supervisor-contratista") {
      return [
        { label: "Informes recibidos", value: data.stats.total_informes, tone: "global", trend: "Alcance contratista" },
        { label: "Informes del mes", value: data.stats.informes_mes_global, tone: "operativo", trend: "Actividad mensual" },
        { label: "Tecnicos activos", value: data.stats.tecnicos_activos, tone: "global", trend: "Dotacion con actividad" },
        { label: "Elementos termografiados", value: data.stats.elementos_termografiados, tone: "operativo", trend: "Cobertura operativa" },
      ];
    }
    if (role === "supervisor-owner") {
      return [
        { label: "Informes recibidos", value: data.stats.total_informes, tone: "global", trend: "Vista de yacimiento" },
        { label: "Pendientes", value: data.stats.pendientes, tone: "critico", trend: "Cola de revision" },
        { label: "Contratistas activas", value: data.stats.contratistas_activas, tone: "global", trend: "Operacion multiproveedor" },
        { label: "Criticos detectados", value: data.stats.fallas_criticas, tone: "critico", trend: "Eventos de alto riesgo" },
      ];
    }
    return [
      { label: "Total informes", value: data.stats.total_informes, tone: "global", trend: "Consolidado plataforma" },
      { label: "Informes del mes", value: data.stats.informes_mes_global, tone: "operativo", trend: "Ritmo mensual" },
      { label: "Tecnicos activos", value: data.stats.tecnicos_activos, tone: "global", trend: "Usuarios operativos" },
      { label: "Fallas criticas", value: data.stats.fallas_criticas, tone: "critico", trend: "Prioridad de gestion" },
    ];
  }, [data, role]);

  const filteredReports = useMemo(() => {
    if (!data) return [];
    const q = filters.q.trim().toLowerCase();
    return data.reports.filter((r) => {
      const matchSearch = q === "" || [r.elemento, r.tecnico, r.empresa, r.yacimiento, r.estado].some((v) => v.toLowerCase().includes(q));
      const matchCriticidad = filters.criticidad === "" || normalizeCriticidad(r.criticidad) === normalizeCriticidad(filters.criticidad);
      return matchSearch && matchCriticidad;
    });
  }, [data, filters.q, filters.criticidad]);

  // Datos descriptivos para el header de la página (no se persisten ni se mandan
  // a backend; son solo etiquetas visuales derivadas del scope ya cargado).
  const empresaText = data?.scope.empresa_nombre ?? "Sin empresa";
  const yacimientoText = data?.scope.assigned_yacimiento_names?.length
    ? data.scope.assigned_yacimiento_names.join(", ")
    : "Según alcance";

  return (
    <>
      {/* ─── Header de página ─────────────────────────────────────────── */}
      <div className="tv-page-head">
        <div className="tv-page-head__left">
          <span className="tv-page-head__eyebrow">{roleText[role].eyebrow}</span>
          <h1 className="tv-page-head__title">{roleText[role].title}</h1>
          <p className="tv-page-head__subtitle">{roleText[role].subtitle}</p>
          <div className="tv-page-head__chips">
            <Badge tone="neutral" variant="outline">{empresaText}</Badge>
            <Badge tone="neutral" variant="outline">{yacimientoText}</Badge>
          </div>
        </div>
        <div className="tv-page-head__actions">
          <Button
            variant="secondary"
            size="sm"
            leftIcon={<RefreshCw size={14} strokeWidth={2} />}
            onClick={() => void loadDashboard()}
            disabled={loading}
            aria-label="Refrescar"
          >
            Refrescar
          </Button>
          <Button
            variant="primary"
            size="md"
            leftIcon={<Plus size={15} strokeWidth={2.2} />}
            onClick={() => {
              setInspectionElementId(null);
              setInspectionModalOpen(true);
            }}
          >
            Registrar nueva termografía
          </Button>
        </div>
      </div>

      {error && (
        <div className="tv-notice tv-notice--danger" role="alert">
          <AlertCircle size={16} />
          <span>{error}</span>
        </div>
      )}

      <KPIGrid cards={cards} />

      <FilterBar
        filters={filters}
        onChange={(next) => setFilters((prev) => ({ ...prev, ...next }))}
        onClear={() => setFilters({ estado: "", q: "", fecha_desde: "", fecha_hasta: "", criticidad: "" })}
        filteredCount={filteredReports.length}
        totalCount={data?.reports.length ?? 0}
      />

      <ReportTable
        loading={loading}
        reports={filteredReports}
        onOpenDetail={(id) => {
          setInspectionDetailId(id);
          setInspectionDetailOpen(true);
        }}
      />

      <InspectionModal
        isOpen={inspectionModalOpen}
        onClose={() => {
          setInspectionModalOpen(false);
          setInspectionElementId(null);
        }}
        preSelectedElementId={inspectionElementId}
        onSuccess={() => {
          void loadDashboard();
          setInspectionModalOpen(false);
          setInspectionElementId(null);
        }}
      />

      <InspectionDetailModal
        isOpen={inspectionDetailOpen}
        inspeccionId={inspectionDetailId}
        onClose={() => setInspectionDetailOpen(false)}
        userGroups={groups}
        canReviewOverride={canReviewReports}
        onStatusChanged={() => void loadDashboard()}
      />
    </>
  );
}
