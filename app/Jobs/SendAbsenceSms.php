<?php

namespace App\Jobs;

use App\Models\AbsenceSms;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces Django's custom `process_sms_queue` management command (an
 * infinite polling loop). Laravel's own queue worker does the polling,
 * retrying and backoff for us -- run it with:
 *
 *     php artisan queue:work --tries=5 --backoff=300
 *
 * $tries and $backoff below mirror the source app's
 * max_attempts=5 / retry_minutes=5.
 */
class SendAbsenceSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 300; // seconds, 5 minutes

    public function __construct(public int $absenceSmsId)
    {
    }

    public function handle(): void
    {
        $message = AbsenceSms::with('student')->find($this->absenceSmsId);
        if (!$message) {
            return;
        }

        $student = $message->student;
        if (!$student || !$student->parent_mobile) {
            $message->update([
                'status' => AbsenceSms::STATUS_FAILED,
                'last_error' => 'No parent phone number.',
                'next_attempt_at' => null,
            ]);
            return;
        }

        $dateStr = $message->date->format('d-M-y');
        $sms = SmsService::buildAbsentMessage(
            $student->name,
            $dateStr,
            $student->roll_no,
            $student->class_name
        );
        $message->update(['sms_segments' => SmsService::segmentCount($sms)]);

        [$success, $response] = SmsService::send($student->parent_mobile, $sms);

        if ($success) {
            $message->update([
                'status' => AbsenceSms::STATUS_SENT,
                'next_attempt_at' => null,
                'last_error' => '',
                'sent_at' => now(),
            ]);
        } else {
            $this->markFailed($message, $response ?: 'SMS provider rejected the request.');
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $message = AbsenceSms::find($this->absenceSmsId);
        if ($message) {
            $this->markFailed($message, $exception->getMessage());
        }
    }

    private function markFailed(AbsenceSms $message, string $error): void
    {
        $message->update([
            'status' => AbsenceSms::STATUS_FAILED,
            'attempts' => $message->attempts + 1,
            'last_error' => substr($error, 0, 1000),
        ]);
    }
}
