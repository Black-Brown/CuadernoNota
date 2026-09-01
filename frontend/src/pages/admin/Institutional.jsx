import React from 'react';
import { useSearchParams } from 'react-router-dom';
import PageHeader from '../../components/ui/PageHeader';
import Years from './catalog/Years';
import Grades from './catalog/Grades';
import Subjects from './catalog/Subjects';
import ActivityTemplates from './catalog/ActivityTemplates';
import Sections from './catalog/Sections';

const TABS = [
  { key: 'years', label: 'Años escolares', icon: 'calendar_month', Component: Years },
  { key: 'grades', label: 'Grados', icon: 'stairs', Component: Grades },
  { key: 'sections', label: 'Secciones', icon: 'domain', Component: Sections },
  { key: 'subjects', label: 'Materias', icon: 'menu_book', Component: Subjects },
  { key: 'templates', label: 'Actividades base', icon: 'add_task', Component: ActivityTemplates },
];

export default function Institutional() {
  const [params, setParams] = useSearchParams();
  const activeKey = TABS.some((t) => t.key === params.get('tab')) ? params.get('tab') : 'years';
  const active = TABS.find((t) => t.key === activeKey);

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Configuración institucional', active.label]}
        title="Configuración institucional"
        description="Configura años escolares, grados, sus secciones A/B/C, materias y actividades base. Las secciones pertenecen a un grado y a un año escolar."
      />

      <div className="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200">
        {TABS.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setParams({ tab: tab.key })}
            className={`flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-xs font-bold transition-colors ${
              tab.key === activeKey ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            <span className="material-symbols-outlined text-[18px]">{tab.icon}</span>
            {tab.label}
          </button>
        ))}
      </div>

      <active.Component initialGradeId={params.get('grade') || ''} />
    </>
  );
}
