# TermoVault - Frontend Security Architecture

Este documento detalla las medidas de seguridad y controles implementados en el frontend de TermoVault (Etapa 7) para mitigar vectores de ataque como Cross-Site Scripting (XSS), Clickjacking, y filtración de tokens mediante el almacenamiento persistente.

---

## 🛡️ 1. Política de Seguridad del Contenido (CSP)

La CSP se entrega **exclusivamente por header HTTP** (no por `<meta>`), con dos fuentes según el entorno:

- **Producción (documento HTML)**: Nginx, vía la fuente canónica [`infra/nginx/security-headers.conf`](file:///C:/Users/Alejandro/Desktop/local/termovault/infra/nginx/security-headers.conf).
- **API**: middleware [SecurityHeaders.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/app/Http/Middleware/SecurityHeaders.php).
- **Desarrollo**: Vite dev server (ver `server.headers` en `web/vite.config.ts`); incluye orígenes locales y `unsafe-inline/eval` que **no** se envían en el build de producción.

### Estructura de la CSP de producción
- **`default-src 'self'`**: Solo permite cargar recursos originados en el propio dominio de la aplicación.
- **`script-src 'self'`**: Restringe la ejecución de scripts al propio origen. **No se permite ningún CDN externo** (FIX [006]): el build de Vite sirve módulos desde el propio origen, y habilitar un CDN como `cdn.jsdelivr.net` implicaría que un compromiso de ese CDN derivara en XSS masivo.
- **`style-src 'self' 'unsafe-inline'`**: Permite estilos locales e inline (requeridos por React/Vite para estilos dinámicos).
- **`img-src 'self' data: https:`**: Imágenes locales, `data:` URIs y URLs remotas por HTTPS.
- **`connect-src 'self' https://cognito-idp.us-east-2.amazonaws.com`**: Whitelist de endpoints de red. La API es same-origin (`/api`); Cognito se incluye para el flujo de autenticación. **Sin** orígenes de desarrollo en producción.
- **`object-src 'none'`**: Bloquea plugins embebidos (`<object>`, `<embed>`).
- **`frame-ancestors 'none'`**: Impide el embebido en `<frame>`/`<iframe>`, mitigando **Clickjacking**.
- **`base-uri 'self'`**: Restringe las URLs del elemento `<base>`.
- **`form-action 'self'`**: Limita los destinos de envío de formularios.

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
   - Actualiza `connect-src` en los **tres** puntos de entrega para mantenerlos alineados:
     - Producción HTML: [`infra/nginx/security-headers.conf`](file:///C:/Users/Alejandro/Desktop/local/termovault/infra/nginx/security-headers.conf)
     - API: [SecurityHeaders.php](file:///C:/Users/Alejandro/Desktop/local/termovault/backend/app/Http/Middleware/SecurityHeaders.php)
     - Desarrollo: `server.headers` en `web/vite.config.ts`
   - **Nunca** agregues un CDN externo a `script-src` (ver FIX [006]).
2. **Ejecutar suite de pruebas de seguridad**:
   ```bash
   cd frontend
   npm test -- --testPathPattern="security"
   
   cd ../backend
   php artisan test --filter=SecurityHeaders
   ```
