<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Support\ClassChoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Port of the read-only history/report views + Excel exports in
 * views.py (attendance_history, class_history, teacher_attendance_history,
 * student_history_detail, teacher_history_detail, export_attendance,
 * export_attendance_month, export_teacher_attendance).
 */
class HistoryController extends Controller
{
    private function safeDate(?string $value): string
    {
        try {
            if ($value) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->toDateString();
            }
        } catch (\Throwable $e) {
        }
        return now('Asia/Dhaka')->toDateString();
    }

    private function monthBounds(?string $monthStr): array
    {
        if ($monthStr && preg_match('/^(\d{4})-(\d{1,2})$/', $monthStr, $m) && $m[2] >= 1 && $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2], sprintf('%04d-%02d', $m[1], $m[2])];
        }
        $today = now('Asia/Dhaka');
        return [$today->year, $today->month, $today->format('Y-m')];
    }

    /** Admin: any class, read-only report by day or month. */
    public function attendanceHistory(Request $request)
    {
        $classNames = ClassChoices::all();
        $classFilter = trim((string) $request->query('class', ''));
        $allSelected = in_array($classFilter, ['__all__', 'all'], true);
        $rollFilter = trim((string) $request->query('roll', ''));
        $viewMode = $request->query('view') === 'month' ? 'month' : 'day';

        $context = [
            'allClasses' => collect($classNames)->map(fn ($c) => ['name' => $c, 'is_selected' => $c === $classFilter]),
            'classFilter' => $classFilter,
            'allClassesSelected' => $allSelected,
            'rollFilter' => $rollFilter,
            'viewMode' => $viewMode,
        ];

        if ($viewMode === 'month') {
            [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
            $records = [];
            if ($classFilter && !$allSelected) {
                $students = Student::withoutGlobalScopes()->where('class_name', $classFilter)
                    ->when($rollFilter, fn ($q) => $q->where('roll_no', 'like', "%{$rollFilter}%"))
                    ->orderBy('section')->orderBy('roll_no')->get();
                $monthlyCounts = Attendance::whereIn('student_id', $students->pluck('id'))
                    ->whereYear('date', $year)->whereMonth('date', $month)
                    ->selectRaw('student_id, SUM(CASE WHEN is_present = 1 THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN is_present = 0 THEN 1 ELSE 0 END) as absent_count')
                    ->groupBy('student_id')->get()->keyBy('student_id');

                foreach ($students as $student) {
                    $counts = $monthlyCounts->get($student->id);
                    $present = (int) ($counts->present_count ?? 0);
                    $absent = (int) ($counts->absent_count ?? 0);
                    $records[] = ['student' => $student, 'present' => $present, 'absent' => $absent, 'marked_days' => $present + $absent];
                }
            }
            return view('history.attendance-history', $context + ['monthStr' => $monthStr, 'records' => $records]);
        }

        $dateFilter = $this->safeDate($request->query('date'));
        $records = [];
        if ($allSelected || $classFilter || $rollFilter) {
            $students = Student::withoutGlobalScopes()
                ->when($classFilter && !$allSelected, fn ($q) => $q->where('class_name', $classFilter))
                ->when($rollFilter, fn ($q) => $q->where('roll_no', 'like', "%{$rollFilter}%"))
                ->orderBy('class_name')->orderBy('section')->orderBy('roll_no')->get();

            if ($allSelected) {
                $order = array_flip($classNames);
                $students = $students->sortBy(fn ($s) => [$order[$s->class_name] ?? PHP_INT_MAX, $s->section, $s->roll_no])->values();
            }

            $attendanceMap = Attendance::whereIn('student_id', $students->pluck('id'))->where('date', $dateFilter)->pluck('is_present', 'student_id');
            foreach ($students as $student) {
                $status = $attendanceMap->has($student->id) ? ($attendanceMap[$student->id] ? 'present' : 'absent') : 'not_marked';
                $records[] = ['student' => $student, 'status' => $status];
            }
        }

        $presentCount = count(array_filter($records, fn ($r) => $r['status'] === 'present'));
        $absentCount = count(array_filter($records, fn ($r) => $r['status'] === 'absent'));

        return view('history.attendance-history', $context + compact('dateFilter', 'records', 'presentCount', 'absentCount'));
    }

    public function correctAttendance(Request $request, Student $student)
    {
        $dateStr = $request->input('date') ?: now('Asia/Dhaka')->toDateString();
        $newStatus = $request->input('status');
        if (!in_array($newStatus, ['present', 'absent'], true)) {
            return redirect()->route('history.attendance');
        }

        Attendance::updateOrCreate(
            ['student_id' => $student->id, 'date' => $dateStr],
            ['is_present' => $newStatus === 'present']
        );

        if ($newStatus === 'absent' && $request->input('send_sms') === 'yes' && $student->parent_mobile) {
            $message = \App\Services\SmsService::buildAbsentMessage($student->name, $dateStr, $student->roll_no, $student->class_name . $student->section);
            \App\Services\SmsService::send($student->parent_mobile, $message);
        }

        return redirect()->route('history.attendance', $request->only(['class', 'roll', 'date']));
    }

    /** Teacher (own class) or admin (any class): day/month report. */
    public function classHistory(Request $request)
    {
        [$isAdmin, $allowedClasses] = $this->viewerAllowedClasses();
        if (empty($allowedClasses)) {
            return view('history.class-history', ['noAccess' => true]);
        }

        $classFilter = trim((string) $request->query('class', ''));
        if (!in_array($classFilter, $allowedClasses, true)) {
            $classFilter = $allowedClasses[0];
        }
        $viewMode = $request->query('view') === 'month' ? 'month' : 'day';
        $rollFilter = trim((string) $request->query('roll', ''));

        $students = Student::withoutGlobalScopes()->where('class_name', $classFilter)
            ->when($rollFilter, fn ($q) => $q->where('roll_no', 'like', "%{$rollFilter}%"))
            ->orderBy('section')->orderBy('roll_no')->get();

        $context = compact('isAdmin', 'allowedClasses', 'classFilter', 'viewMode', 'rollFilter');

        if ($viewMode === 'month') {
            [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
            $monthlyCounts = Attendance::whereIn('student_id', $students->pluck('id'))
                ->whereYear('date', $year)->whereMonth('date', $month)
                ->selectRaw('student_id, SUM(CASE WHEN is_present = 1 THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN is_present = 0 THEN 1 ELSE 0 END) as absent_count')
                ->groupBy('student_id')->get()->keyBy('student_id');

            $records = $students->map(function ($student) use ($monthlyCounts) {
                $counts = $monthlyCounts->get($student->id);
                $present = (int) ($counts->present_count ?? 0);
                $absent = (int) ($counts->absent_count ?? 0);
                return ['student' => $student, 'present' => $present, 'absent' => $absent, 'marked_days' => $present + $absent];
            })->all();
            return view('history.class-history', $context + compact('monthStr', 'records'));
        }

        $dateFilter = $this->safeDate($request->query('date'));
        $attendanceMap = Attendance::whereIn('student_id', $students->pluck('id'))->where('date', $dateFilter)->pluck('is_present', 'student_id');
        $records = $students->map(function ($student) use ($attendanceMap) {
            $status = $attendanceMap->has($student->id) ? ($attendanceMap[$student->id] ? 'present' : 'absent') : 'not_marked';
            return ['student' => $student, 'status' => $status];
        })->all();

        return view('history.class-history', $context + [
            'dateFilter' => $dateFilter,
            'records' => $records,
            'presentCount' => count(array_filter($records, fn ($r) => $r['status'] === 'present')),
            'absentCount' => count(array_filter($records, fn ($r) => $r['status'] === 'absent')),
        ]);
    }

    public function studentHistoryDetail(Request $request, Student $student)
    {
        [$isAdmin, $allowedClasses] = $this->viewerAllowedClasses();
        if (!$isAdmin && !in_array($student->class_name, $allowedClasses, true)) {
            return view('history.class-history', ['noAccess' => true]);
        }

        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
        $records = Attendance::where('student_id', $student->id)->whereYear('date', $year)->whereMonth('date', $month)->orderBy('date')->get();

        return view('history.student-history-detail', [
            'student' => $student,
            'isAdminUser' => $isAdmin,
            'monthStr' => $monthStr,
            'prevMonth' => $this->prevMonth($year, $month),
            'nextMonth' => $this->nextMonth($year, $month),
            'presentDates' => $records->where('is_present', true)->pluck('date'),
            'absentDates' => $records->where('is_present', false)->pluck('date'),
            'presentCount' => $records->where('is_present', true)->count(),
            'absentCount' => $records->where('is_present', false)->count(),
            'totalMarked' => $records->count(),
        ]);
    }

        public function teacherAttendanceHistory(Request $request)
    {
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));

        // Fetch all monthly attendance totals in one grouped query instead of
        // running two COUNT queries for every teacher (N+1 query pattern).
        $attendanceCounts = TeacherAttendance::selectRaw('teacher_id, is_present, COUNT(*) as total')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->groupBy('teacher_id', 'is_present')
            ->get()
            ->groupBy('teacher_id');

        $buildRecords = function ($teachers) use ($attendanceCounts) {
            return $teachers->map(function ($teacher) use ($attendanceCounts) {
                $counts = $attendanceCounts->get($teacher->id, collect());
                return [
                    'teacher' => $teacher,
                    'present' => (int) $counts->firstWhere('is_present', true)?->total,
                    'absent' => (int) $counts->firstWhere('is_present', false)?->total,
                ];
            })->values();
        };

        $fullTimeRecords = $buildRecords(Teacher::where('employment_type', Teacher::EMPLOYMENT_FULL)->orderBy('id')->get());
        $partTimeRecords = $buildRecords(Teacher::where('employment_type', Teacher::EMPLOYMENT_PART)->orderBy('id')->get());

        return view('history.teacher-attendance-history', compact('fullTimeRecords', 'partTimeRecords', 'monthStr'));
    }

    public function teacherHistoryDetail(Request $request, Teacher $teacher)
    {
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
        $records = TeacherAttendance::where('teacher_id', $teacher->id)->whereYear('date', $year)->whereMonth('date', $month)->orderBy('date')->get();

        return view('history.teacher-history-detail', [
            'teacher' => $teacher,
            'monthStr' => $monthStr,
            'prevMonth' => $this->prevMonth($year, $month),
            'nextMonth' => $this->nextMonth($year, $month),
            'presentDates' => $records->where('is_present', true)->pluck('date'),
            'absentDates' => $records->where('is_present', false)->pluck('date'),
            'presentCount' => $records->where('is_present', true)->count(),
            'absentCount' => $records->where('is_present', false)->count(),
            'totalMarked' => $records->count(),
        ]);
    }

    // --- Excel exports (openpyxl -> PhpSpreadsheet, same P/A colored-grid layout) ---

    public function exportAttendance(Request $request)
    {
        $classFilter = trim((string) $request->query('class', ''));
        $dateFilter = $this->safeDate($request->query('date'));
        if (!$classFilter) {
            return redirect()->route('history.attendance');
        }

        $students = Student::withoutGlobalScopes()->where('class_name', $classFilter)->orderBy('roll_no')->get();
        $attendanceMap = Attendance::whereIn('student_id', $students->pluck('id'))->where('date', $dateFilter)->pluck('is_present', 'student_id');

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle("Class {$classFilter}");
        $headers = ['Roll', 'Name', 'Class', 'Section', 'Mobile', 'Status'];
        $this->writeHeaderRow($ws, $headers);

        $row = 2;
        foreach ($students as $student) {
            $statusBool = $attendanceMap->get($student->id);
            $status = $statusBool === true ? 'Present' : ($statusBool === false ? 'Absent' : 'Not Marked');
            $ws->fromArray([$student->roll_no, $student->name, $student->class_name, $student->section, $student->parent_mobile ?: '', $status], null, "A{$row}");
            if ($statusBool !== null) {
                $ws->getStyle("F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusBool ? 'D1FAE5' : 'FEE2E2');
            }
            $row++;
        }
        $this->applyBorders($ws, $row - 1, count($headers));

        return $this->streamXlsx($sheet, "Attendance_Class_{$classFilter}_{$dateFilter}.xlsx");
    }

    public function exportAttendanceMonth(Request $request)
    {
        [$isAdmin, $allowedClasses] = $this->viewerAllowedClasses();
        $classFilter = trim((string) $request->query('class', ''));
        if (!in_array($classFilter, $allowedClasses, true)) {
            return redirect()->route('dashboard');
        }
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));

        $students = Student::withoutGlobalScopes()->where('class_name', $classFilter)->orderBy('section')->orderBy('roll_no')->get();
        $dates = Attendance::whereIn('student_id', $students->pluck('id'))->whereYear('date', $year)->whereMonth('date', $month)
            ->distinct()->orderBy('date')->pluck('date');
        $attendanceMap = Attendance::whereIn('student_id', $students->pluck('id'))->whereYear('date', $year)->whereMonth('date', $month)
            ->get()->mapWithKeys(fn ($a) => ["{$a->student_id}_{$a->date->format('Y-m-d')}" => $a->is_present]);

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle(substr("Class {$classFilter} {$monthStr}", 0, 31));
        $headers = array_merge(['Roll', 'Name', 'Section'], $dates->map(fn ($d) => $d->format('d M'))->all(), ['Present', 'Absent']);
        $this->writeHeaderRow($ws, $headers);

        $row = 2;
        foreach ($students as $student) {
            $present = $absent = 0;
            $ws->fromArray([$student->roll_no, $student->name, $student->section], null, "A{$row}");
            $col = 4;
            foreach ($dates as $d) {
                $key = "{$student->id}_{$d->format('Y-m-d')}";
                $val = $attendanceMap->get($key);
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                if ($val === true) { $ws->setCellValue("{$letter}{$row}", 'P'); $ws->getStyle("{$letter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5'); $present++; }
                elseif ($val === false) { $ws->setCellValue("{$letter}{$row}", 'A'); $ws->getStyle("{$letter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2'); $absent++; }
                else { $ws->setCellValue("{$letter}{$row}", '-'); }
                $col++;
            }
            $presentLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $absentLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $ws->setCellValue("{$presentLetter}{$row}", $present);
            $ws->setCellValue("{$absentLetter}{$row}", $absent);
            $row++;
        }
        $this->applyBorders($ws, $row - 1, count($headers));

        return $this->streamXlsx($sheet, "Attendance_Class_{$classFilter}_{$monthStr}.xlsx");
    }

    public function exportTeacherAttendance(Request $request)
    {
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
        $teachers = Teacher::orderBy('id')->get();
        $dates = TeacherAttendance::whereYear('date', $year)->whereMonth('date', $month)->distinct()->orderBy('date')->pluck('date');
        $attendanceMap = TeacherAttendance::whereYear('date', $year)->whereMonth('date', $month)
            ->get()->mapWithKeys(fn ($a) => ["{$a->teacher_id}_{$a->date->format('Y-m-d')}" => $a->is_present]);

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle(substr("Teacher Attendance {$monthStr}", 0, 31));
        $headers = array_merge(['ID', 'Name', 'Mobile'], $dates->map(fn ($d) => $d->format('d M'))->all(), ['Present', 'Absent']);
        $this->writeHeaderRow($ws, $headers);

        $row = 2;
        foreach ($teachers as $teacher) {
            $present = $absent = 0;
            $ws->fromArray([$teacher->id, $teacher->name, $teacher->mobile ?: ''], null, "A{$row}");
            $col = 4;
            foreach ($dates as $d) {
                $key = "{$teacher->id}_{$d->format('Y-m-d')}";
                $val = $attendanceMap->get($key);
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                if ($val === true) { $ws->setCellValue("{$letter}{$row}", 'P'); $ws->getStyle("{$letter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5'); $present++; }
                elseif ($val === false) { $ws->setCellValue("{$letter}{$row}", 'A'); $ws->getStyle("{$letter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2'); $absent++; }
                else { $ws->setCellValue("{$letter}{$row}", '-'); }
                $col++;
            }
            $presentLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $absentLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $ws->setCellValue("{$presentLetter}{$row}", $present);
            $ws->setCellValue("{$absentLetter}{$row}", $absent);
            $row++;
        }
        $this->applyBorders($ws, $row - 1, count($headers));

        return $this->streamXlsx($sheet, "Teacher_Attendance_{$monthStr}.xlsx");
    }

    private function writeHeaderRow($ws, array $headers): void
    {
        $ws->fromArray($headers, null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $ws->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3D32');
        $ws->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal('center');
    }

    private function applyBorders($ws, int $lastRow, int $colCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
        $ws->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function streamXlsx(Spreadsheet $sheet, string $filename)
    {
        $writer = new Xlsx($sheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

       private function viewerAllowedClasses(): array
    {
        return \App\Support\ViewerAccess::allowedClasses(Auth::user());
    }

    private function prevMonth(int $year, int $month): string
    {
        $d = \Carbon\Carbon::create($year, $month, 1)->subDay();
        return $d->format('Y-m');
    }

    private function nextMonth(int $year, int $month): string
    {
        return $month === 12 ? sprintf('%04d-01', $year + 1) : sprintf('%04d-%02d', $year, $month + 1);
    }
}
