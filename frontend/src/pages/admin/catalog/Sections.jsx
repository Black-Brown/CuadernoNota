import React, { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createSection, deleteSection, getAcademicYears, getGrades, getSections, updateSection } from '../../../api/admin.api';
import useToast from '../../../hooks/useToast';
import { getErrorMessage } from '../../../utils/apiError';
import FilterBar from '../../../components/ui/FilterBar';
import DataTable from '../../../components/ui/DataTable';
import SideDrawer from '../../../components/ui/SideDrawer';
import ConfirmDialog from '../../../components/ui/ConfirmDialog';
import FormField, { inputClass, selectClass } from '../../../components/ui/FormField';
import Toast from '../../../components/ui/Toast';

const EMPTY_FORM = { grade_id: '', academic_year_id: '', name: '', shift: '' };

export default function Sections({ lockedYearId = null, periodId = null, readOnly = false, initialGradeId = '' }) {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [yearFilter, setYearFilter] = useState(lockedYearId ? String(lockedYearId) : '');
  const [gradeFilter, setGradeFilter] = useState(String(initialGradeId));
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ ...EMPTY_FORM, academic_year_id: lockedYearId ? String(lockedYearId) : '' });
  const [error, setError] = useState('');
  const [deleteTarget, setDeleteTarget] = useState(null);

  useEffect(() => {
    if (lockedYearId) setYearFilter(String(lockedYearId));
  }, [lockedYearId]);

  useEffect(() => { setGradeFilter(String(initialGradeId)); }, [initialGradeId]);

  const { data: years } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });
  const { data: grades } = useQuery({ queryKey: ['admin-grades', 'all'], queryFn: () => getGrades({ status: 'all' }) });
  const selectableGrades = grades?.filter((g) => g.active || String(g.id) === String(form.grade_id));

  const params = {};
  if (yearFilter) params.academic_year_id = yearFilter;
  if (gradeFilter) params.grade_id = gradeFilter;
  const { data, isLoading } = useQuery({ queryKey: ['admin-sections', params], queryFn: () => getSections(params) });

  const openCreate = () => {
    if (readOnly) return;
    setEditing(null);
    setForm({ ...EMPTY_FORM, grade_id: gradeFilter, academic_year_id: String(lockedYearId || yearFilter || years?.find((y) => y.active)?.id || '') });
    setError(''); setDrawerOpen(true);
  };
  const openEdit = (section) => {
    if (readOnly) return;
    setEditing(section);
    setForm({ grade_id: section.grade?.id || section.grade_id, academic_year_id: section.academicYear?.id || section.academic_year_id, name: section.name, shift: section.shift });
    setError(''); setDrawerOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: () => editing ? updateSection(editing.id, { ...form, period_id: periodId }) : createSection({ ...form, period_id: periodId }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-sections'] });
      qc.invalidateQueries({ queryKey: ['admin-grades'] });
      qc.invalidateQueries({ queryKey: ['admin-years'] });
      setDrawerOpen(false);
      showToast(editing ? 'Sección actualizada.' : 'Sección creada.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteSection(deleteTarget.id, periodId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-sections'] });
      setDeleteTarget(null);
      showToast('Sección eliminada.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setDeleteTarget(null); },
  });

  return (
    <>
      <p className="mb-4 text-sm leading-6 text-slate-600">Un mismo grado puede tener varias secciones: por ejemplo, 1ro Grado A, 1ro Grado B y 1ro Grado C. Crea una sección por cada grupo y selecciona su año escolar y tanda.</p>
      <div className="mb-4 flex justify-end">
        {!readOnly && (
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">add</span>
            Nueva sección
          </button>
        )}
      </div>

      <FilterBar>
        {!lockedYearId && (
          <select value={yearFilter} onChange={(e) => setYearFilter(e.target.value)} className={`${selectClass} w-auto`}>
            <option value="">Todos los años</option>
            {years?.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
        )}
        <select value={gradeFilter} onChange={(e) => setGradeFilter(e.target.value)} className={`${selectClass} w-auto`}>
          <option value="">Todos los grados</option>
          {grades?.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
        </select>
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={data}
        emptyIcon="domain"
        emptyTitle="No hay secciones que coincidan con los filtros."
        columns={[
          { key: 'grade', label: 'Grado', render: (s) => s.grade?.name },
          { key: 'name', label: 'Sección', render: (s) => <span className="font-bold text-slate-900">{s.name}</span> },
          { key: 'shift', label: 'Tanda' },
          { key: 'year', label: 'Año escolar', render: (s) => (s.academic_year || s.academicYear)?.name },
          { key: 'students_count', label: 'Estudiantes', align: 'center' },
          { key: 'course_offerings_count', label: 'Cursos disponibles', align: 'center' },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (s) => (
              readOnly ? (
                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-300">Bloqueado</span>
              ) : (
                <div className="flex justify-end gap-1">
                  <button onClick={() => openEdit(s)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800">
                    <span className="material-symbols-outlined text-[18px]">edit</span>
                  </button>
                  <button onClick={() => setDeleteTarget(s)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
                    <span className="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </div>
              )
            ),
          },
        ]}
      />

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Editar sección' : 'Nueva sección'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.grade_id || !form.academic_year_id || !form.name || !form.shift} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Grado" required>
            <select className={selectClass} value={form.grade_id} onChange={(e) => setForm({ ...form, grade_id: e.target.value })}>
              <option value="">Seleccionar grado</option>
              {selectableGrades?.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
            </select>
          </FormField>
          {lockedYearId ? (
            <FormField label="Año escolar" hint="Definido por el workspace de período seleccionado.">
              <input className={inputClass} disabled value={years?.find((y) => String(y.id) === String(lockedYearId))?.name || ''} />
            </FormField>
          ) : (
            <FormField label="Año escolar" required>
              <select className={selectClass} value={form.academic_year_id} onChange={(e) => setForm({ ...form, academic_year_id: e.target.value })}>
                <option value="">Seleccionar año</option>
                {years?.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
              </select>
            </FormField>
          )}
          <FormField label="Letra o nombre de la sección" htmlFor="section-name" required hint="Elige A, B o C, o escribe otro identificador de hasta 5 caracteres. No incluyas el nombre del grado.">
            <div className="mb-2 flex gap-2" role="group" aria-label="Letras de sección sugeridas">
              {['A', 'B', 'C'].map((name) => <button key={name} type="button" aria-pressed={form.name === name} onClick={() => setForm({ ...form, name })}
                className={`h-10 w-10 rounded-lg border text-sm font-bold ${form.name === name ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`}>{name}</button>)}
            </div>
            <input id="section-name" className={inputClass} maxLength={5} placeholder="Ej. A" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value.toUpperCase() })} />
          </FormField>
          <FormField label="Tanda" required hint="Ej. Matutina, Vespertina">
            <input className={inputClass} value={form.shift} onChange={(e) => setForm({ ...form, shift: e.target.value })} />
          </FormField>
          {form.grade_id && form.name && <p className="rounded-lg bg-indigo-50 p-3 text-sm font-semibold text-indigo-900">Grupo: {grades?.find((g) => String(g.id) === String(form.grade_id))?.name} · Sección {form.name}{form.shift ? ` · ${form.shift}` : ''}</p>}
        </div>
      </SideDrawer>

      <ConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => deleteMutation.mutate()}
        loading={deleteMutation.isPending}
        tone="danger"
        title="Eliminar sección"
        message={`Se eliminará "${deleteTarget?.name}". Solo es posible si no tiene estudiantes ni cursos asociados.`}
        confirmLabel="Eliminar"
      />

      <Toast toast={toast} />
    </>
  );
}
