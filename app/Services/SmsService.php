<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Direct port of attendance/sms_utils.py. Keep the phone-number rules
 * and message wording identical to the Django app so existing parents'
 * saved numbers and SMS templates behave the same after migration.
 */
class SmsService
{

    /**
     * Calculate billable SMS segments from the complete message text, including the appended school name.
     * GSM-7: 160 units for one segment, then 153 per concatenated segment.
     * Unicode/UCS-2: 70 UTF-16 code units for one segment, then 67 per concatenated segment.
     */
    public static function segmentCount(string $message): int
    {
        $gsmBasic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $gsmExtended = "^{}\\[~]|€\f";
        $septets = 0;
        $isGsm7 = true;

        foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (str_contains($gsmBasic, $char)) {
                $septets++;
            } elseif (str_contains($gsmExtended, $char)) {
                $septets += 2;
            } else {
                $isGsm7 = false;
                break;
            }
        }

        if ($isGsm7) {
            return $septets <= 160 ? 1 : intdiv($septets + 152, 153);
        }

        $units = intdiv(strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')), 2);
        return $units <= 70 ? 1 : intdiv($units + 66, 67);
    }

    /** Count GSM-7 septets or Unicode UTF-16 code units for the 159-unit rule. */
    public static function smsUnitCount(string $message): int
    {
        $gsmBasic = "@£\\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $gsmExtended = "^{}\\\\[~]|€\f";
        $septets = 0;

        foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (str_contains($gsmBasic, $char)) {
                $septets++;
            } elseif (str_contains($gsmExtended, $char)) {
                $septets += 2;
            } else {
                return intdiv(strlen(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')), 2);
            }
        }

        return $septets;
    }

    public static function appendSchoolName(string $baseMessage, bool $shortIfOver159 = true): string
    {
        $fullName = config('school.full_name', 'School');
        $shortName = config('school.short_name', 'School');
        $fullMessage = $baseMessage . "\n" . $fullName;

        if ($shortIfOver159 && self::smsUnitCount($fullMessage) > 159) {
            return $baseMessage . "\n" . $shortName;
        }

        return $fullMessage;
    }
    public static function buildAbsentMessage(string $studentName, string $dateStr, ?string $rollNo = null, ?string $className = null): string
    {
        $studentDetails = $studentName;
        if ($rollNo) {
            $studentDetails .= " (Roll: {$rollNo}";
            if ($className) {
                $studentDetails .= ", Class: {$className}";
            }
            $studentDetails .= ')';
        } elseif ($className) {
            $studentDetails .= " (Class: {$className})";
        }

        $base = "Dear Parents,\n{$studentDetails} was ABSENT on {$dateStr}. Contact Authority if mistake.";
        return self::appendSchoolName($base);
    }

    public static function buildTeacherAbsentMessage(string $teacherName, string $dateStr): string
    {
        $base = "Dear {$teacherName},\nYou have been marked ABSENT today on {$dateStr}. Please contact the Authority if this is a mistake.";
        return self::appendSchoolName($base);
    }

    /**
     * Normalize any human/Excel-entered phone number into the app's
     * canonical STORAGE format: an 11-digit Bangladeshi local number
     * starting with 0 (e.g. "01712345678"). Never silently drops a
     * value -- returns the best-effort cleaned string. See
     * normalizeSmsNumber() for the strict check used right before an
     * SMS actually goes out.
     */
    public static function cleanPhoneForStorage($raw): string
    {
        if ($raw === null) {
            return '';
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }

        // Excel sometimes turns "01712345678" into the float 1712345678.0
        if (str_ends_with($s, '.0')) {
            $s = substr($s, 0, -2);
        }

        // Strip everything except digits and a leading +
        $s = preg_replace('/[^\d+]/', '', $s);

        if (str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }
        if (str_starts_with($s, '00')) {
            $s = substr($s, 2);
        }

        if (str_starts_with($s, '880') && strlen($s) === 13) {
            // 8801712345678 -> 01712345678
            $s = '0' . substr($s, 3);
        } elseif (ctype_digit($s) && strlen($s) === 10 && !str_starts_with($s, '0')) {
            // 1712345678 -> 01712345678
            $s = '0' . $s;
        }

        return $s;
    }

    /**
     * Convert a stored (or raw) phone number into the exact format the
     * SMS gateway needs: "+8801XXXXXXXXX". Returns null if it can't be
     * confidently normalized, so callers can skip/flag it instead of
     * sending to a malformed destination.
     */
    public static function normalizeSmsNumber($number): ?string
    {
        $cleaned = self::cleanPhoneForStorage($number);
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '01') && ctype_digit($cleaned)) {
            return '+880' . substr($cleaned, 1);
        }
        return null;
    }

    /**
     * Send one SMS via bdbulksms.net. Returns [bool $success, string $responseOrError].
     */
    public static function send($number, string $message): array
    {
        $token = config('services.bdbulksms.token');
        if (!$token) {
            return [false, 'SMS token is not configured in environment variables.'];
        }

        $normalizedNumber = self::normalizeSmsNumber($number);
        if (!$normalizedNumber) {
            Log::warning("SMS not sent: could not normalize phone number", ['number' => $number]);
            return [false, "Invalid/unrecognized phone number format: " . json_encode($number)];
        }

        try {
            $response = Http::timeout(10)->get('https://api.bdbulksms.net/api.php', [
                'token' => $token,
                'to' => $normalizedNumber,
                'message' => $message,
                'json' => '',
            ]);

            $responseText = trim($response->body());
            $isSuccess = false;

            $payload = json_decode($responseText, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $results = is_array($payload) && array_is_list($payload) ? $payload : [$payload];
                foreach ($results as $result) {
                    if (is_array($result) && strtoupper((string) ($result['status'] ?? '')) === 'SENT') {
                        $isSuccess = $response->successful();
                        break;
                    }
                }
            } else {
                $lower = strtolower($responseText);
                $isSuccess = $response->successful() && (str_contains($lower, 'ok:') || str_contains($lower, 'success'));
            }

            Log::info("SMS delivery attempt", [
                'to' => $normalizedNumber,
                'sms_segments' => self::segmentCount($message),
                'success' => $isSuccess,
                'status' => $response->status(),
                'response' => substr($responseText, 0, 500),
            ]);

            return [$isSuccess, $responseText];
        } catch (\Throwable $exc) {
            Log::error("SMS delivery network error", ['to' => $normalizedNumber, 'error' => $exc->getMessage()]);
            return [false, 'SMS delivery failed due to a network connection error.'];
        }
    }
}
