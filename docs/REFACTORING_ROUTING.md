# Refactoring: Frontend Routing Migration — TermoVault

Guide for migrating from manual `window.location.pathname` routing to `react-router-dom` v6.

---

## Current State (Manual Routing)

**File**: `web/src/App.tsx` (lines 11-50)

```tsx
// ❌ Current approach
const path = window.location.pathname;

if (path === "/elementos/gestion") {
  return <ElementosGestion />;
} else if (path === "/admin/usuarios") {
  return <AdminUsuariosPage />;
} else {
  return <Dashboard />;
}
```

### Limitations

| Issue | Impact | Example |
|-------|--------|---------|
| **No route parameters** | Can't access item IDs | `/elementos/123` requires parsing |
| **No nested routes** | Difficult to share layouts | Detail page can't inherit parent layout |
| **No lazy loading** | All components loaded upfront | Dashboard, Forms all load immediately |
| **No route guards** | Manually check user roles | Same check logic repeated everywhere |
| **Fragile matching** | String comparisons error-prone | Typo breaks navigation |

---

## Target State (react-router-dom v6)

**Example**:
```tsx
<BrowserRouter>
  <Routes>
    <Route path="/" element={<Layout />}>
      <Route index element={<Dashboard />} />
      <Route path="elementos/gestion" element={<ElementosGestion />} />
      <Route path="elementos/:id" element={<ElementoDetalle />} />
      <Route path="admin/usuarios" element={<AdminUsuariosPage />} />
    </Route>
    <Route path="/login" element={<Login />} />
  </Routes>
</BrowserRouter>
```

### Benefits

✅ **Route parameters**: `/elementos/:id` → access `useParams()`  
✅ **Nested routes**: Share layout, breadcrumbs, sidebars  
✅ **Lazy loading**: `React.lazy(() => import('...'))` per route  
✅ **Route guards**: Declarative `<ProtectedRoute>` wrapper  
✅ **Type-safe**: Use TypeScript for route definitions  

---

## Migration Steps

### 1️⃣ Install react-router-dom

```bash
cd web
npm install react-router-dom
```

**Current dependencies**:
- React 19
- Vite
- Ensure no conflicting routing libs

### 2️⃣ Create Route Configuration

**New file**: `web/src/routes.tsx`

```tsx
import { ReactNode } from "react";
import Dashboard from "./pages/Dashboard";
import ElementosGestion from "./pages/ElementosGestion";
import AdminUsuariosPage from "./pages/AdminUsuariosPage";
import { Login } from "./pages/Login";

export interface RouteConfig {
  path: string;
  element: ReactNode;
  requireAuth?: boolean;
  allowedGroups?: string[];
  children?: RouteConfig[];
}

export const routes: RouteConfig[] = [
  {
    path: "/login",
    element: <Login />,
    requireAuth: false,
  },
  {
    path: "/",
    element: <Layout />,
    requireAuth: true,
    children: [
      {
        path: "",
        element: <Dashboard />,
        allowedGroups: ["admin", "supervisor", "tecnico"],
      },
      {
        path: "elementos/gestion",
        element: <ElementosGestion />,
        allowedGroups: ["admin", "supervisor"],
      },
      {
        path: "elementos/:id",
        element: <ElementoDetalle />,
        allowedGroups: ["admin", "supervisor", "tecnico"],
      },
      {
        path: "admin/usuarios",
        element: <AdminUsuariosPage />,
        allowedGroups: ["admin"],
      },
    ],
  },
];
```

### 3️⃣ Update App.tsx

**Replace** manual routing with Router:

```tsx
import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import { useAuth } from "./auth/useAuth";
import { ProtectedRoute } from "./auth/ProtectedRoute";
import { Login } from "./pages/Login";
import { routes } from "./routes";

function App() {
  const { user } = useAuth();

  return (
    <Router>
      <Routes>
        {routes.map((route) =>
          route.requireAuth ? (
            <Route
              key={route.path}
              path={route.path}
              element={
                <ProtectedRoute allowedGroups={route.allowedGroups}>
                  {route.element}
                </ProtectedRoute>
              }
            >
              {route.children?.map((child) => (
                <Route key={child.path} path={child.path} element={child.element} />
              ))}
            </Route>
          ) : (
            <Route key={route.path} path={route.path} element={route.element} />
          )
        )}
      </Routes>
    </Router>
  );
}

export default App;
```

### 4️⃣ Update Navigation

**Replace** `navigate()` function with `useNavigate()` hook:

```tsx
import { useNavigate } from "react-router-dom";

function SomeComponent() {
  const navigate = useNavigate();

  return (
    <button onClick={() => navigate("/elementos/gestion")}>
      Go to Elementos
    </button>
  );
}
```

### 5️⃣ Update ProtectedRoute

**Current**: `web/src/auth/ProtectedRoute.tsx`

Make sure it works with react-router-dom:

```tsx
import { Navigate } from "react-router-dom";
import { useAuth } from "./useAuth";

export interface ProtectedRouteProps {
  allowedGroups?: string[];
  children: ReactNode;
}

export function ProtectedRoute({ allowedGroups = [], children }: ProtectedRouteProps) {
  const { user } = useAuth();

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedGroups.length > 0) {
    const hasAccess = allowedGroups.some((group) => user.groups?.includes(group));
    if (!hasAccess) {
      return <Navigate to="/" replace />;
    }
  }

  return <>{children}</>;
}
```

### 6️⃣ Remove Manual Routing

**Delete** from `App.tsx`:
- `const path = window.location.pathname;`
- `function navigate() { ... }`
- All `if (currentPath === ...)` conditions
- popstate event listeners

---

## Testing Checklist

- [ ] ✅ `/login` → Login page
- [ ] ✅ `/` → Dashboard (authenticated)
- [ ] ✅ `/elementos/gestion` → Element management (admin/supervisor only)
- [ ] ✅ Browser back/forward buttons work
- [ ] ✅ Deep links work (paste URL, should load correct page)
- [ ] ✅ Route params work (e.g., `/elementos/123`)
- [ ] ✅ Role-based access works (redirect non-admins from `/admin/usuarios`)

---

## Timeline Recommendation

**When**: Implement before adding pages that need:
- Route parameters (e.g., `/elementos/:id`)
- Nested detail views
- Complex navigation flows

**When NOT**: Don't refactor just for cleanup. Wait until a feature naturally requires it.

---

## References

- [react-router-dom v6 Docs](https://reactrouter.com/en/main)
- [Nested Routes & Layouts](https://reactrouter.com/en/main/start/overview#nested-routes)
- [useParams Hook](https://reactrouter.com/en/main/hooks/use-params)
- [useNavigate Hook](https://reactrouter.com/en/main/hooks/use-navigate)
