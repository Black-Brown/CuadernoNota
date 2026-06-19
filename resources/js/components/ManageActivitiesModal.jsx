import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getActivitiesBySubject } from '../api/grades.api';
import { getPeriods } from '../api/periods.api';
import { createActivity, updateActivity } from '../api/activities.api';

// Icon / color map for known base activities
const ICON_MAP = {
  'Proyectos':          { icon: 'account_tree', color: 'bg-blue-50 text-blue-600' },
  'Examen':             { icon: 'assignment',   color: 'bg-purple-50 text-purple-600' },
  'Tareas':             { icon: 'edit_note',    color: 'bg-amber-50 text-amber-600' },
  'Ensayo':             { icon: 'article',      color: 'bg-rose-50 text-rose-600' },
  'Producción en aula': { icon: 'school',       color: 'bg-teal-50 text-teal-600' },
  'Diagnósticas':       { icon: 'fact_check',   color: 'bg-emerald-50 text-emerald-600' },
};
const DEFAULT_META = { icon: 'add_task', color: 'bg-slate-50 text-slate-600' };

const ACTIVITY_TYPES = [
  { value: 'proyecto',        label: 'Proyecto' },
  { value: 'examen',          label: 'Examen' },
  { value: 'tarea',           label: 'Tarea' },
  { value: 'ensayo',          label: 'Ensayo' },
  { value: 'produccion_aula', label: 'Producción en aula' },
  { value: 'diagnostica',     label: 'Diagnóstica' },
  { value: 'otra',            label: 'Otra' },
];

const EMPTY_FORM = {
  name:        '',
  description: '',
  type:        '',
  period_id:   '',
  status:      'active',
  due_date:    '',
  weight:      '',
};

