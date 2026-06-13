import {
  ArrowUpRight,
  Building2,
  Factory,
  Plus,
  RefreshCw,
  Save,
  ShieldCheck,
  UserPlus,
  Users,
  AlertCircle,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
  createAdminUsuario,
  createEmpresa,
  createYacimiento,
  getAdminUsuariosMeta,
  listAdminUsuarios,
  updateAdminUsuario,
  type AdminUsuario,
  type AdminUsuarioMeta,
} from "../api/adminUsuarios";

import { Button } from "../components/ui/Button";
import { Badge } from "../components/ui/Badge";
import { KPICard } from "../components/ui/KPICard";
import { Card, CardHeader, CardBody } from "../components/ui/Card";
import { Field, Input, Select } from "../components/ui/Field";
import { Modal } from "../components/ui/Modal";

interface Props {
  onBack: () => void;
}
type RoleCode = "admin" | "supervisor" | "tecnico";

function roleLabel(rol: string | null | undefined): string {
  if (rol === "admin") return "Admin";
  if (rol === "supervisor") return "Supervisor";
  if (rol === "tecnico") return "Técnico";
  return rol ?? "—";
}

function roleTone(rol: string | null | undefined): "primary" | "info" | "warning" | "neutral" {
  if (rol === "admin") return "primary";
  if (rol === "supervisor") return "info";
  if (rol === "tecnico") return "warning";
  return "neutral";
}

