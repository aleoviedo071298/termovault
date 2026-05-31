import type { DashboardOverview, DashboardReportRow } from "../../api/dashboard";

export type DashboardRole = "admin" | "supervisor-owner" | "supervisor-contratista" | "tecnico";

export interface DashboardFiltersState {
  estado: string;
  q: string;
  fecha_desde: string;
  fecha_hasta: string;
  criticidad: string;
}

export interface DashboardHeaderInfo {
  role: DashboardRole;
  roleLabel: string;
  title: string;
  subtitle: string;
  eyebrow: string;
  userName: string;
  userEmail: string;
  empresaLabel: string;
  yacimientoLabel: string;
  reportsCount: number;
}

export interface KpiCardData {
  label: string;
  value: string | number;
  tone: "operativo" | "critico" | "global";
  trend: string;
}

export interface ReportTableProps {
  loading: boolean;
  reports: DashboardReportRow[];
  onOpenDetail: (id: number) => void;
}

export interface FilterBarProps {
  filters: DashboardFiltersState;
  onChange: (next: Partial<DashboardFiltersState>) => void;
  onClear: () => void;
  filteredCount: number;
  totalCount: number;
}

export interface RoleHeroProps {
  info: DashboardHeaderInfo;
  stats: DashboardOverview["stats"] | null;
  onPrimaryAction: () => void;
  onManageElements: () => void;
  onManageUsers: () => void;
  onRefresh: () => void;
  onLogout: () => void;
  canManageElements: boolean;
  canManageUsers: boolean;
}

export type DashboardHeroProps = RoleHeroProps;
