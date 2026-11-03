<?php

namespace App\Http\Controllers;

use App\Support\ViewerAccess;
use Carbon\Carbon;
use App\Jobs\SendAbsenceSms;
use App\Models\AbsenceSms;
use App\Models\Attendance;
use App\Models\Student;
use App\Support\ClassChoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Port of views.py: mark_attendance(). This is the most sensitive piece
 * to migrate correctly -- it relies on select_for_update() row locking
 * inside a DB transaction to stop two concurrent submits (teacher +
 * admin, or a double click) from racing into a partial save. Laravel's
 * lockForUpdate() inside DB::transaction() is the direct equivalent and
 * is used the same way below.
 */
class AttendanceController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        $isAdminUser = (bool) $user->is_admin;
        $selectedClass = trim((string) $request->query('class', ''));

        if (!$isAdminUser && !$teacher) {
            return view('attendance.mark', [
                'error' => 'Your account is not linked to any teacher. Ask admin to link it.',
                'isAdminUser' => false,
                'today' => now('Asia/Dhaka')->toDateString(),
            ]);
        }

        $classChoices = $isAdminUser ? $this->classChoices() : $teacher->class_list;
        if (!$isAdminUser && empty($classChoices)) {
            return view('attendance.mark', [
                'error' => 'No class has been assigned to you yet. Please ask the admin to assign one.',
                'isAdminUser' => false,
                'today' => now('Asia/Dhaka')->toDateString(),
            ]);
        }

        $showClassSelector = $isAdminUser || count($classChoices) > 1;
        $currentClass = null;
        if (!$isAdminUser && count($classChoices) === 1) {
            $currentClass = $classChoices[0];
        } elseif (in_array($selectedClass, $classChoices, true)) {
            $currentClass = $selectedClass;
        }

        $today = now('Asia/Dhaka')->toDateString();
        $students = $currentClass ? $this->loadStudents($currentClass, $isAdminUser) : collect();
        $alreadyMarked = $currentClass
            ? Attendance::whereHas('student', fn ($q) => $q->where('class_name', $currentClass))->where('date', $today)->exists()
            : false;

        return view('attendance.mark', [
            'teacher' => $teacher,
            'isAdminUser' => $isAdminUser,
            'today' => $today,
            'classChoices' => $classChoices,
            'allClasses' => collect($classChoices)->map(fn ($c) => ['name' => $c, 'is_selected' => $c === $currentClass]),
            'showClassSelector' => $showClassSelector,
            'currentClass' => $currentClass,
            'students' => $students,
            'alreadyMarked' => $alreadyMarked && !$isAdminUser,
            'classStatuses' => $this->classAttendanceStatuses($classChoices, $today),
            'studentRows' => $this->buildStudentRows($students, $currentClass, $today),
            'saved' => false,
            'smsQueuedCount' => 0,
            'smsWarning' => null,
        ]);
    }
        public function absentReport(Request $request)
    {
        [$isAdminUser, $allowedClasses] = ViewerAccess::allowedClasses(Auth::user());
        if (empty($allowedClasses)) {
            return view('attendance.absent-report', ['noAccess' => true]);
        }

        $classFilter = trim((string) $request->query('class', ''));
        if (!in_array($classFilter, $allowedClasses, true)) {
            $classFilter = $allowedClasses[0];
        }

        $targetDateStr = $this->safeDateOrYesterday($request->query('date', ''));
        $target = Carbon::parse($targetDateStr);

        $totalStudents = Student::withoutGlobalScopes()->where('class_name', $classFilter)->count();
        $markedCount = Attendance::whereHas('student', fn ($q) => $q->where('class_name', $classFilter))
            ->where('date', $targetDateStr)->count();

        $absentees = Attendance::with('student')
            ->whereHas('student', fn ($q) => $q->where('class_name', $classFilter))
            ->where('date', $targetDateStr)->where('is_present', false)
            ->get()
            ->sortBy(fn ($a) => [$a->student->section, $a->student->roll_no])
            ->values();

        return view('attendance.absent-report', [
            'isAdminUser' => $isAdminUser,
            'allowedClasses' => $allowedClasses,
            'classFilter' => $classFilter,
            'targetDate' => $target,
            'prevDate' => $target->copy()->subDay()->toDateString(),
            'nextDate' => $target->copy()->addDay()->toDateString(),
            'isToday' => $targetDateStr === now('Asia/Dhaka')->toDateString(),
            'absentees' => $absentees,
            'absentCount' => $absentees->count(),
            'totalStudents' => $totalStudents,
            'markedCount' => $markedCount,
            'wasTaken' => $markedCount > 0,
            'isComplete' => $totalStudents > 0 && $markedCount === $totalStudents,
        ]);
    }

    private function safeDateOrYesterday(string $value): string
    {
        if ($value) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
            } catch (\Throwable $e) {
            }
        }
        return now('Asia/Dhaka')->subDay()->toDateString();
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        $isAdminUser = (bool) $user->is_admin;
        $today = now('Asia/Dhaka')->toDateString();

        $classChoices = $isAdminUser ? $this->classChoices() : $teacher->class_list;
        $currentClass = trim((string) $request->input('class', ''));
        if (!$isAdminUser && count($classChoices) === 1) {
            $currentClass = $classChoices[0];
        }

        if (!in_array($currentClass, $classChoices, true)) {
            abort(400, 'Invalid class.');
        }

        $alreadyMarked = Attendance::whereHas('student', fn ($q) => $q->where('class_name', $currentClass))
            ->where('date', $today)->exists();
        $canSubmit = $isAdminUser || !$alreadyMarked;

        $saved = false;
        $smsQueuedCount = 0;
        $smsWarning = null;

        if ($canSubmit) {
            $students = $this->loadStudents($currentClass, $isAdminUser)->all();
            $absentStudents = [];

            DB::transaction(function () use ($request, $students, $currentClass, $today, &$absentStudents) {
                // Row-level lock, same purpose as Django's select_for_update():
                // stop a concurrent double-submit from writing a partial set.
                $existing = Attendance::whereHas('student', fn ($q) => $q->where('class_name', $currentClass))
                    ->where('date', $today)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('student_id');

                $rowsToCreate = [];
                $rowsToUpdate = [];
                $timestamp = now();

                foreach ($students as $student) {
                    $status = $request->input("att_{$student->id}", 'present');
                    $isPresent = $status === 'present';
                    $existingAttendance = $existing->get($student->id);

                    if ($existingAttendance) {
                        if ((bool) $existingAttendance->is_present !== $isPresent) {
                            $rowsToUpdate[(int) $student->id] = $isPresent;
                        }
                    } else {
                        $rowsToCreate[] = [
                            'student_id' => $student->id,
                            'date' => $today,
                            'is_present' => $isPresent,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }

                    if (!$isPresent) {
                        $absentStudents[] = $student;
                    }
                }

                if ($rowsToCreate) {
                    Attendance::insert($rowsToCreate);
                }

                if ($rowsToUpdate) {
                    $cases = [];
                    foreach ($rowsToUpdate as $studentId => $isPresent) {
                        $cases[] = 'WHEN ' . (int) $studentId . ' THEN ' . ($isPresent ? 'TRUE' : 'FALSE');
                    }

                    DB::table('attendances')
                        ->where('date', $today)
                        ->whereIn('student_id', array_keys($rowsToUpdate))
                        ->update([
                            'is_present' => DB::raw('CASE student_id ' . implode(' ', $cases) . ' ELSE is_present END'),
                            'updated_at' => $timestamp,
                        ]);
                }
            });

            $attendanceCount = Attendance::whereHas('student', fn ($q) => $q->where('class_name', $currentClass))
                ->where('date', $today)->count();
            $classStudentCount = Student::withoutGlobalScopes()->where('class_name', $currentClass)->count();

            if ($classStudentCount && $attendanceCount === $classStudentCount) {
                $existingSmsStudentIds = AbsenceSms::whereIn('student_id', array_map(fn ($s) => $s->id, $absentStudents))
                    ->where('date', $today)->pluck('student_id')->all();

                foreach ($absentStudents as $student) {
                    if (in_array($student->id, $existingSmsStudentIds, true)) {
                        continue;
                    }
                    $sms = AbsenceSms::create(['student_id' => $student->id, 'date' => $today]);
                    SendAbsenceSms::dispatch($sms->id);
                    $smsQueuedCount++;
                }
                $saved = true;
            } else {
                $saved = true;
                $smsWarning = 'Attendance was saved, but SMS was not sent because the class attendance is incomplete.';
            }
        }

        return redirect()->route('attendance.show', ['class' => $currentClass])
            ->with('saved', $saved)
            ->with('sms_queued_count', $smsQueuedCount)
            ->with('sms_warning', $smsWarning);
    }

    private function loadStudents(string $className, bool $isAdmin)
    {
        $q = Student::withoutGlobalScopes()->where('class_name', $className);
        return $isAdmin ? $q->orderBy('section')->orderBy('roll_no')->get() : $q->orderBy('roll_no')->get();
    }

    private function classChoices(): array
    {
        return ClassChoices::all();
    }

    private function classAttendanceStatuses(array $classNames, string $date): array
    {
        $studentCounts = Student::withoutGlobalScopes()->whereIn('class_name', $classNames)
            ->selectRaw('class_name, count(*) as total')->groupBy('class_name')->pluck('total', 'class_name');
        $attendanceCounts = Attendance::whereHas('student', fn ($q) => $q->whereIn('class_name', $classNames))
            ->where('date', $date)
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->selectRaw('students.class_name, count(*) as total')->groupBy('students.class_name')
            ->pluck('total', 'class_name');

        return collect($classNames)->map(function ($name) use ($studentCounts, $attendanceCounts) {
            $studentCount = $studentCounts[$name] ?? 0;
            $attendanceCount = $attendanceCounts[$name] ?? 0;
            return [
                'name' => $name,
                'student_count' => $studentCount,
                'attendance_count' => $attendanceCount,
                'is_complete' => $studentCount > 0 && $attendanceCount === $studentCount,
            ];
        })->all();
    }

    private function buildStudentRows($students, ?string $currentClass, string $today): array
    {
        if (!$currentClass) {
            return [];
        }
        $attendanceMap = Attendance::whereHas('student', fn ($q) => $q->where('class_name', $currentClass))
            ->where('date', $today)->pluck('is_present', 'student_id');

        return $students->map(function ($student) use ($attendanceMap) {
            $marked = $attendanceMap->has($student->id);
            $isPresent = $attendanceMap->get($student->id, true);
            return [
                'student' => $student,
                'is_marked' => $marked,
                'is_present' => $isPresent,
                'present_checked' => (!$marked || $isPresent) ? 'checked' : '',
                'absent_checked' => ($marked && !$isPresent) ? 'checked' : '',
            ];
        })->all();
    }
}
