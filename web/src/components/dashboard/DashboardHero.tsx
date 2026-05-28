import { Activity, CalendarDays, Factory, Gauge, MapPin, RadioTower, UserRound } from "lucide-react";
import { ActionToolbar } from "./ActionToolbar";
import { RoleBadge } from "./RoleBadge";
import type { DashboardHeroProps } from "./types";

function formatNow() {
  return new Date().toLocaleString("es-AR", {
    weekday: "short",
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function DashboardHero({
  info,
  stats,
  onPrimaryAction,
  onManageElements,
  onManageUsers,
  onRefresh,
  onLogout,
  canManageElements,
  canManageUsers,
}: DashboardHeroProps) {
  return (
    <section className={`dashboard-hero-v2 role-${info.role}`}>
      <div className="hero-gridline" />
      <div className="hero-identity">
        <div className="hero-kicker">
          <span className="system-dot" />
          {info.eyebrow}
        </div>
        <h1>{info.title}</h1>
        <p>{info.subtitle}</p>

        <div className="hero-meta-row">
          <span><UserRound size={14} /> {info.userName}</span>
          <span><Factory size={14} /> {info.empresaLabel.replace("Empresa: ", "")}</span>
          <span><MapPin size={14} /> {info.yacimientoLabel.replace("Yacimiento: ", "")}</span>
        </div>
      </div>

      <div className="hero-control-panel">
        <div className="control-panel-top">
          <RoleBadge role={info.role} label={info.roleLabel} />
          <span className="control-timestamp"><CalendarDays size={14} /> {formatNow()}</span>
        </div>

        <div className="hero-instruments" aria-label="Resumen operativo">
          <article>
            <RadioTower size={15} />
            <span>Informes visibles</span>
            <strong>{info.reportsCount}</strong>
          </article>
          <article>
            <Gauge size={15} />
            <span>Pendientes</span>
            <strong>{stats?.pendientes ?? 0}</strong>
          </article>
          <article>
            <Activity size={15} />
            <span>Criticos</span>
            <strong>{stats?.fallas_criticas ?? 0}</strong>
          </article>
        </div>

        <ActionToolbar
          onPrimaryAction={onPrimaryAction}
          onManageElements={onManageElements}
          onManageUsers={onManageUsers}
          onRefresh={onRefresh}
          onLogout={onLogout}
          canManageElements={canManageElements}
          canManageUsers={canManageUsers}
        />
      </div>
    </section>
  );
}
