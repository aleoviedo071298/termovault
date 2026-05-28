import { ArrowLeft, ArrowUpRight, Building2, Edit3, Factory, Plus, RefreshCw, Save, ShieldCheck, SlidersHorizontal, UserPlus, Users } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
  createAdminUsuario,
  createEmpresa,
  createYacimiento,
  getAdminUsuariosMeta,
  listAdminUsuarios,
  updateAdminUsuario,
  type AdminUsuario,
  type AdminUsuarioMeta
} from "../api/adminUsuarios";

interface Props { onBack: () => void }
type RoleCode = "admin" | "supervisor" | "tecnico";

export default function AdminUsuariosPage({ onBack }: Props) {
  const [meta, setMeta] = useState<AdminUsuarioMeta | null>(null);
  const [items, setItems] = useState<AdminUsuario[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [nombre, setNombre] = useState("");
  const [apellido, setApellido] = useState("");
  const [email, setEmail] = useState("");
  const [rol, setRol] = useState<RoleCode>("tecnico");
  const [empresaId, setEmpresaId] = useState<number | "">("");
  const [yacimientos, setYacimientos] = useState<number[]>([]);

  const [empresaNueva, setEmpresaNueva] = useState("");
  const [yacNombre, setYacNombre] = useState("");
  const [yacCodigo, setYacCodigo] = useState("");

  const [editingId, setEditingId] = useState<number | null>(null);
  const [editNombre, setEditNombre] = useState("");
  const [editApellido, setEditApellido] = useState("");
  const [editEmail, setEditEmail] = useState("");
  const [editRol, setEditRol] = useState<RoleCode>("tecnico");
  const [editEmpresaId, setEditEmpresaId] = useState<number | "">("");
  const [editYacimientos, setEditYacimientos] = useState<number[]>([]);
  const [editActivo, setEditActivo] = useState(true);

  async function loadAll() {
    try {
      setLoading(true);
      setError(null);
      const [m, u] = await Promise.all([getAdminUsuariosMeta(), listAdminUsuarios()]);
      setMeta(m);
      setItems(u);
      if (!empresaId && m.empresas.length > 0) setEmpresaId(m.empresas[0].id);
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo cargar usuarios.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void loadAll(); }, []);

  const allYacimientos = useMemo(() => meta?.yacimientos ?? [], [meta]);
  const activeUsers = useMemo(() => items.filter((u) => u.activo).length, [items]);
  const supervisorCount = useMemo(() => items.filter((u) => u.rol === "supervisor").length, [items]);

  async function handleCreate() {
    if (!empresaId) return;
    try {
      setSaving(true);
      await createAdminUsuario({
        nombre, apellido, email,
        empresa_id: Number(empresaId),
        rol_codigo: rol,
        yacimientos: rol === "admin" ? [] : yacimientos,
      });
      setNombre(""); setApellido(""); setEmail(""); setRol("tecnico"); setYacimientos([]);
      await loadAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo crear el usuario.");
    } finally {
      setSaving(false);
    }
  }

  function startEdit(user: AdminUsuario) {
    setEditingId(user.id);
    setEditNombre(user.nombre);
    setEditApellido(user.apellido);
    setEditEmail(user.email);
    setEditRol((user.rol as RoleCode) ?? "tecnico");
    setEditEmpresaId(user.empresa_id ?? "");
    setEditYacimientos(user.yacimientos.map((y) => y.id));
    setEditActivo(Boolean(user.activo));
  }

  async function saveEdit() {
    if (!editingId || !editEmpresaId) return;
    try {
      setSaving(true);
      await updateAdminUsuario(editingId, {
        nombre: editNombre,
        apellido: editApellido,
        email: editEmail,
        empresa_id: Number(editEmpresaId),
        rol_codigo: editRol,
        yacimientos: editRol === "admin" ? [] : editYacimientos,
        activo: editActivo
      });
      setEditingId(null);
      await loadAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo actualizar el usuario.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="app-shell admin-users-shell">
      <section className="admin-hero dashboard-section">
        <div className="admin-hero-main">
          <div className="hero-kicker">
            <span className="system-dot" />
            Administracion / identidad y alcance
          </div>
          <h1>Usuarios y organizacion</h1>
          <p>Gestion de perfiles, empresas contratistas y yacimientos habilitados para operacion termografica.</p>
        </div>

        <div className="admin-hero-panel">
          <div className="admin-metrics">
            <article><Users size={16} /><span>Usuarios</span><strong>{loading ? "--" : items.length}</strong></article>
            <article><ShieldCheck size={16} /><span>Activos</span><strong>{loading ? "--" : activeUsers}</strong></article>
            <article><UserPlus size={16} /><span>Supervisores</span><strong>{loading ? "--" : supervisorCount}</strong></article>
            <article><Factory size={16} /><span>Empresas</span><strong>{meta?.empresas.length ?? "--"}</strong></article>
          </div>
          <div className="admin-actions">
            <button className="secondary-command" type="button" onClick={onBack}><ArrowLeft size={15} /> Dashboard</button>
            <button className="utility-command" type="button" onClick={() => void loadAll()} title="Recargar"><RefreshCw size={15} /></button>
          </div>
        </div>
      </section>

      {error ? <section className="notice">{error}</section> : null}

      <section className="admin-grid">
        <section className="dashboard-section admin-form-card">
          <div className="section-heading">
            <div>
              <span>Alta rapida de usuario</span>
              <small>Crear usuario local y asignar alcance operativo</small>
            </div>
          </div>
          <div className="admin-card-body">
            <div className="form-grid">
              <div className="form-group"><label>Nombre</label><input value={nombre} onChange={(e) => setNombre(e.target.value)} /></div>
              <div className="form-group"><label>Apellido</label><input value={apellido} onChange={(e) => setApellido(e.target.value)} /></div>
              <div className="form-group col-span-2"><label>Email</label><input value={email} onChange={(e) => setEmail(e.target.value)} /></div>
              <div className="form-group">
                <label>Rol</label>
                <select value={rol} onChange={(e) => setRol(e.target.value as RoleCode)}>
                  <option value="tecnico">Tecnico</option>
                  <option value="supervisor">Supervisor</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div className="form-group">
                <label>Empresa / Contratista</label>
                <select value={empresaId} onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : "")}>
                  {meta?.empresas.map((empresa) => <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>)}
                </select>
              </div>
              {rol !== "admin" ? (
                <div className="form-group col-span-2">
                  <label>{rol === "supervisor" ? "Yacimientos que puede supervisar" : "Yacimientos habilitados para carga"}</label>
                  <div className="check-grid">
                    {allYacimientos.map((y) => (
                      <label key={y.id} className="check-row">
                        <input
                          type="checkbox"
                          checked={yacimientos.includes(y.id)}
                          onChange={(e) => setYacimientos((prev) => e.target.checked ? [...prev, y.id] : prev.filter((id) => id !== y.id))}
                        />
                        {y.nombre} ({y.codigo})
                      </label>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
            <button className="primary-command admin-submit" type="button" disabled={saving || !nombre || !apellido || !email || !empresaId} onClick={() => void handleCreate()}>
              <Plus size={16} /> {saving ? "Creando..." : "Crear usuario local"}
            </button>
          </div>
        </section>

        <section className="dashboard-section admin-form-card">
          <div className="section-heading">
            <div>
              <span>Organizacion</span>
              <small>Empresas y yacimientos usados por permisos y reportes</small>
            </div>
          </div>
          <div className="admin-card-body">
            <div className="organization-stack">
              <div className="organization-line">
                <div className="form-group">
                  <label>Nueva empresa / contratista</label>
                  <input value={empresaNueva} onChange={(e) => setEmpresaNueva(e.target.value)} placeholder="Ej: ELECTROPATAGONIA" />
                </div>
                <button className="secondary-command" type="button" disabled={!empresaNueva.trim()} onClick={async () => {
                  try {
                    await createEmpresa({ nombre: empresaNueva.trim() });
                    setEmpresaNueva("");
                    await loadAll();
                  } catch (err) { setError(err instanceof Error ? err.message : "No se pudo crear empresa."); }
                }}><Building2 size={15} /> Crear empresa</button>
              </div>

              <div className="organization-yacimiento-grid">
                <div className="form-group">
                  <label>Empresa propietaria</label>
                  <select value={empresaId} onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : "")}>
                    {meta?.empresas.map((empresa) => <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>)}
                  </select>
                </div>
                <div className="form-group">
                  <label>Nuevo yacimiento</label>
                  <input value={yacNombre} onChange={(e) => setYacNombre(e.target.value)} placeholder="Ej: PAE Central" />
                </div>
                <div className="form-group">
                  <label>Codigo yacimiento</label>
                  <input value={yacCodigo} onChange={(e) => setYacCodigo(e.target.value)} placeholder="Ej: YAC-PAE-CENTRAL" />
                </div>
                <button className="secondary-command" type="button" disabled={!empresaId || !yacNombre.trim() || !yacCodigo.trim()} onClick={async () => {
                  if (!empresaId) return;
                  try {
                    await createYacimiento({ empresa_id: Number(empresaId), nombre: yacNombre.trim(), codigo: yacCodigo.trim() });
                    setYacNombre(""); setYacCodigo(""); await loadAll();
                  } catch (err) { setError(err instanceof Error ? err.message : "No se pudo crear yacimiento."); }
                }}>Crear yacimiento</button>
              </div>
            </div>
            <p className="admin-rule-note">
              Supervisor contratista ve informes de su empresa dentro de yacimientos asignados. Supervisor PAE con YAC-PAE asignado ve el yacimiento completo.
            </p>
          </div>
        </section>
      </section>

      <section className="dashboard-section admin-users-panel">
        <div className="section-heading report-heading">
          <div>
            <span>Directorio de usuarios</span>
            <small>Estado, rol, empresa y alcance de yacimientos</small>
          </div>
          <strong>{items.length}</strong>
        </div>

        <div className="report-table-wrap">
          <table className="report-table admin-users-table">
            <thead>
              <tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Empresa</th><th>Yacimientos</th><th>Estado</th><th aria-label="Accion" /></tr>
            </thead>
            <tbody>
              {loading ? <tr><td colSpan={7} className="empty-cell">Cargando usuarios...</td></tr> : items.map((u) => (
                <tr key={u.id}>
                  <td className="main-report-cell">
                    <div className="report-title-line">
                      <Users size={16} />
                      <strong>{u.nombre} {u.apellido}</strong>
                    </div>
                    <span>Usuario #{u.id}</span>
                  </td>
                  <td>{u.email}</td>
                  <td><span className={`role-chip role-chip-${u.rol ?? "none"}`}>{u.rol ?? "-"}</span></td>
                  <td>{u.empresa ?? "-"}</td>
                  <td>{u.yacimientos.map((y) => y.codigo).join(", ") || "-"}</td>
                  <td><span className={`status-chip ${u.activo ? "status-cerrada" : "status-revisada"}`}>{u.activo ? "Activo" : "Inactivo"}</span></td>
                  <td className="action-cell">
                    <button className="table-action" type="button" onClick={() => startEdit(u)}>
                      Editar
                      <ArrowUpRight size={14} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {editingId ? (
        <div className="modal-backdrop">
          <div className="modal-content">
            <header className="modal-header">
              <h2>Editar usuario #{editingId}</h2>
              <button className="close-btn" onClick={() => setEditingId(null)} type="button">x</button>
            </header>
            <div className="modal-form">
              <div className="form-grid">
                <div className="form-group"><label>Nombre</label><input value={editNombre} onChange={(e) => setEditNombre(e.target.value)} /></div>
                <div className="form-group"><label>Apellido</label><input value={editApellido} onChange={(e) => setEditApellido(e.target.value)} /></div>
                <div className="form-group col-span-2"><label>Email</label><input value={editEmail} onChange={(e) => setEditEmail(e.target.value)} /></div>
                <div className="form-group">
                  <label>Rol</label>
                  <select value={editRol} onChange={(e) => setEditRol(e.target.value as RoleCode)}>
                    <option value="tecnico">Tecnico</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>
                <div className="form-group">
                  <label>Empresa</label>
                  <select value={editEmpresaId} onChange={(e) => setEditEmpresaId(e.target.value ? Number(e.target.value) : "")}>
                    {meta?.empresas.map((empresa) => <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>)}
                  </select>
                </div>
                <div className="form-group">
                  <label>Estado</label>
                  <select value={editActivo ? "1" : "0"} onChange={(e) => setEditActivo(e.target.value === "1")}>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                  </select>
                </div>
                {editRol !== "admin" ? (
                  <div className="form-group col-span-2">
                    <label>{editRol === "supervisor" ? "Yacimientos del supervisor" : "Yacimientos del tecnico"}</label>
                    <div className="check-grid">
                      {allYacimientos.map((y) => (
                        <label key={y.id} className="check-row">
                          <input
                            type="checkbox"
                            checked={editYacimientos.includes(y.id)}
                            onChange={(e) => setEditYacimientos((prev) => e.target.checked ? [...prev, y.id] : prev.filter((id) => id !== y.id))}
                          />
                          {y.nombre} ({y.codigo})
                        </label>
                      ))}
                    </div>
                  </div>
                ) : null}
              </div>
              <div className="modal-actions">
                <button className="cancel-btn" type="button" onClick={() => setEditingId(null)}>Cancelar</button>
                <button className="submit-btn" type="button" onClick={() => void saveEdit()} disabled={saving}>
                  <Save size={16} /> {saving ? "Guardando..." : "Guardar cambios"}
                </button>
              </div>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}
