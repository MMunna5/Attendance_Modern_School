<?php

namespace App\Http\Controllers;

use App\Models\AbsenceSms;
use App\Models\TeacherAbsenceSms;
use App\Models\EmergencySmsLog;
use App\Jobs\SendAbsenceSms;
use App\Jobs\SendTeacherAbsenceSms;
use Illuminate\Http\Request;

class SmsStatusController extends Controller
{
    public function index(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            $kind = $request->input('kind');
            $id = $request->input('id');

            if ($action === 'retry_all') {
                if ($kind === 'student') {
                    AbsenceSms::where('status', AbsenceSms::STATUS_FAILED)
                        ->chunkById(100, function ($messages) {
                            foreach ($messages as $msg) {
                                $msg->update([
                                    'status' => AbsenceSms::STATUS_PENDING,
                                    'next_attempt_at' => null,
                                ]);
                                SendAbsenceSms::dispatch($msg->id);
                            }
                        });
                } elseif ($kind === 'teacher') {
                    TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_FAILED)
                        ->chunkById(100, function ($messages) {
                            foreach ($messages as $msg) {
                                $msg->update([
                                    'status' => TeacherAbsenceSms::STATUS_PENDING,
                                    'next_attempt_at' => null,
                                ]);
                                SendTeacherAbsenceSms::dispatch($msg->id);
                            }
                        });
                }
            } elseif ($action === 'retry') {
                if ($kind === 'student') {
                    $msg = AbsenceSms::where('status', AbsenceSms::STATUS_FAILED)->find($id);
                    if ($msg) {
                        $msg->update(['status' => AbsenceSms::STATUS_PENDING, 'next_attempt_at' => null]);
                        SendAbsenceSms::dispatch($msg->id);
                    }
                } elseif ($kind === 'teacher') {
                    $msg = TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_FAILED)->find($id);
                    if ($msg) {
                        $msg->update(['status' => TeacherAbsenceSms::STATUS_PENDING, 'next_attempt_at' => null]);
                        SendTeacherAbsenceSms::dispatch($msg->id);
                    }
                }
            }

            return redirect()->route('sms-status.index');
        }

        // Aggregate sent, failed and pending records in one month-scoped query per SMS table.
        // The OR conditions keep each status/date range eligible for its matching index.
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
        $monthStart = now('Asia/Dhaka')->setDate($year, $month, 1)->startOfDay();
        $nextMonthStart = $monthStart->copy()->addMonth();

        $dateStart = $monthStart->toDateString();
        $nextDateStart = $nextMonthStart->toDateString();

        $studentStats = AbsenceSms::query()
            ->where(function ($query) use ($monthStart, $nextMonthStart, $dateStart, $nextDateStart) {
                $query->where(function ($sent) use ($monthStart, $nextMonthStart) {
                    $sent->where('status', AbsenceSms::STATUS_SENT)
                        ->where('sent_at', '>=', $monthStart)
                        ->where('sent_at', '<', $nextMonthStart);
                })->orWhere(function ($failed) use ($dateStart, $nextDateStart) {
                    $failed->where('status', AbsenceSms::STATUS_FAILED)
                        ->where('date', '>=', $dateStart)
                        ->where('date', '<', $nextDateStart);
                })->orWhere(function ($pending) use ($dateStart, $nextDateStart) {
                    $pending->where('status', AbsenceSms::STATUS_PENDING)
                        ->where('date', '>=', $dateStart)
                        ->where('date', '<', $nextDateStart);
                });
            })
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND sent_at >= ? AND sent_at < ? THEN 1 ELSE 0 END), 0) as sent,
                 COALESCE(SUM(CASE WHEN status = ? AND sent_at >= ? AND sent_at < ? THEN sms_segments ELSE 0 END), 0) as segments,
                 COALESCE(SUM(CASE WHEN status = ? AND date >= ? AND date < ? THEN 1 ELSE 0 END), 0) as failed,
                 COALESCE(SUM(CASE WHEN status = ? AND date >= ? AND date < ? THEN 1 ELSE 0 END), 0) as pending',
                [
                    AbsenceSms::STATUS_SENT, $monthStart, $nextMonthStart,
                    AbsenceSms::STATUS_SENT, $monthStart, $nextMonthStart,
                    AbsenceSms::STATUS_FAILED, $dateStart, $nextDateStart,
                    AbsenceSms::STATUS_PENDING, $dateStart, $nextDateStart,
                ]
            )
            ->first();

        $teacherStats = TeacherAbsenceSms::query()
            ->where(function ($query) use ($monthStart, $nextMonthStart, $dateStart, $nextDateStart) {
                $query->where(function ($sent) use ($monthStart, $nextMonthStart) {
                    $sent->where('status', TeacherAbsenceSms::STATUS_SENT)
                        ->where('sent_at', '>=', $monthStart)
                        ->where('sent_at', '<', $nextMonthStart);
                })->orWhere(function ($failed) use ($dateStart, $nextDateStart) {
                    $failed->where('status', TeacherAbsenceSms::STATUS_FAILED)
                        ->where('date', '>=', $dateStart)
                        ->where('date', '<', $nextDateStart);
                })->orWhere(function ($pending) use ($dateStart, $nextDateStart) {
                    $pending->where('status', TeacherAbsenceSms::STATUS_PENDING)
                        ->where('date', '>=', $dateStart)
                        ->where('date', '<', $nextDateStart);
                });
            })
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND sent_at >= ? AND sent_at < ? THEN 1 ELSE 0 END), 0) as sent,
                 COALESCE(SUM(CASE WHEN status = ? AND sent_at >= ? AND sent_at < ? THEN sms_segments ELSE 0 END), 0) as segments,
                 COALESCE(SUM(CASE WHEN status = ? AND date >= ? AND date < ? THEN 1 ELSE 0 END), 0) as failed,
                 COALESCE(SUM(CASE WHEN status = ? AND date >= ? AND date < ? THEN 1 ELSE 0 END), 0) as pending',
                [
                    TeacherAbsenceSms::STATUS_SENT, $monthStart, $nextMonthStart,
                    TeacherAbsenceSms::STATUS_SENT, $monthStart, $nextMonthStart,
                    TeacherAbsenceSms::STATUS_FAILED, $dateStart, $nextDateStart,
                    TeacherAbsenceSms::STATUS_PENDING, $dateStart, $nextDateStart,
                ]
            )
            ->first();

        $emergencyStats = EmergencySmsLog::where('sent_at', '>=', $monthStart)
            ->where('sent_at', '<', $nextMonthStart)
            ->selectRaw('COUNT(*) as sent, COALESCE(SUM(sms_segments), 0) as segments')
            ->first();

        $counts = [
            'student' => [
                'segments' => (int) $studentStats->segments,
                'sent' => (int) $studentStats->sent,
                'failed' => (int) $studentStats->failed,
                'pending' => (int) $studentStats->pending,
            ],
            'teacher' => [
                'segments' => (int) $teacherStats->segments,
                'sent' => (int) $teacherStats->sent,
                'failed' => (int) $teacherStats->failed,
                'pending' => (int) $teacherStats->pending,
            ],
            'emergency' => [
                'segments' => (int) $emergencyStats->segments,
                'sent' => (int) $emergencyStats->sent,
            ],
        ];

        $totalSegments = $counts['student']['segments']
            + $counts['teacher']['segments']
            + $counts['emergency']['segments'];
        $totalMessages = $counts['student']['sent']
            + $counts['teacher']['sent']
            + $counts['emergency']['sent'];

        return view('sms.status', [
            'studentFailed' => AbsenceSms::with('student')
                ->where('status', AbsenceSms::STATUS_FAILED)
                ->orderByDesc('created_at')->limit(50)->get(),
            'studentPending' => AbsenceSms::with('student')
                ->where('status', AbsenceSms::STATUS_PENDING)
                ->orderByDesc('created_at')->limit(50)->get(),
            'teacherFailed' => TeacherAbsenceSms::with('teacher')
                ->where('status', TeacherAbsenceSms::STATUS_FAILED)
                ->orderByDesc('created_at')->limit(50)->get(),
            'teacherPending' => TeacherAbsenceSms::with('teacher')
                ->where('status', TeacherAbsenceSms::STATUS_PENDING)
                ->orderByDesc('created_at')->limit(50)->get(),
            'monthStr' => $monthStr,
            'counts' => $counts,
            'totalSegments' => $totalSegments,
            'totalMessages' => $totalMessages,
        ]);
    }

    private function monthBounds(?string $monthStr): array
    {
        if (
            $monthStr &&
            preg_match('/^(\d{4})-(\d{1,2})$/', $monthStr, $m) &&
            (int) $m[2] >= 1 &&
            (int) $m[2] <= 12
        ) {
            return [
                (int) $m[1],
                (int) $m[2],
                sprintf('%04d-%02d', (int) $m[1], (int) $m[2]),
            ];
        }

        $today = now('Asia/Dhaka');

        return [
            $today->year,
            $today->month,
            $today->format('Y-m'),
        ];
    }
}