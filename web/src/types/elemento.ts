export interface Elemento {
  id: number;
  nombre: string;
  codigo: string;
  tipo_elemento_id: number;
  tipo: string | null;
  funcion: string | null;
  empresa_id: number | null;
  yacimiento: string | null;
  criticidad: string | null;
  criticidad_nivel: number | null;
  criticidad_color: string | null;
  nivel_tension_id?: number | null;
  tension?: string | null;
}
