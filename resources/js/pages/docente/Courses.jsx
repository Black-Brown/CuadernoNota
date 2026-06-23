import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import DashboardLayout from '../../components/DashboardLayout';

export default function Courses() {
  const { data, isLoading } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
  });

  const rawCourses = data?.courses || [];

  // Adapt database courses to mockup visual styles, or fallback to mockup data
  const coursesList = rawCourses.length > 0
    ? rawCourses.map((c, index) => ({
        id: `${c.section_id}-${c.subject_id}`,
        title: `${c.grade_name} ${c.section_name} - ${c.subject_name}`,
        description: index === 0 
          ? "Álgebra avanzada, fundamentos de geometría e interpretación de datos." 
          : "Introducción a la mecánica cuántica, electromagnetismo y dinámica clásica.",
        students: 30,
        period: "Q3 Activo",
        updated: "Hace 2h",
        status: index % 2 === 0 ? "Oficial" : "Borrador",
        image: index % 2 === 0 
          ? "https://lh3.googleusercontent.com/aida-public/AB6AXuDbjTvUi8Aah8y8gz1gx6PNlfMJttkDMAnOOxP4dPWv5aiFZ3f-Qz2j1CwAKW_qiJ7x9g9WRBMzV8chrtGh_oXVtf41GkqCVGlMrB9K6ILugWUNTpqZyZIEkT2Rm9d3rfS9c67slWva_t2EeR_noQuNJ-9PXfv8P9lhfG0f5GNRRZGX0RZBRqGY8mDSY6Tne853ECs-cNR6Eiok9TJ4-mmlNYDbwk_IxbiGxr81npI9ltCtIGpBKWbwyseW3e0wAYODNkXKxleXzrRb"
          : "https://lh3.googleusercontent.com/aida-public/AB6AXuBshAQWtIDc922rtfrmzkciPeox01ikPQPZ4PbImonLfbFNZuJKP-3udwF9k0_muiDVG40IB41DT6u8g44_B7TI4IM0RxVInz0pX3RXjK5rLCfpT_HEmTyFuj-wcYeUulqRJ1X62Y8PXJQmDnWuS40h79-CAxyhL60zMopWOBbuv8UPSWSmyMOECIiq1fvUL4V6fVFj8ROz41RKGVOr2Mu95WEb70Yw0WiWLJVqLhEeaUD92PmMoF2b4WXaKIhzc-0w-_m01oj0QRw0"
      }))
    : [
        {
          id: 'demo-1',
          title: "1ro Secundaria A - Matemática",
          description: "Álgebra avanzada, fundamentos de geometría e interpretación de datos.",
          students: 32,
          period: "Q3 Activo",
          updated: "Hace 2h",
          status: "Oficial",
          image: "https://lh3.googleusercontent.com/aida-public/AB6AXuDbjTvUi8Aah8y8gz1gx6PNlfMJttkDMAnOOxP4dPWv5aiFZ3f-Qz2j1CwAKW_qiJ7x9g9WRBMzV8chrtGh_oXVtf41GkqCVGlMrB9K6ILugWUNTpqZyZIEkT2Rm9d3rfS9c67slWva_t2EeR_noQuNJ-9PXfv8P9lhfG0f5GNRRZGX0RZBRqGY8mDSY6Tne853ECs-cNR6Eiok9TJ4-mmlNYDbwk_IxbiGxr81npI9ltCtIGpBKWbwyseW3e0wAYODNkXKxleXzrRb"
        },
        {
          id: 'demo-2',
          title: "4to Secundaria B - Física Avanzada",
          description: "Introducción a la mecánica cuántica, electromagnetismo y dinámica clásica.",
          students: 28,
          period: "Q3 Prep",
          updated: "Hace un momento",
          status: "Borrador",
          image: "https://lh3.googleusercontent.com/aida-public/AB6AXuBshAQWtIDc922rtfrmzkciPeox01ikPQPZ4PbImonLfbFNZuJKP-3udwF9k0_muiDVG40IB41DT6u8g44_B7TI4IM0RxVInz0pX3RXjK5rLCfpT_HEmTyFuj-wcYeUulqRJ1X62Y8PXJQmDnWuS40h79-CAxyhL60zMopWOBbuv8UPSWSmyMOECIiq1fvUL4V6fVFj8ROz41RKGVOr2Mu95WEb70Yw0WiWLJVqLhEeaUD92PmMoF2b4WXaKIhzc-0w-_m01oj0QRw0"
        }
      ];

  // Bento Stats
  const activeCoursesCount = rawCourses.length > 0 ? String(rawCourses.length).padStart(2, '0') : '02';
  const totalStudents = rawCourses.length > 0 ? (rawCourses.length * 30) : 60;
  const avgGrade = '84.2';
  const attendanceAvg = '92%';

  return (
    <DashboardLayout>
      {/* Header Section */}
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-slate-900 tracking-tight mb-1">Mis Cursos</h2>
        <p className="text-sm text-slate-500 max-w-2xl">
          Administra calificaciones, asistencia y rendimiento académico para tus secciones asignadas.
        </p>
      </div>

      {/* KPI Grid (Bento Style) */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        {/* KPI 1 */}
        <div className="bg-white p-6 border border-slate-200 rounded-xl flex flex-col gap-2 shadow-sm">
          <span className="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CURSOS ACTIVOS</span>
          <div className="flex items-end justify-between">
            <span className="text-3xl font-bold text-slate-800">{activeCoursesCount}</span>
            <div className="text-emerald-600 flex items-center gap-1 text-xs font-semibold">
              <span className="material-symbols-outlined text-[16px] fill-1">check_circle</span>
              <span>Estado OK</span>
            </div>
          </div>
        </div>

        {/* KPI 2 */}
        <div className="bg-white p-6 border border-slate-200 rounded-xl flex flex-col gap-2 shadow-sm">
          <span className="text-[11px] font-bold text-slate-400 tracking-wider uppercase">ESTUDIANTES TOTALES</span>
          <div className="flex items-end justify-between">
            <span className="text-3xl font-bold text-slate-800">{totalStudents}</span>
            <div className="flex -space-x-2">
              <div className="w-6 h-6 rounded-full bg-slate-200 border-2 border-white"></div>
              <div className="w-6 h-6 rounded-full bg-indigo-100 border-2 border-white"></div>
              <div className="w-6 h-6 rounded-full bg-indigo-200 border-2 border-white"></div>
            </div>
          </div>
        </div>

        {/* KPI 3 */}
        <div className="bg-white p-6 border border-slate-200 rounded-xl flex flex-col gap-2 shadow-sm">
          <span className="text-[11px] font-bold text-slate-400 tracking-wider uppercase">NOTA PROMEDIO</span>
          <div className="flex items-end justify-between">
            <span className="text-3xl font-bold text-slate-800">{avgGrade}</span>
            <div className="text-amber-600 flex items-center gap-1 text-xs font-semibold">
              <span className="material-symbols-outlined text-[16px]">trending_up</span>
              <span>+1.2%</span>
            </div>
          </div>
        </div>

        {/* KPI 4 */}
        <div className="bg-white p-6 border border-slate-200 rounded-xl flex flex-col gap-2 shadow-sm">
          <span className="text-[11px] font-bold text-slate-400 tracking-wider uppercase">PROM. ASISTENCIA</span>
          <div className="flex items-end justify-between">
            <span className="text-3xl font-bold text-slate-800">{attendanceAvg}</span>
            <div className="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
              <div className="bg-emerald-500 h-full w-[92%] rounded-full"></div>
            </div>
          </div>
        </div>

      </div>

      {/* Course Cards (Modern Grid) */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        {coursesList.map((course) => (
          <div 
            key={course.id} 
            className="group bg-white border border-slate-200 hover:border-slate-800 rounded-xl transition-all duration-300 overflow-hidden flex flex-col shadow-sm"
          >
            {/* Image Header */}
            <div className="relative h-32 overflow-hidden bg-slate-900">
              <img 
                alt="Visualización del curso" 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-90" 
                src={course.image}
              />
              <div className="absolute top-4 left-4">
                <span className={`px-3 py-1 rounded text-[10px] font-bold uppercase tracking-wider text-white shadow-sm ${
                  course.status === 'Oficial' ? 'bg-emerald-500' : 'bg-slate-500'
                }`}>
                  {course.status}
                </span>
              </div>
            </div>

            {/* Card Content */}
            <div className="p-5 flex-grow flex flex-col">
              <div className="mb-4">
                <div className="flex justify-between items-start gap-2 mb-1">
                  <h3 className="text-base font-bold text-slate-800 group-hover:text-indigo-600 transition-colors leading-snug">
                    {course.title}
                  </h3>
                  <button className="text-slate-400 hover:text-slate-700 shrink-0">
                    <span className="material-symbols-outlined text-[20px]">more_vert</span>
                  </button>
                </div>
                <p className="text-xs text-slate-500 line-clamp-2">
                  {course.description}
                </p>
              </div>

              {/* Course details */}
              <div className="grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 mb-5 text-center">
                <div>
                  <p className="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Estudiantes</p>
                  <p className="text-xs font-semibold text-slate-700">{course.students}</p>
                </div>
                <div>
                  <p className="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Periodo</p>
                  <p className="text-xs font-semibold text-slate-700">{course.period}</p>
                </div>
                <div>
                  <p className="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Actualizado</p>
                  <p className="text-xs font-semibold text-slate-700">{course.updated}</p>
                </div>
              </div>

              <Link 
                to={`/docente/courses/${course.id.replace('-', '/')}`}
                className="w-full mt-auto bg-slate-950 text-white py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-slate-900 active:scale-[0.98] transition-all flex items-center justify-center gap-1.5 group/btn text-center justify-center"
              >
                <span>Abrir Espacio</span>
                <span className="material-symbols-outlined text-[16px] group-hover/btn:translate-x-1 transition-transform">
                  arrow_forward
                </span>
              </Link>

            </div>
          </div>
        ))}

      </div>

    </DashboardLayout>
  );
}
