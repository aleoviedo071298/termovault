const API_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api";

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

