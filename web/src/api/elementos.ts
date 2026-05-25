import { apiGet } from "./client";
import type { Elemento } from "../types/elemento";

export function listElementos(): Promise<Elemento[]> {
  return apiGet<Elemento[]>("/elementos");
}
