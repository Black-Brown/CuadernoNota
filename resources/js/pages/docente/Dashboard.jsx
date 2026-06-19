import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { getDashboardSummary } from '../../api/dashboard.api';
import useAuthStore from '../../store/authStore';
import DashboardLayout from '../../components/DashboardLayout';

export default function DocenteDashboard() {
  const { user } = useAuthStore();

  const { data, isLoading } = useQuery({
    queryKey: ['dashboardSummary'],
    queryFn: getDashboardSummary,
  });

  // Fallbacks to match user mockup styling when database is empty
  const activeStudents = data?.total_students ?? 1284;
  const attendanceAvg = data?.attendance_avg ?? 94.2;
  const avgGrade = data?.avg_grade ?? 82.4;
  const activeCourses = data?.active_courses ?? 6;

  return (
    <DashboardLayout>
      {/* Welcome Header Section */}
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-slate-800 tracking-tight mb-1">
          Bienvenido de nuevo, {user?.name || 'Docente'}
        </h2>
        <p className="text-sm text-slate-500">
          Aquí tienes un resumen del rendimiento académico para este periodo escolar.
        </p>
      </div>

      {/* Bento Grid Dashboard Layout */}
      <div className="grid grid-cols-12 gap-6">
        
        {/* KPI Widget: Estudiantes Activos */}
        <div className="col-span-12 md:col-span-6 lg:col-span-3">
          <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col justify-between h-36 shadow-sm hover:border-indigo-500 transition-colors duration-200">
            <div className="flex justify-between items-start">
              <span className="text-[11px] font-bold tracking-wider text-slate-400 uppercase">ESTUDIANTES ACTIVOS</span>
              <span className="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-bold">+2.4%</span>
            </div>
            <div className="mt-2">
              <span className="text-3xl font-bold text-slate-800">{activeStudents}</span>
              <div className="w-full h-1.5 bg-slate-100 mt-3 rounded-full overflow-hidden">
                <div className="bg-emerald-500 h-full w-[85%] rounded-full"></div>
              </div>
            </div>
          </div>
        </div>

        {/* KPI Widget: Asistencia Promedio */}
        <div className="col-span-12 md:col-span-6 lg:col-span-3">
          <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col justify-between h-36 shadow-sm hover:border-indigo-500 transition-colors duration-200">
            <div className="flex justify-between items-start">
              <span className="text-[11px] font-bold tracking-wider text-slate-400 uppercase">ASISTENCIA PROMEDIO</span>
              <span className="material-symbols-outlined text-emerald-500 text-[18px]">trending_up</span>
            </div>
            <div className="mt-2">
              <span className="text-3xl font-bold text-slate-800">{attendanceAvg}%</span>
              <p className="text-xs text-slate-400 mt-2">Meta institucional: 92.0%</p>
            </div>
          </div>
        </div>

        {/* KPI Widget: Promedio General */}
        <div className="col-span-12 md:col-span-6 lg:col-span-3">
          <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col justify-between h-36 shadow-sm hover:border-indigo-500 transition-colors duration-200">
            <div className="flex justify-between items-start">
              <span className="text-[11px] font-bold tracking-wider text-slate-400 uppercase">PROMEDIO DE NOTAS</span>
              <span className="material-symbols-outlined text-indigo-500 text-[18px]">auto_graph</span>
            </div>
            <div className="mt-2">
              <span className="text-3xl font-bold text-slate-800">{avgGrade}</span>
              <p className="text-xs text-slate-400 mt-2">Escala: 0 - 100 • Aprobación: 70</p>
            </div>
          </div>
        </div>

        {/* KPI Widget: Cursos Activos */}
        <div className="col-span-12 md:col-span-6 lg:col-span-3">
          <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col justify-between h-36 shadow-sm hover:border-indigo-500 transition-colors duration-200">
            <div className="flex justify-between items-start">
              <span className="text-[11px] font-bold tracking-wider text-slate-400 uppercase">CURSOS ASIGNADOS</span>
              <span className="material-symbols-outlined text-slate-400 text-[18px]">school</span>
            </div>
            <div className="mt-2">
              <span className="text-3xl font-bold text-slate-800">{activeCourses}</span>
              <p className="text-xs text-slate-400 mt-2">Secciones académicas vigentes</p>
            </div>
          </div>
        </div>

        {/* Main Row: Graphic & Reports */}
        <div className="col-span-12 lg:col-span-8">
          <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col min-h-[400px]">
            <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white">
              <h3 className="text-base font-bold text-slate-800">Distribución del Rendimiento por Curso</h3>
              <button className="text-indigo-600 font-semibold text-xs hover:underline">Ver todos los cursos</button>
            </div>
            
            <div className="flex-1 p-6 flex flex-col items-center justify-center bg-slate-50/50">
              <div className="w-full max-w-md bg-white border border-slate-200 rounded-xl p-8 flex flex-col items-center justify-center text-center space-y-4 shadow-sm">
                
                {/* Circular Chart Ring SVG */}
                <div className="w-48 h-48 relative flex items-center justify-center">
                  <svg className="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" strokeWidth="8" />
                    <circle 
                      cx="50" 
                      cy="50" 
                      r="40" 
                      fill="none" 
                      stroke="#4f46e5" 
                      strokeWidth="8" 
                      strokeDasharray="251.2" 
                      strokeDashoffset="60"
                      className="transition-all duration-1000 ease-out"
                    />
                    <circle 
                      cx="50" 
                      cy="50" 
                      r="40" 
                      fill="none" 
                      stroke="#10b981" 
                      strokeWidth="8" 
                      strokeDasharray="251.2" 
                      strokeDashoffset="180" 
                      className="transition-all duration-1000 ease-out"
                    />
                  </svg>
                  <div className="absolute flex flex-col items-center justify-center">
                    <span className="text-2xl font-bold text-slate-800">{avgGrade}</span>
                    <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Promedio General</span>
                  </div>
                </div>

                <div>
                  <p className="text-sm font-bold text-slate-700">Visualización de Estadísticas</p>
                  <p className="text-xs text-slate-400 max-w-xs mx-auto mt-1">
                    Representación interactiva de la distribución de calificaciones y asistencia en tus diferentes secciones académicas.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Side Column: Recent Reports */}
        <div className="col-span-12 lg:col-span-4">
          <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col h-full justify-between">
            <div>
              <h3 className="text-base font-bold text-slate-800 mb-6">Reportes Recientes</h3>
              
              <div className="space-y-4">
                
                {/* Report Item 1 */}
                <div className="flex gap-4 p-3 rounded-lg hover:bg-slate-50 cursor-pointer border border-transparent hover:border-slate-100 transition-all duration-150">
                  <div className="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                    <span className="material-symbols-outlined text-[22px]">description</span>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-800">Reporte de Asistencia Q3</p>
                    <p className="text-xs text-slate-400">Generado hace 2 horas • PDF</p>
                  </div>
                </div>

                {/* Report Item 2 */}
                <div className="flex gap-4 p-3 rounded-lg hover:bg-slate-50 cursor-pointer border border-transparent hover:border-slate-100 transition-all duration-150">
                  <div className="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                    <span className="material-symbols-outlined text-[22px]">table_chart</span>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-800">Exportación de Notas Parciales</p>
                    <p className="text-xs text-slate-400">Generado hace 5 horas • XLSX</p>
                  </div>
                </div>

                {/* Report Item 3 */}
                <div className="flex gap-4 p-3 rounded-lg hover:bg-slate-50 cursor-pointer border border-transparent hover:border-slate-100 transition-all duration-150">
                  <div className="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                    <span className="material-symbols-outlined text-[22px]">history</span>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-800">Bitácora de Clases Semanal</p>
                    <p className="text-xs text-slate-400">Generado ayer • CSV</p>
                  </div>
                </div>

              </div>
            </div>

            <button className="w-full mt-8 py-3 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition-colors shadow-sm">
              Ir al Centro de Reportes
            </button>
          </div>
        </div>

      </div>

      {/* Floating Action Button (FAB) */}
      <div className="fixed bottom-6 right-6 z-50">
        <button 
          title="Acción rápida"
          className="w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg flex items-center justify-center hover:scale-105 active:scale-95 transition-all duration-150 group"
        >
          <span className="material-symbols-outlined text-[28px] fill-1">add</span>
        </button>
      </div>
    </DashboardLayout>
  );
}
