import { apiPostMultipart } from "./client";

export function createInspeccion(formData: FormData): Promise<any> {
  return apiPostMultipart<any>("/inspecciones", formData);
}
