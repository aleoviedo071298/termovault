import { HardHat, ShieldCheck, UserCog, Wrench } from "lucide-react";
import type { DashboardRole } from "./types";

interface RoleBadgeProps {
  role: DashboardRole;
  label: string;
}

export function RoleBadge({ role, label }: RoleBadgeProps) {
  const Icon = role === "admin"
    ? ShieldCheck
    : role === "tecnico"
      ? Wrench
      : role === "supervisor-owner"
        ? HardHat
        : UserCog;

  return (
    <span className={`role-badge role-badge-${role}`}>
      <Icon size={15} />
      {label}
    </span>
  );
}