export default function AdminUsuariosPage({ onBack: _onBack }: Props) {
  const [meta, setMeta] = useState<AdminUsuarioMeta | null>(null);
  const [items, setItems] = useState<AdminUsuario[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Crear usuario
  const [nombre, setNombre] = useState("");
  const [apellido, setApellido] = useState("");
  const [email, setEmail] = useState("");
  const [rol, setRol] = useState<RoleCode>("tecnico");
  const [empresaId, setEmpresaId] = useState<number | "">("");
  const [yacimientos, setYacimientos] = useState<number[]>([]);

  // Crear empresa/yacimiento
  const [empresaNueva, setEmpresaNueva] = useState("");
  const [yacNombre, setYacNombre] = useState("");
  const [yacCodigo, setYacCodigo] = useState("");
  const [yacPermiteSupervisorElementos, setYacPermiteSupervisorElementos] = useState(false);

  // Editar usuario (modal)
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

  useEffect(() => {
    void loadAll();
  }, []);

  const allYacimientos = useMemo(() => meta?.yacimientos ?? [], [meta]);
  const empresaNombreById = useMemo(() => {
    const names = new Map<number, string>();
    meta?.empresas.forEach((empresa) => names.set(empresa.id, empresa.nombre));
    return names;
  }, [meta]);
  // Los tecnicos/contratistas pueden cargar informes en yacimientos de empresas owner.
  const yacimientosCreate = allYacimientos;
  const yacimientosEdit = allYacimientos;
  const activeUsers = useMemo(() => items.filter((u) => u.activo).length, [items]);
  const supervisorCount = useMemo(() => items.filter((u) => u.rol === "supervisor").length, [items]);

  function changeEmpresaCreate(value: number | "") {
    setEmpresaId(value);
  }
  function changeEmpresaEdit(value: number | "") {
    setEditEmpresaId(value);
  }
  function yacimientoLabel(yacimiento: AdminUsuarioMeta["yacimientos"][number]): string {
    const empresa = empresaNombreById.get(yacimiento.empresa_id);
    return empresa
      ? `${yacimiento.nombre} (${yacimiento.codigo}) — ${empresa}`
      : `${yacimiento.nombre} (${yacimiento.codigo})`;
  }

  async function handleCreate() {
    if (!empresaId) return;
    try {
      setSaving(true);
      await createAdminUsuario({
        nombre,
        apellido,
        email,
        empresa_id: Number(empresaId),
        rol_codigo: rol,
        yacimientos: rol === "admin" ? [] : yacimientos,
      });
      setNombre("");
      setApellido("");
      setEmail("");
      setRol("tecnico");
      setYacimientos([]);
      await loadAll();
    } catch (err) {
      setError(err instanceof Error ? err.message : "No se pudo crear el usuario.");
    } finally {
      setSaving(false);
    }
  }

  function startEdit(user: AdminUsuario) {
    const empId: number | "" = user.empresa_id ?? "";
    setEditingId(user.id);
    setEditNombre(user.nombre);
    setEditApellido(user.apellido);
    setEditEmail(user.email);
    setEditRol((user.rol as RoleCode) ?? "tecnico");
    setEditEmpresaId(empId);
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
        activo: editActivo,
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
    <>
      {/* ─── Header de página ─── */}
      <div className="tv-page-head">
        <div className="tv-page-head__left">
          <div className="tv-page-head__chips">
            <Badge tone="neutral" variant="outline">Administración</Badge>
            <Badge tone="neutral" variant="outline">{meta?.empresas.length ?? 0} empresas · {allYacimientos.length} yacimientos</Badge>
          </div>
          <h1 className="tv-page-head__title">Usuarios</h1>
          <p className="tv-page-head__subtitle">
            Perfiles, empresas contratistas y yacimientos habilitados.
          </p>
        </div>
        <div className="tv-page-head__actions">
          <Button
            variant="secondary"
            size="md"
            leftIcon={<RefreshCw size={14} strokeWidth={2} />}
            onClick={() => void loadAll()}
            disabled={loading}
          >
            Refrescar
          </Button>
        </div>
      </div>

      {error && (
        <div className="tv-notice tv-notice--danger" role="alert">
          <AlertCircle size={16} />
          <span>{error}</span>
        </div>
      )}

      {/* ─── KPIs ─── */}
      <div className="tv-kpi-grid">
        <KPICard
          label="Usuarios"
          value={loading ? "—" : items.length}
          caption="Total de cuentas"
          icon={<Users size={18} strokeWidth={1.8} />}
          tone="info"
        />
        <KPICard
          label="Activos"
          value={loading ? "—" : activeUsers}
          caption="Habilitados para ingresar"
          icon={<ShieldCheck size={18} strokeWidth={1.8} />}
          tone="success"
        />
        <KPICard
          label="Supervisores"
          value={loading ? "—" : supervisorCount}
          caption="Rol supervisor activo"
          icon={<UserPlus size={18} strokeWidth={1.8} />}
          tone="warning"
        />
        <KPICard
          label="Empresas"
          value={meta?.empresas.length ?? "—"}
          caption={`${allYacimientos.length} yacimientos en total`}
          icon={<Factory size={18} strokeWidth={1.8} />}
          tone="neutral"
        />
      </div>

      {/* ─── 2 cards: Nuevo usuario | Organización ─── */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "minmax(0, 1fr) minmax(0, 1fr)",
          gap: 16,
          marginBottom: 18,
        }}
        className="tv-admin-grid"
      >
        {/* ── Nuevo usuario ── */}
        <Card>
          <CardHeader
            title="Nuevo usuario"
            subtitle="Crear un usuario y asignarle su alcance"
          />
          <CardBody>
            <div className="tv-step__grid">
              <Field label="Nombre" required>
                {(id) => (
                  <Input id={id} value={nombre} onChange={(e) => setNombre(e.target.value)} placeholder="Ej: María" />
                )}
              </Field>
              <Field label="Apellido" required>
                {(id) => (
                  <Input id={id} value={apellido} onChange={(e) => setApellido(e.target.value)} placeholder="Ej: González" />
                )}
              </Field>
              <div className="tv-field--full">
                <Field label="Email" required>
                  {(id) => (
                    <Input
                      id={id}
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="nombre@empresa.com"
                      autoComplete="off"
                    />
                  )}
                </Field>
              </div>
              <Field label="Rol" required>
                {(id) => (
                  <Select id={id} value={rol} onChange={(e) => setRol(e.target.value as RoleCode)}>
                    <option value="tecnico">Técnico</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="admin">Admin</option>
                  </Select>
                )}
              </Field>
              <Field label="Empresa / contratista" required>
                {(id) => (
                  <Select
                    id={id}
                    value={empresaId}
                    onChange={(e) => changeEmpresaCreate(e.target.value ? Number(e.target.value) : "")}
                  >
                    {meta?.empresas.map((empresa) => (
                      <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>
                    ))}
                  </Select>
                )}
              </Field>

              {rol !== "admin" ? (
                <div className="tv-field--full">
                  <Field
                    label={
                      rol === "supervisor"
                        ? "Yacimientos que puede supervisar"
                        : "Yacimientos habilitados para carga"
                    }
                    hint="Marcá los que correspondan."
                  >
                    {() => (
                      <div className="tv-checkbox-grid">
                        {yacimientosCreate.length === 0 ? (
                          <p className="tv-section__empty" style={{ margin: 0 }}>
                            No hay yacimientos activos. Creá uno en el panel de Organización.
                          </p>
                        ) : (
                          yacimientosCreate.map((y) => (
                            <label key={y.id} className="tv-check">
                              <input
                                type="checkbox"
                                checked={yacimientos.includes(y.id)}
                                onChange={(e) =>
                                  setYacimientos((prev) =>
                                    e.target.checked
                                      ? [...prev, y.id]
                                      : prev.filter((id) => id !== y.id)
                                  )
                                }
                              />
                              <span>{yacimientoLabel(y)}</span>
                            </label>
                          ))
                        )}
                      </div>
                    )}
                  </Field>
                </div>
              ) : (
                <div className="tv-field--full">
                  <div className="tv-notice" style={{ marginBottom: 0, background: "var(--tv-info-soft)", borderColor: "rgba(37, 99, 235, 0.15)", color: "#1D4ED8" }}>
                    <ShieldCheck size={14} />
                    <span>Los administradores acceden a todos los yacimientos; no requieren asignación.</span>
                  </div>
                </div>
              )}
            </div>

            <div style={{ marginTop: 14, display: "flex", justifyContent: "flex-end" }}>
              <Button
                variant="primary"
                leftIcon={<Plus size={15} />}
                disabled={saving || !nombre || !apellido || !email || !empresaId}
                onClick={() => void handleCreate()}
              >
                {saving ? "Creando…" : "Crear usuario"}
              </Button>
            </div>
          </CardBody>
        </Card>

        {/* ── Organización ── */}
        <Card>
          <CardHeader
            title="Organización"
            subtitle="Empresas y yacimientos"
          />
          <CardBody>
            {/* Nueva empresa */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 10, alignItems: "end" }}>
              <Field label="Nueva empresa / contratista">
                {(id) => (
                  <Input
                    id={id}
                    value={empresaNueva}
                    onChange={(e) => setEmpresaNueva(e.target.value)}
                    placeholder="Ej: ELECTROPATAGONIA"
                  />
                )}
              </Field>
              <Button
                variant="secondary"
                leftIcon={<Building2 size={14} />}
                disabled={!empresaNueva.trim()}
                onClick={async () => {
                  try {
                    await createEmpresa({ nombre: empresaNueva.trim() });
                    setEmpresaNueva("");
                    await loadAll();
                  } catch (err) {
                    setError(err instanceof Error ? err.message : "No se pudo crear empresa.");
                  }
                }}
              >
                Crear empresa
              </Button>
            </div>

            <div style={{ height: 1, background: "var(--tv-border)", margin: "18px 0" }} />

            {/* Nuevo yacimiento */}
            <div className="tv-step__grid">
              <Field label="Empresa propietaria">
                {(id) => (
                  <Select
                    id={id}
                    value={empresaId}
                    onChange={(e) => changeEmpresaCreate(e.target.value ? Number(e.target.value) : "")}
                  >
                    {meta?.empresas.map((empresa) => (
                      <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>
                    ))}
                  </Select>
                )}
              </Field>
              <Field label="Código yacimiento">
                {(id) => (
                  <Input
                    id={id}
                    value={yacCodigo}
                    onChange={(e) => setYacCodigo(e.target.value)}
                    placeholder="Ej: YAC-PAE-CENTRAL"
                  />
                )}
              </Field>
              <div className="tv-field--full">
                <Field label="Nuevo yacimiento">
                  {(id) => (
                    <Input
                      id={id}
                      value={yacNombre}
                      onChange={(e) => setYacNombre(e.target.value)}
                      placeholder="Ej: PAE Central"
                    />
                  )}
                </Field>
              </div>

              <div className="tv-field--full">
                <label className="tv-check">
                  <input
                    type="checkbox"
                    checked={yacPermiteSupervisorElementos}
                    onChange={(e) => setYacPermiteSupervisorElementos(e.target.checked)}
                  />
                  <span>El supervisor de la empresa puede editar / agregar / eliminar elementos en este yacimiento</span>
                </label>
              </div>
            </div>

            <div style={{ marginTop: 14, display: "flex", justifyContent: "flex-end" }}>
              <Button
                variant="secondary"
                leftIcon={<Plus size={14} />}
                disabled={!empresaId || !yacNombre.trim() || !yacCodigo.trim()}
                onClick={async () => {
                  if (!empresaId) return;
                  try {
                    await createYacimiento({
                      empresa_id: Number(empresaId),
                      nombre: yacNombre.trim(),
                      codigo: yacCodigo.trim(),
                      permite_supervisor_elementos: yacPermiteSupervisorElementos,
                    });
                    setYacNombre("");
                    setYacCodigo("");
                    setYacPermiteSupervisorElementos(false);
                    await loadAll();
                  } catch (err) {
                    setError(err instanceof Error ? err.message : "No se pudo crear yacimiento.");
                  }
                }}
              >
                Crear yacimiento
              </Button>
            </div>

            <div
              className="tv-notice"
              style={{ marginTop: 16, marginBottom: 0, background: "var(--tv-surface-muted)", borderColor: "var(--tv-border)", color: "var(--tv-text-muted)" }}
            >
              <span>
                Supervisor contratista ve informes de su empresa dentro de yacimientos asignados.
                Si el yacimiento marca permiso de supervisor owner, ese supervisor también puede
                administrar elementos.
              </span>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* ─── Tabla de usuarios ─── */}
      <div className="tv-table-wrap">
        <div className="tv-table-head">
          <div>
            <div className="tv-table-head__title">Listado de usuarios</div>
            <div className="tv-table-head__sub">Estado, rol, empresa y yacimientos</div>
          </div>
          <span className="tv-table-head__count">{items.length}</span>
        </div>

        <div className="tv-table-scroll">
          <table className="tv-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Empresa</th>
                <th>Yacimientos</th>
                <th>Estado</th>
                <th aria-label="Acción" />
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="tv-table__empty">Cargando usuarios…</td>
                </tr>
              ) : items.length === 0 ? (
                <tr>
                  <td colSpan={7} className="tv-table__empty">No hay usuarios para mostrar.</td>
                </tr>
              ) : (
                items.map((u) => (
                  <tr key={u.id}>
                    <td className="tv-table__main">
                      <div className="tv-table__main-title">
                        <Users size={15} strokeWidth={1.8} />
                        <span>{u.nombre} {u.apellido}</span>
                      </div>
                      <div className="tv-table__main-sub">Usuario #{u.id}</div>
                    </td>
                    <td style={{ color: "var(--tv-text-muted)" }}>{u.email}</td>
                    <td>
                      <Badge tone={roleTone(u.rol)}>{roleLabel(u.rol)}</Badge>
                    </td>
                    <td>{u.empresa ?? "—"}</td>
                    <td style={{ fontSize: 12, color: "var(--tv-text-secondary)" }}>
                      {u.yacimientos.length === 0 ? (
                        <span style={{ color: "var(--tv-text-muted)" }}>
                          {u.rol === "admin" ? "Todos" : "—"}
                        </span>
                      ) : (
                        u.yacimientos.map((y) => y.codigo).join(", ")
                      )}
                    </td>
                    <td>
                      <Badge tone={u.activo ? "success" : "neutral"} dot>
                        {u.activo ? "Activo" : "Inactivo"}
                      </Badge>
                    </td>
                    <td style={{ textAlign: "right" }}>
                      <button
                        type="button"
                        className="tv-table__action"
                        onClick={() => startEdit(u)}
                      >
                        Editar
                        <ArrowUpRight size={14} />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* ─── Modal de edición ─── */}
      <Modal
        open={editingId !== null}
        onClose={() => setEditingId(null)}
        title={`Editar usuario #${editingId ?? ""}`}
        subtitle="Modificá los datos y guardá los cambios."
        size="lg"
        footer={
          <>
            <Button variant="ghost" onClick={() => setEditingId(null)} disabled={saving}>
              Cancelar
            </Button>
            <Button
              variant="primary"
              leftIcon={<Save size={14} />}
              onClick={() => void saveEdit()}
              disabled={saving || !editEmpresaId}
            >
              {saving ? "Guardando…" : "Guardar cambios"}
            </Button>
          </>
        }
      >
        <div className="tv-step__grid">
          <Field label="Nombre" required>
            {(id) => (
              <Input id={id} value={editNombre} onChange={(e) => setEditNombre(e.target.value)} />
            )}
          </Field>
          <Field label="Apellido" required>
            {(id) => (
              <Input id={id} value={editApellido} onChange={(e) => setEditApellido(e.target.value)} />
            )}
          </Field>
          <div className="tv-field--full">
            <Field label="Email" required>
              {(id) => (
                <Input id={id} type="email" value={editEmail} onChange={(e) => setEditEmail(e.target.value)} />
              )}
            </Field>
          </div>
          <Field label="Rol" required>
            {(id) => (
              <Select id={id} value={editRol} onChange={(e) => setEditRol(e.target.value as RoleCode)}>
                <option value="tecnico">Técnico</option>
                <option value="supervisor">Supervisor</option>
                <option value="admin">Admin</option>
              </Select>
            )}
          </Field>
          <Field label="Empresa" required>
            {(id) => (
              <Select
                id={id}
                value={editEmpresaId}
                onChange={(e) => changeEmpresaEdit(e.target.value ? Number(e.target.value) : "")}
              >
                {meta?.empresas.map((empresa) => (
                  <option key={empresa.id} value={empresa.id}>{empresa.nombre}</option>
                ))}
              </Select>
            )}
          </Field>
          <Field label="Estado">
            {(id) => (
              <Select
                id={id}
                value={editActivo ? "1" : "0"}
                onChange={(e) => setEditActivo(e.target.value === "1")}
              >
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </Select>
            )}
          </Field>

          {editRol !== "admin" ? (
            <div className="tv-field--full">
              <Field
                label={editRol === "supervisor" ? "Yacimientos del supervisor" : "Yacimientos del técnico"}
                hint="Marcá los yacimientos a los que tendrá acceso."
              >
                {() => (
                  <div className="tv-checkbox-grid">
                    {yacimientosEdit.length === 0 ? (
                      <p className="tv-section__empty" style={{ margin: 0 }}>
                        No hay yacimientos activos.
                      </p>
                    ) : (
                      yacimientosEdit.map((y) => (
                        <label key={y.id} className="tv-check">
                          <input
                            type="checkbox"
                            checked={editYacimientos.includes(y.id)}
                            onChange={(e) =>
                              setEditYacimientos((prev) =>
                                e.target.checked
                                  ? [...prev, y.id]
                                  : prev.filter((id) => id !== y.id)
                              )
                            }
                          />
                          <span>{yacimientoLabel(y)}</span>
                        </label>
                      ))
                    )}
                  </div>
                )}
              </Field>
            </div>
          ) : (
            <div className="tv-field--full">
              <div className="tv-notice" style={{ marginBottom: 0, background: "var(--tv-info-soft)", borderColor: "rgba(37, 99, 235, 0.15)", color: "#1D4ED8" }}>
                <ShieldCheck size={14} />
                <span>Los administradores acceden a todos los yacimientos; no requieren asignación.</span>
              </div>
            </div>
          )}
        </div>
      </Modal>
    </>
  );
}
