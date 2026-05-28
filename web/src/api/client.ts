export const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api";

export async function apiGet<T>(path: string): Promise<T> {
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    headers
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
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: "POST",
    headers,
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
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: "PUT",
    headers,
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
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: "PATCH",
    headers,
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
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: "DELETE",
    headers
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
  const token = localStorage.getItem("access_token");

  const headers: HeadersInit = {
    Accept: "application/json"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: "POST",
    headers,
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
  const token = localStorage.getItem("access_token");
  const headers: HeadersInit = {
    Accept: "application/octet-stream"
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${path}`, { headers });
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
