// Presentation from master: docente Workspace and ManageActivitiesModal.
export const BASE_ACTIVITY_PRESENTATION = {
  Proyectos: { icon: 'account_tree', color: 'bg-blue-50 text-blue-600', desc: 'Evaluación basada en competencias colaborativas.' },
  Examen: { icon: 'assignment', color: 'bg-purple-50 text-purple-600', desc: 'Pruebas sumativas periódicas.' },
  Tareas: { icon: 'edit_note', color: 'bg-amber-50 text-amber-600', desc: 'Seguimiento de trabajo autónomo.' },
  Ensayo: { icon: 'article', color: 'bg-rose-50 text-rose-600', desc: 'Análisis crítico y redacción científica.' },
  'Producción en aula': { icon: 'school', color: 'bg-teal-50 text-teal-600', desc: 'Evidencias de trabajo diario y participación.' },
  Diagnósticas: { icon: 'fact_check', color: 'bg-emerald-50 text-emerald-600', desc: 'Evaluación inicial de conocimientos previos.' },
};

export function getActivityPresentation(activity) {
  const base = BASE_ACTIVITY_PRESENTATION[activity.name];
  // Older templates were stored with the same generic icon for every activity.
  const customIcon = activity.icon && !['assignment', 'add_task'].includes(activity.icon);
  return {
    icon: customIcon ? activity.icon : base?.icon || activity.icon || 'add_task',
    color: base?.color || 'bg-slate-50 text-slate-600',
    desc: activity.description || base?.desc || 'Actividad de evaluación.',
  };
}
