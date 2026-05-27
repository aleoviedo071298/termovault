import { apiGet, apiPost, apiPut } from "./client";

export interface AdminUsuarioMeta {
  roles: Array<{ id: number; codigo: "admin" | "supervisor" | "tecnico"; nombre: string }>;
  empresas: Array<{ id: number; nombre: string }>;
  yacimientos: Array<{ id: number; nombre: string; codigo: string; empresa_id: number }>;
}

export interface AdminUsuario {
  id: number;
  nombre: string;
  apellido: string;
  email: string;
  activo: boolean;
  empresa_id?: number;
  empresa: string | null;
  rol: string | null;
  yacimientos: Array<{ id: number; nombre: string; codigo: string }>;
}

export function listAdminUsuarios(): Promise<AdminUsuario[]> {
  return apiGet<AdminUsuario[]>("/admin/usuarios");
}

export function getAdminUsuariosMeta(): Promise<AdminUsuarioMeta> {
  return apiGet<AdminUsuarioMeta>("/admin/usuarios/meta");
}

export function createAdminUsuario(payload: {
  nombre: string;
  apellido: string;
  email: string;
  empresa_id: number;
  rol_codigo: "admin" | "supervisor" | "tecnico";
  yacimientos?: number[];
}): Promise<AdminUsuario> {
  return apiPost<AdminUsuario>("/admin/usuarios", payload);
}

export function updateAdminUsuario(id: number, payload: {
  nombre: string;
  apellido: string;
  email: string;
  empresa_id: number;
  rol_codigo: "admin" | "supervisor" | "tecnico";
  yacimientos?: number[];
  activo?: boolean;
}): Promise<AdminUsuario> {
  return apiPut<AdminUsuario>(`/admin/usuarios/${id}`, payload);
}

export function createEmpresa(payload: { nombre: string }): Promise<{ id: number; nombre: string }> {
  return apiPost<{ id: number; nombre: string }>("/admin/empresas", payload);
}

export function createYacimiento(payload: { empresa_id: number; nombre: string; codigo: string }): Promise<{ id: number; nombre: string; codigo: string; empresa_id: number }> {
  return apiPost<{ id: number; nombre: string; codigo: string; empresa_id: number }>("/admin/yacimientos", payload);
}