export default function ManageActivitiesModal({ subjectId, onClose }) {
  const queryClient = useQueryClient();

  // 'list' | 'create'
  const [view, setView]           = useState('list');
  const [form, setForm]           = useState(EMPTY_FORM);
  const [editingId, setEditingId] = useState(null);
  const [editingName, setEditingName] = useState('');
  const [toastMsg, setToastMsg]   = useState('');
  const [toastType, setToastType] = useState('success');

  // ── Queries ────────────────────────────────────────────────────────────────
  const { data: activitiesData, isLoading } = useQuery({
    queryKey: ['activitiesBySubject', subjectId],
    queryFn:  () => getActivitiesBySubject(subjectId),
    enabled:  !!subjectId,
  });

  const { data: periodsData } = useQuery({
    queryKey: ['periods'],
    queryFn:  getPeriods,
    staleTime: 5 * 60_000,
  });

  const activities = activitiesData?.activities || [];
  const periods    = periodsData?.periods || [];

  // ── Mutations ──────────────────────────────────────────────────────────────
  const toggleMutation = useMutation({
    mutationFn: ({ id }) => updateActivity(id, {}), // PATCH toggles active
    onSuccess:  () => {
      queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId] });
      showToast('Estado actualizado correctamente.');
    },
    onError: () => showToast('Error al actualizar el estado.', 'error'),
  });

  const renameMutation = useMutation({
    mutationFn: ({ id, name }) => updateActivity(id, { name }),
    onSuccess:  () => {
      queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId] });
      setEditingId(null);
      setEditingName('');
      showToast('Nombre actualizado correctamente.');
    },
    onError: () => showToast('Error al renombrar.', 'error'),
  });

  const createMutation = useMutation({
    mutationFn: (payload) => createActivity(payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId] });
      setForm(EMPTY_FORM);
      setView('list');
      showToast(`"${data.activity?.name ?? 'Actividad'}" creada correctamente.`);
    },
    onError: () => showToast('Error al crear la actividad.', 'error'),
  });

  // ── Helpers ────────────────────────────────────────────────────────────────
  const showToast = (msg, type = 'success') => {
    setToastMsg(msg);
    setToastType(type);
    setTimeout(() => setToastMsg(''), 3500);
  };

  const handleToggle = (act) => toggleMutation.mutate({ id: act.id });

  const handleRenameConfirm = () => {
    const trimmed = editingName.trim();
    if (!trimmed || !editingId) return;
    renameMutation.mutate({ id: editingId, name: trimmed });
  };

  const handleFormChange = (field, value) =>
    setForm(prev => ({ ...prev, [field]: value }));

  const handleCreateSubmit = (e) => {
    e.preventDefault();
    if (!form.name.trim()) return;

    createMutation.mutate({
      name:        form.name.trim(),
      subject_id:  subjectId,
      description: form.description || null,
      type:        form.type || null,
      period_id:   form.period_id ? Number(form.period_id) : null,
      status:      form.status || 'active',
      due_date:    form.due_date || null,
      weight:      form.weight !== '' ? Number(form.weight) : null,
      icon:        'add_task',
    });
  };

  // ESC closes modal (from list) or goes back to list (from create)
  useEffect(() => {
    const handler = (e) => {
      if (e.key === 'Escape') {
        if (view === 'create') { setView('list'); setForm(EMPTY_FORM); }
        else onClose();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [view, onClose]);

  // ── Render ─────────────────────────────────────────────────────────────────
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
        onClick={() => { if (view === 'create') { setView('list'); setForm(EMPTY_FORM); } else onClose(); }}
      />

      {/* Modal panel */}
      <div
        className="relative z-10 w-full max-w-2xl max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl overflow-hidden"
        style={{ animation: 'fadeInScale 0.18s cubic-bezier(.4,0,.2,1)' }}
      >

        {/* ── Header ──────────────────────────────────────────────────────── */}
        <div className="flex items-center justify-between px-7 pt-6 pb-4 border-b border-slate-100 shrink-0">
          {view === 'list' ? (
            <div>
              <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">Gestión de Actividades</h2>
              <p className="text-xs text-slate-500 mt-0.5">Configure los tipos de evaluación disponibles para esta asignatura.</p>
            </div>
          ) : (
            <div className="flex items-center gap-3">
              <button
                onClick={() => { setView('list'); setForm(EMPTY_FORM); }}
                className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
              >
                <span className="material-symbols-outlined text-[20px]">arrow_back</span>
              </button>
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-slate-950 text-white flex items-center justify-center">
                  <span className="material-symbols-outlined text-[22px]">add_task</span>
                </div>
                <div>
                  <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">Nueva Actividad</h2>
                  <p className="text-xs text-slate-500 mt-0.5">Registra una actividad de evaluación para esta asignatura.</p>
                </div>
              </div>
            </div>
          )}

          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors ml-4"
          >
            <span className="material-symbols-outlined text-[22px]">close</span>
          </button>
        </div>

        {/* ── Body ────────────────────────────────────────────────────────── */}
        <div className="flex-1 overflow-y-auto custom-scrollbar">

          {/* ── VIEW: LIST ─────────────────────────────────────────────────── */}
          {view === 'list' && (
            <>
              <table className="w-full border-collapse text-left">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <th className="px-6 py-3.5 w-12">#</th>
                    <th className="px-6 py-3.5">Nombre de la Actividad</th>
                    <th className="px-6 py-3.5 text-center w-32">Estado</th>
                    <th className="px-6 py-3.5 text-right w-28">Acciones</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">

                  {isLoading && (
                    <tr>
                      <td colSpan="4" className="py-12 text-center text-slate-400 text-xs font-semibold">
                        <span className="material-symbols-outlined animate-spin text-[28px] block mx-auto mb-2 text-slate-300">sync</span>
                        Cargando actividades...
                      </td>
                    </tr>
                  )}

                  {!isLoading && activities.length === 0 && (
                    <tr>
                      <td colSpan="4" className="py-12 text-center text-slate-400 text-xs font-semibold">
                        No hay actividades configuradas para esta asignatura.
                      </td>
                    </tr>
                  )}

                  {activities.map((act, idx) => {
                    const meta      = ICON_MAP[act.name] ?? DEFAULT_META;
                    const isBase    = !!ICON_MAP[act.name];
                    const isEditing = editingId === act.id;

                    return (
                      <tr
                        key={act.id}
                        className={`transition-all group ${!act.active ? 'opacity-50 grayscale bg-slate-50/60' : 'hover:bg-slate-50/50'}`}
                      >
                        <td className="px-6 py-3.5 font-mono text-[10px] text-slate-400">
                          {String(idx + 1).padStart(2, '0')}
                        </td>

                        <td className="px-6 py-3.5">
                          {isEditing ? (
                            <div className="flex items-center gap-2">
                              <input
                                autoFocus
                                type="text"
                                value={editingName}
                                onChange={(e) => setEditingName(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') handleRenameConfirm(); if (e.key === 'Escape') setEditingId(null); }}
                                className="flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-semibold focus:ring-1 focus:ring-slate-900 focus:border-slate-900 outline-none"
                              />
                              <button onClick={handleRenameConfirm} className="px-2.5 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-700 transition-colors">
                                OK
                              </button>
                              <button onClick={() => setEditingId(null)} className="px-2.5 py-1.5 border border-slate-200 text-slate-500 rounded-lg text-xs font-bold hover:bg-slate-50 transition-colors">
                                ✕
                              </button>
                            </div>
                          ) : (
                            <div className="flex items-center gap-3">
                              <div className={`p-2 rounded-lg ${meta.color}`}>
                                <span className="material-symbols-outlined text-[18px]">{act.icon ?? meta.icon}</span>
                              </div>
                              <div className="flex items-center gap-2">
                                <span className="font-semibold text-slate-800">{act.name}</span>
                                {act.type && (
                                  <span className="text-[9px] font-bold px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded uppercase tracking-wider">
                                    {act.type}
                                  </span>
                                )}
                                {isBase && (
                                  <span className="text-[9px] font-bold px-1.5 py-0.5 bg-indigo-50 text-indigo-500 rounded uppercase tracking-wider">
                                    Base
                                  </span>
                                )}
                              </div>
                            </div>
                          )}
                        </td>

                        <td className="px-6 py-3.5">
                          <div className="flex justify-center">
                            <label className="relative inline-flex items-center cursor-pointer select-none">
                              <input
                                type="checkbox"
                                className="sr-only peer"
                                checked={act.active}
                                onChange={() => handleToggle(act)}
                                disabled={toggleMutation.isPending}
                              />
                              <div className="w-11 h-6 bg-slate-200 rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:start-[2px]
                                after:bg-white after:border-slate-300 after:border after:rounded-full
                                after:h-5 after:w-5 after:transition-all
                                peer-checked:bg-emerald-500 transition-colors">
                              </div>
                            </label>
                          </div>
                        </td>

                        <td className="px-6 py-3.5 text-right">
                          <button
                            onClick={() => { setEditingId(act.id); setEditingName(act.name); }}
                            disabled={!act.active || isEditing}
                            title="Renombrar"
                            className="p-1.5 text-slate-400 hover:text-slate-800 rounded-lg transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                          >
                            <span className="material-symbols-outlined text-[18px]">edit</span>
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>

              {/* Add button */}
              <div className="px-6 py-4 border-t border-slate-200">
                <button
                  onClick={() => setView('create')}
                  className="flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors group"
                >
                  <div className="w-7 h-7 rounded-full border-2 border-dashed border-slate-300 group-hover:border-slate-700 group-hover:bg-slate-50 flex items-center justify-center transition-colors">
                    <span className="material-symbols-outlined text-[16px]">add</span>
                  </div>
                  Nueva Actividad
                </button>
              </div>

              {/* Info notice */}
              <div className="mx-4 mb-4 bg-slate-50 border border-slate-200 rounded-xl px-5 py-4 flex items-start gap-3">
                <span className="material-symbols-outlined text-indigo-400 text-[20px] shrink-0 mt-0.5">info</span>
                <p className="text-[11px] text-slate-500 leading-relaxed">
                  Deshabilitar una actividad <span className="font-bold text-slate-700">no elimina</span> sus datos históricos.
                  Las actividades marcadas como <span className="font-bold text-indigo-500">Base</span> están predefinidas por la institución.
                </p>
              </div>
            </>
          )}

          {/* ── VIEW: CREATE ───────────────────────────────────────────────── */}
          {view === 'create' && (
            <form onSubmit={handleCreateSubmit} className="p-6 space-y-5">

              {/* Name */}
              <div className="space-y-1.5">
                <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                  Nombre de la Actividad <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  autoFocus
                  placeholder="Ej. Proyecto Final, Examen Parcial, Ensayo..."
                  value={form.name}
                  onChange={(e) => handleFormChange('name', e.target.value)}
                  className="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                />
              </div>

              {/* Description */}
              <div className="space-y-1.5">
                <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                  Descripción
                </label>
                <textarea
                  rows="3"
                  placeholder="Objetivos, instrucciones o rúbrica de la actividad..."
                  value={form.description}
                  onChange={(e) => handleFormChange('description', e.target.value)}
                  className="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors resize-none"
                />
              </div>

              {/* Type + Period + Status */}
              <div className="grid grid-cols-3 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                    Tipo <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <select
                      required
                      value={form.type}
                      onChange={(e) => handleFormChange('type', e.target.value)}
                      className="w-full appearance-none px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                    >
                      <option value="">Seleccionar</option>
                      {ACTIVITY_TYPES.map(t => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                      ))}
                    </select>
                    <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">
                      expand_more
                    </span>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                    Período <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <select
                      required
                      value={form.period_id}
                      onChange={(e) => handleFormChange('period_id', e.target.value)}
                      className="w-full appearance-none px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                    >
                      <option value="">Seleccionar</option>
                      {periods.map(p => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                      ))}
                    </select>
                    <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">
                      expand_more
                    </span>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                    Estado
                  </label>
                  <div className="relative">
                    <select
                      value={form.status}
                      onChange={(e) => handleFormChange('status', e.target.value)}
                      className="w-full appearance-none px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                    >
                      <option value="active">Activo</option>
                      <option value="draft">Borrador</option>
                      <option value="inactive">Inactivo</option>
                    </select>
                    <span className="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">
                      expand_more
                    </span>
                  </div>
                </div>
              </div>

              {/* Due date + Weight */}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                    Fecha límite
                  </label>
                  <input
                    type="date"
                    value={form.due_date}
                    onChange={(e) => handleFormChange('due_date', e.target.value)}
                    className="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-[11px] uppercase tracking-wide font-semibold text-slate-500">
                    Ponderación
                  </label>
                  <div className="relative">
                    <input
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      placeholder="Ej. 100"
                      value={form.weight}
                      onChange={(e) => handleFormChange('weight', e.target.value)}
                      className="w-full px-4 py-2.5 pr-9 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition-colors"
                    />
                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-semibold pointer-events-none">
                      %
                    </span>
                  </div>
                </div>
              </div>

              {/* Icon preview */}
              <div className="flex items-center gap-4 bg-slate-50 border border-slate-200 rounded-xl p-4">
                <div className="w-12 h-12 rounded-xl bg-slate-950 text-white flex items-center justify-center shrink-0">
                  <span className="material-symbols-outlined text-[28px]">add_task</span>
                </div>
                <div>
                  <p className="font-bold text-sm text-slate-900">Ícono por defecto</p>
                  <p className="text-[11px] text-slate-500 leading-relaxed">
                    Esta actividad usará un ícono del sistema. No es necesario subir imágenes.
                  </p>
                </div>
              </div>

              {/* Footer actions */}
              <div className="flex justify-end gap-3 pt-1">
                <button
                  type="button"
                  onClick={() => { setView('list'); setForm(EMPTY_FORM); }}
                  className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-50 transition-colors"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || !form.name.trim() || !form.type || !form.period_id}
                  className="px-5 py-2.5 bg-slate-950 text-white font-bold text-sm rounded-xl hover:bg-slate-700 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {createMutation.isPending ? (
                    <span className="material-symbols-outlined text-[18px] animate-spin">sync</span>
                  ) : (
                    <span className="material-symbols-outlined text-[18px]">check</span>
                  )}
                  Guardar Actividad
                </button>
              </div>

            </form>
          )}

        </div>

        {/* ── Footer (list view only) ──────────────────────────────────────── */}
        {view === 'list' && (
          <div className="px-7 py-4 border-t border-slate-100 flex justify-end shrink-0">
            <button
              onClick={onClose}
              className="px-5 py-2.5 bg-slate-950 text-white text-xs font-bold rounded-xl hover:bg-slate-700 transition-colors"
            >
              Cerrar
            </button>
          </div>
        )}

      </div>

      {/* Toast */}
      {toastMsg && (
        <div className={`fixed bottom-6 right-6 z-[60] flex items-center gap-2 px-4 py-3 rounded-xl shadow-xl text-xs font-semibold text-white ${toastType === 'error' ? 'bg-red-600' : 'bg-slate-900'}`}>
          <span className="material-symbols-outlined text-base">
            {toastType === 'error' ? 'error' : 'check_circle'}
          </span>
          {toastMsg}
        </div>
      )}

      <style>{`
        @keyframes fadeInScale {
          from { opacity: 0; transform: scale(0.96) translateY(8px); }
          to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
      `}</style>
    </div>
  );
}
