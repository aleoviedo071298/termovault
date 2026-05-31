import { tokenManager } from "../auth/TokenManager";

// Default prod-safe: mismo origen (/api). En desarrollo, .env.development.local
// define VITE_API_URL=http://localhost:8000/api para apuntar al backend local.
export const API_URL = import.meta.env.VITE_API_URL ?? "/api";

/**
 * Common fetch helper that injects Authorization token and intercepts 401 errors.
 */
async function secureFetch(path: string, options: RequestInit = {}): Promise<Response> {
  const token = tokenManager.getToken();
  const headers = new Headers(options.headers || {});

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers
  });

  if (response.status === 401) {
    tokenManager.clearToken();
    // Redirect to login page on auth failure
    window.location.href = "/login";
  }

  return response;
}

export async function apiGet<T>(path: string): Promise<T> {
  const response = await secureFetch(path, {
    headers: {
      Accept: "application/json"
    }
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  return response.json() as Promise<T>;
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  const response = await secureFetch(path, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(body)
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  return response.json() as Promise<T>;
}

export async function apiPut<T>(path: string, body: unknown): Promise<T> {
  const response = await secureFetch(path, {
    method: "PUT",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(body)
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  return response.json() as Promise<T>;
}

export async function apiPatch<T>(path: string, body: unknown): Promise<T> {
  const response = await secureFetch(path, {
    method: "PATCH",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(body)
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  return response.json() as Promise<T>;
}

export async function apiDelete(path: string): Promise<void> {
  const response = await secureFetch(path, {
    method: "DELETE",
    headers: {
      Accept: "application/json"
    }
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }
}

export async function apiPostMultipart<T>(path: string, formData: FormData): Promise<T> {
  const response = await secureFetch(path, {
    method: "POST",
    headers: {
      Accept: "application/json"
    },
    body: formData
  });

  if (!response.ok) {
    let errorMsg = `API request failed with ${response.status}`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  return response.json() as Promise<T>;
}

function filenameFromContentDisposition(header: string | null): string | null {
  if (!header) return null;

  const utf8Match = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1].replace(/"/g, ""));
  }

  const asciiMatch = header.match(/filename="?([^"]+)"?/i);
  return asciiMatch?.[1] ?? null;
}

export async function apiDownload(path: string, fallbackFilename: string): Promise<void> {
  const response = await secureFetch(path, {
    headers: {
      Accept: "application/octet-stream"
    }
  });

  if (!response.ok) {
    let errorMsg = `No se pudo descargar el archivo (${response.status})`;
    try {
      const errData = await response.json() as { message?: string };
      if (errData?.message) errorMsg = errData.message;
    } catch {}
    throw new Error(errorMsg);
  }

  const blob = await response.blob();
  const objectUrl = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = filenameFromContentDisposition(response.headers.get("Content-Disposition")) ?? fallbackFilename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(objectUrl);
}
