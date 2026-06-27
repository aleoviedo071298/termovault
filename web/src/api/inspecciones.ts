import { apiPatch, apiPostMultipart } from "./client";
import { apiGet } from "./client";

export function createInspeccion(formData: FormData): Promise<any> {
  return apiPostMultipart<any>("/inspecciones", formData);
}

export interface InspeccionDetalle {
  id: number;
  fecha_inspeccion: string;
  estado: string;
  integrantes: string | null;
  empresa_contratista: string | null;
  condiciones_clima: string | null;
  resumen: string | null;
  observaciones_revisor: string | null;
  fecha_revision: string | null;
  fecha_cierre: string | null;
  revisada_por: { id: number; nombre: string; email: string } | null;
  cerrada_por: { id: number; nombre: string; email: string } | null;
  tecnico: { id: number; nombre: string; email: string } | null;
  elemento: { id: number; nombre: string; codigo: string; tipo: string | null; yacimiento: string | null } | null;
  archivos: Array<{ id: number; tipo: string; nombre: string; download_url: string; tamano: number | null; mime: string | null }>;
  novedades: Array<{
    id: number;
    titulo: string;
    descripcion: string | null;
    ubicacion: string | null;
    temperatura: number | null;
    criticidad: string | null;
    criticidad_color: string | null;
    estado: string;
    accion_recomendada: string | null;
  }>;
}

export function getInspeccion(id: number): Promise<InspeccionDetalle> {
  return apiGet<InspeccionDetalle>(`/inspecciones/${id}`);
}

export function updateInspeccionEstado(id: number, payload: { estado: "enviada" | "revisada" | "cerrada"; observaciones_revisor?: string }): Promise<{ id: number; estado: string }> {
  return apiPatch<{ id: number; estado: string }>(`/inspecciones/${id}/estado`, payload);
}

export interface UpdateInspeccionPayload {
  elemento_id?: number;
  fecha_inspeccion?: string;
  integrantes?: string | null;
  empresa_contratista?: string | null;
  condiciones_clima?: string | null;
  resumen?: string | null;
}

export function updateInspeccion(id: number, payload: UpdateInspeccionPayload): Promise<{ id: number; message: string }> {
  return apiPatch<{ id: number; message: string }>(`/inspecciones/${id}`, payload);
}
