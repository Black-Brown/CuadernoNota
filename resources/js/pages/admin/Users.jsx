import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAdminUser, deactivateAdminUser, getAdminUsers, updateAdminUser } from '../../api/admin.api';
import useAuthStore from '../../store/authStore';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import SearchInput from '../../components/ui/SearchInput';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';
import { ADMIN_CREATABLE_ROLES, ROLE_LABELS } from '../../utils/adminAccess';

const ROLE_TONES = { teacher: 'indigo', coordinator: 'info', admin: 'neutral' };
const EMPTY_FORM = { name: '', email: '', password: '', role: 'teacher', active: true };

export default function AdminUsers() {
  const qc = useQueryClient();
  const { user: currentUser } = useAuthStore();
  const { toast, showToast } = useToast();

  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const [active, setActive] = useState('');
  const [page, setPage] = useState(1);

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [errors, setErrors] = useState({});
  const [deactivateTarget, setDeactivateTarget] = useState(null);

  const params = { per_page: 20, page };
  if (search) params.search = search;
  if (role) params.role = role;
  if (active !== '') params.active = active;

  const { data, isLoading } = useQuery({ queryKey: ['admin-users', params], queryFn: () => getAdminUsers(params) });
  const users = data?.data || [];

  const openCreate = () => { setEditingUser(null); setForm(EMPTY_FORM); setErrors({}); setDrawerOpen(true); };
  const openEdit = (u) => { setEditingUser(u); setForm({ name: u.name, email: u.email, password: '', role: u.role, active: u.active }); setErrors({}); setDrawerOpen(true); };

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { ...form };
      if (!payload.password) delete payload.password;
      return editingUser ? updateAdminUser(editingUser.id, payload) : createAdminUser(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setDrawerOpen(false);
      showToast(editingUser ? 'Usuario actualizado correctamente.' : 'Usuario registrado correctamente.');
    },
    onError: (error) => setErrors({ _global: getErrorMessage(error) }),
  });

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateAdminUser(deactivateTarget.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-users'] });
      setDeactivateTarget(null);
      showToast('Usuario desactivado correctamente.');
    },
    onError: (error) => { showToast(getErrorMessage(error), 'error'); setDeactivateTarget(null); },
  });

  const isSelf = (u) => u.id === currentUser?.id;

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Usuarios']}
        title="Usuarios y roles"
        description="Administra las cuentas de docentes, coordinadores y administradores del centro."
        actions={
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">person_add</span>
            Nuevo usuario
          </button>
        }
      />

      <FilterBar>
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por nombre o correo..." className="max-w-xs" />
        <select value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todos los roles</option>
          {Object.entries(ROLE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
        <select value={active} onChange={(e) => { setActive(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todos los estados</option>
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={users}
        emptyIcon="manage_accounts"
        emptyTitle="No hay usuarios que coincidan con los filtros."
        columns={[
          { key: 'name', label: 'Nombre', render: (u) => <span className="font-bold text-slate-900">{u.name}</span> },
          { key: 'email', label: 'Correo' },
          { key: 'role', label: 'Rol', align: 'center', render: (u) => <StatusBadge tone={ROLE_TONES[u.role]} label={ROLE_LABELS[u.role] || u.role} /> },
          { key: 'active', label: 'Estado', align: 'center', render: (u) => <StatusBadge tone={u.active ? 'success' : 'neutral'} label={u.active ? 'Activo' : 'Inactivo'} /> },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (u) => (
              <div className="flex justify-end gap-1">
                <button onClick={() => openEdit(u)} title="Editar" className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800">
                  <span className="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button
                  onClick={() => setDeactivateTarget(u)}
                  disabled={!u.active || isSelf(u)}
                  title={isSelf(u) ? 'No puedes desactivar tu propia cuenta' : 'Desactivar'}
                  className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                >
                  <span className="material-symbols-outlined text-[18px]">person_off</span>
                </button>
              </div>
            ),
          },
        ]}
      />

      {data?.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs font-semibold text-slate-500">
          <span>Página {data.current_page} de {data.last_page} · {data.total} usuarios</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Anterior</button>
            <button disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Siguiente</button>
          </div>
        </div>
      )}

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editingUser ? 'Editar usuario' : 'Nuevo usuario'}
        description={editingUser ? `Actualiza los datos de ${editingUser.name}.` : 'Registra una nueva cuenta de acceso.'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || !form.name.trim() || !form.email.trim()}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {errors._global && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{errors._global}</div>}
          <FormField label="Nombre completo" required>
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Correo electrónico" required>
            <input type="email" className={inputClass} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </FormField>
          <FormField label="Contraseña" hint={editingUser ? 'Déjalo en blanco para mantener la actual.' : 'Mínimo 8 caracteres. Si se omite, se genera una aleatoria.'}>
            <input type="password" className={inputClass} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          </FormField>
          <FormField label="Rol" required>
            <select
              className={selectClass}
              value={form.role}
              disabled={editingUser && isSelf(editingUser)}
              onChange={(e) => setForm({ ...form, role: e.target.value })}
            >
              {editingUser?.role === 'coordinator' && <option value="coordinator" disabled>Coordinador · Próximamente</option>}
              {Object.entries(ADMIN_CREATABLE_ROLES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
          </FormField>
          {editingUser && (
            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
              <input
                type="checkbox"
                checked={form.active}
                disabled={isSelf(editingUser)}
                onChange={(e) => setForm({ ...form, active: e.target.checked })}
              />
              Cuenta activa
            </label>
          )}
        </div>
      </SideDrawer>

      <ConfirmDialog
        open={!!deactivateTarget}
        onClose={() => setDeactivateTarget(null)}
        onConfirm={() => deactivateMutation.mutate()}
        loading={deactivateMutation.isPending}
        tone="danger"
        title="Desactivar usuario"
        message={`${deactivateTarget?.name} perderá acceso al sistema y sus sesiones activas serán invalidadas. Esta acción no elimina su historial.`}
        confirmLabel="Desactivar"
      />

      <Toast toast={toast} />
    </>
  );
}
