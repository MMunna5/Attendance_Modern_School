<?php

namespace App\\Http\\Controllers;

use App\\Models\\AbsenceSms;
use App\\Models\\TeacherAbsenceSms;
use App\\Models\\EmergencySmsLog;
use App\\Jobs\\SendAbsenceSms;
use App\\Jobs\\SendTeacherAbsenceSms;
use Illuminate\\Http\\Request;

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

        // Use a half-open date range so database indexes on sent_at/date can help.
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));
        $monthStart = now('Asia/Dhaka')->setDate($year, $month, 1)->startOfDay();
        $nextMonthStart = $monthStart->copy()->addMonth();

        $studentStats = AbsenceSms::where('status', AbsenceSms::STATUS_SENT)
            ->where('sent_at', '>=', $monthStart)
            ->where('sent_at', '<', $nextMonthStart)
            ->selectRaw('COUNT(*) as sent, COALESCE(SUM(sms_segments), 0) as segments')
            ->first();
        $teacherStats = TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_SENT)
            ->where('sent_at', '>=', $monthStart)
            ->where('sent_at', '<', $nextMonthStart)
            ->selectRaw('COUNT(*) as sent, COALESCE(SUM(sms_segments), 0) as segments')
            ->first();
        $emergencyStats = EmergencySmsLog::where('sent_at', '>=', $monthStart)
            ->where('sent_at', '<', $nextMonthStart)
            ->selectRaw('COUNT(*) as sent, COALESCE(SUM(sms_segments), 0) as segments')
            ->first();

        $dateStart = $monthStart->toDateString();
        $nextDateStart = $nextMonthStart->toDateString();

        $counts = [
            'student' => [
                'segments' => (int) $studentStats->segments,
                'sent' => (int) $studentStats->sent,
                'failed' => AbsenceSms::where('status', AbsenceSms::STATUS_FAILED)
                    ->where('date', '>=', $dateStart)->where('date', '<', $nextDateStart)->count(),
                'pending' => AbsenceSms::where('status', AbsenceSms::STATUS_PENDING)
                    ->where('date', '>=', $dateStart)->where('date', '<', $nextDateStart)->count(),
            ],
            'teacher' => [
                'segments' => (int) $teacherStats->segments,
                'sent' => (int) $teacherStats->sent,
                'failed' => TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_FAILED)
                    ->where('date', '>=', $dateStart)->where('date', '<', $nextDateStart)->count(),
                'pending' => TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_PENDING)
                    ->where('date', '>=', $dateStart)->where('date', '<', $nextDateStart)->count(),
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
            preg_match('/^(\\d{4})-(\\d{1,2})$/', $monthStr, $m) &&
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