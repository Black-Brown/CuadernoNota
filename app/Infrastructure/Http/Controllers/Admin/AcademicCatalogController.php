<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\AcademicYear;
use App\Infrastructure\Models\ActivityTemplate;
use App\Infrastructure\Models\Grade;
use App\Infrastructure\Models\Period;
use App\Infrastructure\Models\Section;
use App\Infrastructure\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicCatalogController extends Controller
{
    public function years(): JsonResponse
    {
        return response()->json(AcademicYear::withCount(['periods', 'sections'])->orderByDesc('start_date')->get());
    }

    public function storeYear(Request $request): JsonResponse
    {
        $data = $request->validate($this->yearRules());
        $year = DB::transaction(function () use ($data) {
            if ($data['active'] ?? false) AcademicYear::query()->update(['active' => false]);
            return AcademicYear::create($data);
        });
        return response()->json($year, 201);
    }

    public function updateYear(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validate($this->yearRules($academicYear));
        DB::transaction(function () use ($data, $academicYear) {
            if ($data['active'] ?? false) AcademicYear::whereKeyNot($academicYear->id)->update(['active' => false]);
            $academicYear->update($data);
        });
        return response()->json($academicYear->fresh());
    }

    public function destroyYear(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->sections()->exists() || $academicYear->periods()->exists()) $this->inUse('El año escolar tiene períodos o secciones asociados.');
        $academicYear->delete();
        return response()->json(['message' => 'Año escolar eliminado.']);
    }

    public function periods(AcademicYear $academicYear): JsonResponse
    {
        return response()->json($academicYear->periods()->orderBy('number')->get());
    }

    public function storePeriod(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validate($this->periodRules($academicYear));
        return response()->json($academicYear->periods()->create($data), 201);
    }

    public function updatePeriod(Request $request, Period $period): JsonResponse
    {
        $data = $request->validate($this->periodRules($period->academicYear, $period));
        $period->update($data);
        return response()->json($period->fresh());
    }

    public function destroyPeriod(Period $period): JsonResponse
    {
        if ($period->activityScores()->exists() || $period->periodGrades()->exists()) $this->inUse('El período contiene calificaciones.');
        $period->delete();
        return response()->json(['message' => 'Período eliminado.']);
    }

    public function grades(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        return response()->json(Grade::withCount(['sections', 'subjects'])
            ->with(['subjects:id,name,code,active', 'sections:id,grade_id,name,shift,academic_year_id', 'sections.academicYear:id,name'])
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('sort_order')->get());
    }

    public function storeGrade(Request $request): JsonResponse
    {
        $grade = Grade::create($request->validate($this->gradeRules()));
        return response()->json($grade, 201);
    }

    public function updateGrade(Request $request, Grade $grade): JsonResponse
    {
        $grade->update($request->validate($this->gradeRules($grade)));
        return response()->json($grade->fresh());
    }

    public function gradeDeletionCheck(Grade $grade): JsonResponse
    {
        $sections = $grade->sections()->count();
        $subjects = $grade->subjects()->count();
        return response()->json([
            'can_delete' => $sections === 0 && $subjects === 0,
            'relations' => ['sections' => $sections, 'subjects' => $subjects],
        ]);
    }

    public function destroyGrade(Grade $grade): JsonResponse
    {
        if ($grade->sections()->exists() || $grade->subjects()->exists()) $this->inUse('El grado tiene secciones o materias asociadas.');
        $grade->delete();
        return response()->json(['message' => 'Grado eliminado.']);
    }

    public function deactivateGrade(Grade $grade): JsonResponse
    {
        $grade->update(['active' => false]);
        return response()->json(['message' => 'El grado se desactivó correctamente.']);
    }

    public function reactivateGrade(Grade $grade): JsonResponse
    {
        $grade->update(['active' => true]);
        return response()->json(['message' => 'El grado se reactivó correctamente.']);
    }

    public function sections(Request $request): JsonResponse
    {
        return response()->json(Section::with(['grade:id,name,level', 'academicYear:id,name,active'])
            ->withCount(['students', 'courseOfferings'])
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->grade_id, fn ($q, $id) => $q->where('grade_id', $id))
            ->orderBy('academic_year_id')->orderBy('grade_id')->orderBy('name')->get());
    }

    public function storeSection(Request $request): JsonResponse
    {
        $data = $request->validate($this->sectionRules());
        $periodId = $data['period_id'] ?? null;
        unset($data['period_id']);
        $this->assertPeriodOpenForLock($periodId);
        $section = DB::transaction(function () use ($data) {
            $section = Section::create($data);
            $subjectIds = DB::table('grade_subjects')->where('grade_id', $section->grade_id)->pluck('subject_id');
            foreach ($subjectIds as $subjectId) $section->courseOfferings()->create(['subject_id' => $subjectId, 'active' => true]);
            return $section;
        });
        return response()->json($section->load(['grade', 'academicYear']), 201);
    }

    public function updateSection(Request $request, Section $section): JsonResponse
    {
        $data = $request->validate($this->sectionRules($section));
        $periodId = $data['period_id'] ?? null;
        unset($data['period_id']);
        $this->assertPeriodOpenForLock($periodId);
        $section->update($data);
        return response()->json($section->fresh(['grade', 'academicYear']));
    }

    public function destroySection(Request $request, Section $section): JsonResponse
    {
        $this->assertPeriodOpenForLock($request->integer('period_id') ?: null);
        if ($section->students()->exists() || $section->courseOfferings()->exists()) $this->inUse('La sección tiene estudiantes o cursos asociados.');
        $section->delete();
        return response()->json(['message' => 'Sección eliminada.']);
    }

    public function periodActivitySummary(Period $period): JsonResponse
    {
        return response()->json(DB::table('course_activities')
            ->join('activity_templates', 'course_activities.activity_template_id', '=', 'activity_templates.id')
            ->where('course_activities.period_id', $period->id)
            ->where('course_activities.status', 'active')
            ->select('activity_templates.id', 'activity_templates.name', 'activity_templates.icon', DB::raw('COUNT(DISTINCT course_activities.course_offering_id) as course_count'))
            ->groupBy('activity_templates.id', 'activity_templates.name', 'activity_templates.icon')
            ->orderBy('activity_templates.name')
            ->get());
    }

    public function subjects(): JsonResponse
    {
        return response()->json(Subject::with('grades:id,name,level')->withCount('courseOfferings')->orderBy('name')->get());
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $data = $request->validate($this->subjectRules());
        $subject = DB::transaction(fn () => $this->saveSubject(new Subject(), $data));
        return response()->json($subject, 201);
    }

    public function updateSubject(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate($this->subjectRules($subject));
        return response()->json(DB::transaction(fn () => $this->saveSubject($subject, $data)));
    }

    public function deactivateSubject(Subject $subject): JsonResponse
    {
        DB::transaction(function () use ($subject) {
            $subject->update(['active' => false]);
            $subject->courseOfferings()->update(['active' => false]);
        });
        return response()->json(['message' => 'Materia desactivada; el historial fue conservado.']);
    }

    public function templates(): JsonResponse
    {
        return response()->json(ActivityTemplate::withCount('courseActivities')->orderBy('name')->get());
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $template = DB::transaction(function () use ($request) {
            $template = ActivityTemplate::create($request->validate($this->templateRules()));
            $this->propagateTemplate($template);
            return $template;
        });
        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, ActivityTemplate $activityTemplate): JsonResponse
    {
        $activityTemplate->update($request->validate($this->templateRules($activityTemplate)));
        return response()->json($activityTemplate->fresh());
    }

    public function deactivateTemplate(ActivityTemplate $activityTemplate): JsonResponse
    {
        $activityTemplate->update(['active' => false]);
        return response()->json(['message' => 'Actividad base desactivada; el historial fue conservado.']);
    }

    private function saveSubject(Subject $subject, array $data): Subject
    {
        $gradeIds = $data['grade_ids']; unset($data['grade_ids']);
        $subject->fill($data)->save();
        $subject->grades()->sync($gradeIds);
        $sectionIds = Section::whereIn('grade_id', $gradeIds)->pluck('id');
        foreach ($sectionIds as $sectionId) DB::table('course_offerings')->updateOrInsert(
            ['section_id' => $sectionId, 'subject_id' => $subject->id],
            ['active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('course_offerings')->join('sections', 'course_offerings.section_id', '=', 'sections.id')
            ->where('course_offerings.subject_id', $subject->id)
            ->whereNotIn('sections.grade_id', $gradeIds)
            ->update(['course_offerings.active' => false, 'course_offerings.updated_at' => now()]);
        return $subject->load('grades:id,name,level');
    }

    private function propagateTemplate(ActivityTemplate $template): void
    {
        DB::table('course_offerings')->join('sections', 'course_offerings.section_id', '=', 'sections.id')
            ->where('course_offerings.active', true)
            ->select('course_offerings.id', 'sections.academic_year_id')
            ->orderBy('course_offerings.id')->each(function (object $offering) use ($template) {
                foreach (DB::table('periods')->where('academic_year_id', $offering->academic_year_id)->pluck('id') as $periodId) {
                    DB::table('course_activities')->insertOrIgnore([
                        'course_offering_id' => $offering->id, 'period_id' => $periodId,
                        'activity_template_id' => $template->id, 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    private function inUse(string $message): never
    {
        throw ValidationException::withMessages(['resource' => $message]);
    }

    private function assertPeriodOpenForLock(?int $periodId): void
    {
        if (!$periodId) return;
        $period = Period::find($periodId);
        if ($period && $period->status !== 'open') {
            $this->inUse('El período seleccionado está cerrado; no se pueden modificar secciones dentro de este workspace.');
        }
    }

    private function yearRules(?AcademicYear $year = null): array { return [
        'name' => [$year ? 'sometimes' : 'required', 'string', 'max:20', Rule::unique('academic_years')->ignore($year?->id)],
        'start_date' => [$year ? 'sometimes' : 'required', 'date'],
        'end_date' => [$year ? 'sometimes' : 'required', 'date', 'after:start_date'],
        'active' => ['sometimes', 'boolean'],
    ]; }
    private function periodRules(AcademicYear $year, ?Period $period = null): array { return [
        'number' => [$period ? 'sometimes' : 'required', 'integer', 'between:1,4', Rule::unique('periods')->where('academic_year_id', $year->id)->ignore($period?->id)],
        'name' => [$period ? 'sometimes' : 'required', 'string', 'max:30'], 'months' => ['required', 'string', 'max:60'],
        'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        'status' => ['sometimes', Rule::in(['open', 'in_review', 'closed'])],
    ]; }
    private function gradeRules(?Grade $grade = null): array { return [
        'name' => [$grade ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('grades')->ignore($grade?->id)],
        'level' => [$grade ? 'sometimes' : 'required', 'string', 'max:20'], 'sort_order' => [$grade ? 'sometimes' : 'required', 'integer', 'between:1,127'],
    ]; }
    private function sectionRules(?Section $section = null): array { return [
        'grade_id' => [$section ? 'sometimes' : 'required', 'exists:grades,id'], 'academic_year_id' => [$section ? 'sometimes' : 'required', 'exists:academic_years,id'],
        'name' => [$section ? 'sometimes' : 'required', 'string', 'max:5'], 'shift' => [$section ? 'sometimes' : 'required', 'string', 'max:15'],
        'period_id' => ['nullable', 'exists:periods,id'],
    ]; }
    private function subjectRules(?Subject $subject = null): array { return [
        'name' => [$subject ? 'sometimes' : 'required', 'string', 'max:60', Rule::unique('subjects')->ignore($subject?->id)],
        'code' => [$subject ? 'sometimes' : 'required', 'string', 'max:10', Rule::unique('subjects')->ignore($subject?->id)],
        'active' => ['sometimes', 'boolean'], 'grade_ids' => ['required', 'array', 'min:1'], 'grade_ids.*' => ['integer', 'distinct', 'exists:grades,id'],
    ]; }
    private function templateRules(?ActivityTemplate $template = null): array { return [
        'name' => [$template ? 'sometimes' : 'required', 'string', 'max:80', Rule::unique('activity_templates')->ignore($template?->id)],
        'icon' => ['nullable', 'string', 'max:50'], 'active' => ['sometimes', 'boolean'],
    ]; }
}
