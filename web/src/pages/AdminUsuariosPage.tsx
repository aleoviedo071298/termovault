import { ArrowLeft, Building2, Edit3, Plus, RefreshCw, Save, Users } from "lucide-react";
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
    <main className="app-shell">
      <section className="toolbar">
        <div className="brand-mark"><Users size={20} /></div>
        <div className="title-stack"><p>Admin</p><h1>Usuarios y Organización</h1></div>
        <div className="metric"><span>{loading ? "--" : items.length}</span><small>usuarios</small></div>
        <div />
        <div style={{ display: "flex", gap: 8 }}>
          <button className="icon-button" type="button" onClick={onBack}><ArrowLeft size={16} /></button>
          <button className="icon-button" type="button" onClick={() => void loadAll()}><RefreshCw size={16} /></button>
        </div>
      </section>

      {error ? <section className="notice">{error}</section> : null}

      <section className="table-frame" style={{ marginTop: 16, padding: 20 }}>
        <h3 style={{ marginTop: 0 }}>Alta rápida de usuario</h3>
        <div className="form-grid">
          <div className="form-group"><label>Nombre</label><input value={nombre} onChange={(e) => setNombre(e.target.value)} /></div>
          <div className="form-group"><label>Apellido</label><input value={apellido} onChange={(e) => setApellido(e.target.value)} /></div>
          <div className="form-group col-span-2"><label>Email</label><input value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <div className="form-group">
            <label>Rol</label>
            <select value={rol} onChange={(e) => setRol(e.target.value as RoleCode)}>
              <option value="tecnico">Técnico</option>
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
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(220px,1fr))", gap: 8 }}>
                {allYacimientos.map((y) => (
                  <label key={y.id} style={{ display: "flex", gap: 8, alignItems: "center" }}>
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
        <div style={{ marginTop: 12 }}>
          <button className="submit-btn" type="button" disabled={saving || !nombre || !apellido || !email || !empresaId} onClick={() => void handleCreate()}>
            <Plus size={16} /> {saving ? "Creando..." : "Crear usuario local"}
          </button>
        </div>
      </section>

      <section className="table-frame" style={{ marginTop: 16, padding: 20 }}>
        <h3 style={{ marginTop: 0, display: "flex", alignItems: "center", gap: 8 }}><Building2 size={18} />Organización</h3>
        <div style={{ display: "grid", gridTemplateColumns: "2fr auto", gap: 12, alignItems: "end", marginBottom: 18 }}>
          <div className="form-group">
            <label>Nueva empresa / contratista</label>
            <input value={empresaNueva} onChange={(e) => setEmpresaNueva(e.target.value)} placeholder="Ej: ELECTROPATAGONIA" />
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <button className="submit-btn" type="button" disabled={!empresaNueva.trim()} onClick={async () => {
              try {
                await createEmpresa({ nombre: empresaNueva.trim() });
                setEmpresaNueva("");
                await loadAll();
              } catch (err) { setError(err instanceof Error ? err.message : "No se pudo crear empresa."); }
            }}>Crear empresa</button>
          </div>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr auto", gap: 12, alignItems: "end" }}>
          <div className="form-group">
            <label>Empresa propietaria del yacimiento</label>
            <select value={empresaId} onChange={(e) => setEmpresaId(e.target.value ? Number(e.target.value) : "")}>
              {meta?.empresas.map((empresa) => <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label>Nuevo yacimiento</label>
            <input value={yacNombre} onChange={(e) => setYacNombre(e.target.value)} placeholder="Ej: PAE Central" />
          </div>
          <div className="form-group">
            <label>Código yacimiento</label>
            <input value={yacCodigo} onChange={(e) => setYacCodigo(e.target.value)} placeholder="Ej: YAC-PAE-CENTRAL" />
          </div>
          <div className="form-group" style={{ margin: 0 }}>
            <button className="submit-btn" type="button" disabled={!empresaId || !yacNombre.trim() || !yacCodigo.trim()} onClick={async () => {
              if (!empresaId) return;
              try {
                await createYacimiento({ empresa_id: Number(empresaId), nombre: yacNombre.trim(), codigo: yacCodigo.trim() });
                setYacNombre(""); setYacCodigo(""); await loadAll();
              } catch (err) { setError(err instanceof Error ? err.message : "No se pudo crear yacimiento."); }
            }}>Crear yacimiento</button>
          </div>
        </div>
        <p style={{ marginTop: 12, marginBottom: 0, color: "#59645e", fontSize: 13 }}>
          Regla operativa: supervisor contratista ve solo informes de su empresa dentro de yacimientos asignados. Supervisor PAE con <strong>YAC-PAE</strong> asignado ve todos los informes de ese yacimiento.
        </p>
      </section>

      <section className="table-frame" style={{ marginTop: 16 }}>
        <table>
          <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Empresa</th><th>Yacimientos</th><th>Estado</th><th>Acción</th></tr></thead>
          <tbody>
            {loading ? <tr><td colSpan={7} className="empty-cell">Cargando usuarios...</td></tr> : items.map((u) => (
              <tr key={u.id}>
                <td>{u.nombre} {u.apellido}</td>
                <td>{u.email}</td>
                <td>{u.rol ?? "-"}</td>
                <td>{u.empresa ?? "-"}</td>
                <td>{u.yacimientos.map((y) => y.codigo).join(", ") || "-"}</td>
                <td>{u.activo ? "Activo" : "Inactivo"}</td>
                <td><button className="icon-button" type="button" onClick={() => startEdit(u)}><Edit3 size={14} /></button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      {editingId ? (
        <div className="modal-backdrop">
          <div className="modal-content">
            <header className="modal-header">
              <h2>Editar usuario #{editingId}</h2>
              <button className="close-btn" onClick={() => setEditingId(null)} type="button">×</button>
            </header>
            <div className="modal-form">
              <div className="form-grid">
                <div className="form-group"><label>Nombre</label><input value={editNombre} onChange={(e) => setEditNombre(e.target.value)} /></div>
                <div className="form-group"><label>Apellido</label><input value={editApellido} onChange={(e) => setEditApellido(e.target.value)} /></div>
                <div className="form-group col-span-2"><label>Email</label><input value={editEmail} onChange={(e) => setEditEmail(e.target.value)} /></div>
                <div className="form-group">
                  <label>Rol</label>
                  <select value={editRol} onChange={(e) => setEditRol(e.target.value as RoleCode)}>
                    <option value="tecnico">Técnico</option>
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
                    <label>{editRol === "supervisor" ? "Yacimientos del supervisor" : "Yacimientos del técnico"}</label>
                    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(220px,1fr))", gap: 8 }}>
                      {allYacimientos.map((y) => (
                        <label key={y.id} style={{ display: "flex", gap: 8, alignItems: "center" }}>
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

