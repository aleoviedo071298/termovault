import { apiGet } from "./client";

export interface DashboardStats {
  total_informes: number;
  pendientes: number;
  revisados: number;
  fallas_criticas: number;
  tecnicos_activos: number;
  empresas: number;
  yacimientos: number;
  informes_mes: number;
  informes_mes_global: number;
  mis_observados: number;
  mis_aprobados: number;
  informes_con_archivos: number;
  ultimo_informe: string | null;
  contratistas_activas: number;
  elementos_termografiados: number;
}

export interface DashboardReportRow {
  id: number;
  fecha_inspeccion: string;
  estado: string;
  observaciones_revisor: string | null;
  elemento: string;
  tecnico: string;
  empresa: string;
  yacimiento: string;
  criticidad: "Normal" | "Baja" | "Media" | "Alta" | "Crítica";
  hallazgos: number;
}

export interface DashboardTopTecnico {
  id: number;
  nombre: string;
  total: number;
}

export interface DashboardOverview {
  role: "admin" | "supervisor" | "tecnico";
  scope: {
    empresa_id: number | null;
    empresa_nombre: string | null;
    user_id: number | null;
    user_name: string | null;
    is_owner_supervisor: boolean;
    assigned_yacimiento_names: string[];
  };
  stats: DashboardStats;
  top_tecnicos: DashboardTopTecnico[];
  subestaciones_recientes: Array<{ nombre: string; ultima_fecha: string }>;
  informes_criticos_recientes: DashboardReportRow[];
  reports: DashboardReportRow[];
}

export function getDashboardOverview(params?: Record<string, string>): Promise<DashboardOverview> {
  const query = params ? `?${new URLSearchParams(params).toString()}` : "";
  return apiGet<DashboardOverview>(`/dashboard/overview${query}`);
}
