# TermoVault - Frontend Security Architecture

Este documento detalla las medidas de seguridad y controles implementados en el frontend de TermoVault (Etapa 7) para mitigar vectores de ataque como Cross-Site Scripting (XSS), Clickjacking, y filtración de tokens mediante el almacenamiento persistente.

---

## 🛡️ 1. Política de Seguridad del Contenido (CSP)

Se implementó una política de CSP estricta mediante meta-tags en [index.html](file:///C:/Users/Alejandro/Desktop/local/termovault/web/index.html) y a través del middleware del backend [SecurityHeaders.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/app/Http/Middleware/SecurityHeaders.php).

### Estructura de la CSP
- **`default-src 'self'`**: Solo permite cargar recursos (scripts, estilos, imágenes) originados en el propio dominio de la aplicación.
- **`script-src 'self' https://cdn.jsdelivr.net`**: Restringe la ejecución de scripts a fuentes locales y orígenes de confianza autorizados (como librerías estáticas validadas). Bloquea la inyección y ejecución de scripts en línea (`inline scripts`) no firmados.
- **`style-src 'self' 'unsafe-inline'`**: Permite estilos locales y de librerías CSS que requieran inicializaciones dinámicas seguras.
- **`img-src 'self' data: blob: https:`**: Habilita la visualización de imágenes locales, blobs cargados dinámicamente y URLs remotas de protocolo seguro.
- **`connect-src 'self' https://cognito-idp.*.amazonaws.com http://localhost:8000 http://127.0.0.1:8000 ws://localhost:5173 ws://127.0.0.1:5173`**: Whitelist de endpoints para peticiones de red (API y Cognito). Incluye soporte local para endpoints de desarrollo y WebSockets de hot-reload de Vite.
- **`frame-ancestors 'none'`**: Impide que la aplicación de frontend sea embebida en `<frame>`, `<iframe>` o `<embed>` por sitios externos, mitigando ataques de **Clickjacking**.
- **`base-uri 'self'`**: Restringe las URLs permitidas en el elemento `<base>` del documento.
- **`form-action 'self'`**: Limita los destinos para los envíos de formularios.

---

## 🔑 2. Secure Token Management (Memory-Only Storage)

Para evitar la filtración y exfiltración de tokens JWT en caso de ataques de inyección XSS exitosos, se eliminó la persistencia en `localStorage`, `sessionStorage` y `cookies`.

- **Mecanismo (`TokenManager`)**:
  - Los tokens de acceso (`access_token`) e identidad (`id_token`) de Cognito se almacenan únicamente en variables privadas dentro de la memoria RAM del navegador ([TokenManager.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/auth/TokenManager.ts)).
  - Al cerrar la pestaña, duplicarla o refrescar la página, el estado de la RAM se limpia por completo y el usuario se desautentica de forma predeterminada.
- **Inyección y Ciclo de Vida**:
  - El cliente de la API ([client.ts](file:///C:/Users/Alejandro/Desktop/local/termovault/web/src/api/client.ts)) obtiene el token en tiempo real de memoria para inyectarlo en la cabecera `Authorization: Bearer`.
  - El cliente intercepta de forma global cualquier respuesta `401 Unauthorized`. Ante esta respuesta, destruye los tokens de memoria y redirige automáticamente al usuario al portal de login.

---

## 🧼 3. Sanitización de Entradas y XSS Prevention

Se integró **DOMPurify** para limpiar cualquier entrada de usuario o marcado dinámico antes de renderizarlo en el DOM de la aplicación.

- **`sanitizeHtml(dirty)`**:
  - Utilizado para renderizar descripciones ricas (como novedades e informes).
  - Permite únicamente etiquetas de formato seguras (`p`, `br`, `strong`, `em`, `ul`, `li`, `a`) y atributos estrictamente validados (`href`, `target`, `rel`).
  - Bloquea atributos peligrosos como `onerror`, `onload`, `javascript:` y esquemas `data:`.
- **`sanitizeUserInput(input)`**:
  - Limpia completamente cualquier cadena de texto ingresada en campos generales antes de procesar cambios. Remueve todas las etiquetas HTML del texto de forma segura.
- **Componentes Seguros (`FormFields.tsx`)**:
  - **`<SanitizedInput>`**: Reemplazo de `<input>` que aplica sanitización preventiva en tiempo real.
  - **`<SafeHtmlContent>`**: Renderiza HTML sanitizado a través de `dangerouslySetInnerHTML` sin riesgo de ejecución de scripts inyectados.

---

## 🛠️ Procedimiento de Actualización de CSP

1. **Agregar nuevo dominio de API**:
   - Modifica el tag CSP de `connect-src` en [index.html](file:///C:/Users/Alejandro/Desktop/local/termovault/web/index.html) agregando el nuevo dominio.
   - Actualiza el CSP header en [SecurityHeaders.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/app/Http/Middleware/SecurityHeaders.php).
2. **Ejecutar suite de pruebas de seguridad**:
   ```bash
   cd frontend
   npm test -- --testPathPattern="security"
   
   cd ../backend
   php artisan test --filter=SecurityHeaders
   ```
