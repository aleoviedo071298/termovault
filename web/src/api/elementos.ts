import { apiGet, apiPost, apiPut, apiDelete } from "./client";
import type { Elemento } from "../types/elemento";

export interface ElementoDetail extends Elemento {
  nivel_tension_id: number | null;
  tension: string | null;
  yacimiento_id: number;
  marca: string | null;
  modelo: string | null;
  n_serie: string | null;
  criticidad_id: number | null;
  estado_operativo: string | null;
  observaciones: string | null;
  observaciones_generales?: string | null;
}

export interface InspeccionArchivo {
  id: number;
  tipo: string;
  nombre: string;
  download_url: string;
  tamano: number;
}

export interface InspeccionNovedad {
  id: number;
  titulo: string;
  descripcion: string | null;
  ubicacion: string | null;
  temperatura: number | null;
  accion_recomendada: string | null;
  criticidad: string | null;
  criticidad_color: string | null;
  estado: string;
}

export interface InspeccionDetail {
  id: number;
  fecha_inspeccion: string;
  cuadrilla: string | null;
  integrantes: string | null;
  empresa_contratista: string | null;
  condiciones_clima: string | null;
  resumen: string | null;
  estado: string;
  tecnico: string | null;
  archivos: InspeccionArchivo[];
  novedades: InspeccionNovedad[];
}

export interface ElementoDetailResponse {
  elemento: ElementoDetail;
  inspecciones: InspeccionDetail[];
}

export interface CatalogItem {
  id: number;
  nombre: string;
}

export interface CatalogYacimiento extends CatalogItem {
  codigo: string;
}

export interface CatalogTipo extends CatalogItem {
  codigo: string;
  requiere_tension: boolean;
}

export interface CatalogTension {
  id: number;
  kv: number;
  etiqueta: string;
}

export interface CatalogCriticidad {
  id: number;
  nivel: number;
  nombre: string;
  color: string;
}

export interface Catalogos {
  yacimientos: CatalogYacimiento[];
  tipos_elemento: CatalogTipo[];
  niveles_tension: CatalogTension[];
  criticidades: CatalogCriticidad[];
}

export function listElementos(params?: { my_inspections_only?: boolean }): Promise<Elemento[]> {
  const query = params?.my_inspections_only ? "?my_inspections_only=true" : "";
  return apiGet<Elemento[]>(`/elementos${query}`);
}

export function getElemento(id: number): Promise<ElementoDetailResponse> {
  return apiGet<ElementoDetailResponse>(`/elementos/${id}`);
}

export function getCatalogos(): Promise<Catalogos> {
  return apiGet<Catalogos>("/catalogos");
}

export function createElemento(data: Partial<ElementoDetail>): Promise<ElementoDetail> {
  return apiPost<ElementoDetail>("/elementos", data);
}

export function updateElemento(id: number, data: Partial<ElementoDetail>): Promise<ElementoDetail> {
  return apiPut<ElementoDetail>(`/elementos/${id}`, data);
}

export function deleteElemento(id: number): Promise<void> {
  return apiDelete(`/elementos/${id}`);
}
