<?php

namespace App\Services;


class ImapOtpService
{

    private function normalizeBody(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractOtpCandidate(string $candidate): ?string
    {
        $digits = preg_replace('/\D+/', '', $candidate) ?? '';
        $len = strlen($digits);

        if ($len >= 4 && $len <= 8) {
            return $digits;
        }

        return null;
    }

    private function normalizeMailbox($mailbox)
    {
        $mailbox = trim((string)$mailbox);

        if (stripos($mailbox, 'IMAP_MAILBOX=') === 0) {
            $mailbox = substr($mailbox, strlen('IMAP_MAILBOX='));
        }

        if ($mailbox === '') {
            return '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
        }

        return $mailbox;
    }

    private function withMailboxFolder(string $mailbox, string $folder): string
    {
        $folder = trim($folder);
        if ($folder === '') {
            return $mailbox;
        }

        $rewritten = preg_replace('/\}.*$/', '}' . $folder, $mailbox, 1);
        return is_string($rewritten) && $rewritten !== '' ? $rewritten : $mailbox;
    }

    public function fetchLatestOtpFromMailboxes($email, $password, $service, array $mailboxes, $forwardedTargetEmail = null)
    {
        $bestResult = null;
        $bestTimestamp = 0;
        $lastError = null;
        $combinedDebugSubjects = [];
        $checkedMailboxes = [];

        foreach ($mailboxes as $mailbox) {
            $checkedMailboxes[] = (string)$mailbox;
            $result = $this->fetchLatestOtpFromInbox($email, $password, $service, $mailbox, $forwardedTargetEmail);

            if (!is_array($result)) {
                continue;
            }

            if (!empty($result['error_type'])) {
                $lastError = $result;
                continue;
            }

            if (!empty($result['debug_subjects']) && is_array($result['debug_subjects'])) {
                $combinedDebugSubjects = array_merge($combinedDebugSubjects, $result['debug_subjects']);
            }

            if (empty($result['otp'])) {
                continue;
            }

            $timestamp = strtotime((string)($result['date'] ?? '')) ?: 0;
            if ($bestResult === null || $timestamp >= $bestTimestamp) {
                $bestResult = $result;
                $bestTimestamp = $timestamp;
            }
        }

        if ($bestResult !== null) {
            if (!empty($combinedDebugSubjects)) {
                $bestResult['debug_subjects'] = $combinedDebugSubjects;
            }

            $bestResult['checked_mailboxes'] = $checkedMailboxes;

            return $bestResult;
        }

        if ($lastError !== null) {
            $lastError['checked_mailboxes'] = $checkedMailboxes;
            return $lastError;
        }

        return [
            'otp' => null,
            'debug_subjects' => $combinedDebugSubjects,
            'checked_mailboxes' => $checkedMailboxes,
        ];
    }

    /**
     * ดึง OTP ล่าสุดจากกล่องเมลที่ subject/body มีชื่อ service
     */
    public function fetchLatestOtpFromInbox($email, $password, $service, $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX', $forwardedTargetEmail = null)
    {
        $mailbox = $this->normalizeMailbox($mailbox);
        $forwardedTargetEmail = strtolower(trim((string)$forwardedTargetEmail));
        $inbox = @imap_open($mailbox, $email, $password);
        if (!$inbox) {
            $imapErrors = imap_errors() ?: [];
            $lastError = implode(' | ', $imapErrors);
            $errorType = 'imap_unavailable';

            if (
                stripos($lastError, 'authentication failed') !== false ||
                stripos($lastError, 'invalid credentials') !== false ||
                stripos($lastError, 'authent') !== false ||
                stripos($lastError, 'web login required') !== false ||
                stripos($lastError, 'application-specific password') !== false ||
                stripos($lastError, 'username and password not accepted') !== false
            ) {
                $errorType = 'auth_failed';
            }

            return [
                'error_type' => $errorType,
                'error' => 'IMAP open failed',
                'error_message' => $lastError ?: null,
                'imap_errors' => $imapErrors,
                'imap_alerts' => imap_alerts(),
            ];
        }
        $numMessages = imap_num_msg($inbox);
        if ($numMessages < 1) {
            imap_close($inbox);
            return null;
        }

        // Config
        $serviceLower = mb_strtolower($service);
        $maxSearch = (int)env('IMAP_MAX_SEARCH', 30);
        $maxSearch = max(10, min($maxSearch, 100));
        $start = max($numMessages - $maxSearch + 1, 1);
        $debugSubjects = [];

        // Search จากเมลล่าสุดไปหาเก่า
        for ($i = $numMessages; $i >= $start; $i--) {
            $overview = imap_fetch_overview($inbox, $i, 0);
            if (empty($overview)) continue;

            $subject = $this->decodeMimeStr($overview[0]->subject ?? '');
            $from = $this->decodeMimeStr($overview[0]->from ?? '');
            $date = $overview[0]->date ?? '';

            // Quick check 1: Forwarded target (check FROM only, no body parsing yet)
            if (!$this->isFromTargetEmail($from, $forwardedTargetEmail)) {
                $debugSubjects[] = [
                    'subject' => $subject,
                    'from' => $from,
                    'date' => $date,
                    'mailbox' => $mailbox,
                    'forwarded_matched' => false,
                ];
                continue;
            }

            // Parse body เฉพาะเมลที่ผ่าน target-email check แล้ว
            $structure = imap_fetchstructure($inbox, $i);
            $parts = $this->getAllTextParts($inbox, $i, $structure);
            $body = $this->extractBodyFromParts($parts);

            // Full service check on body
            if (!$this->isServiceMatch($subject, $from, $body, $serviceLower)) {
                $debugSubjects[] = [
                    'subject' => $subject,
                    'from' => $from,
                    'date' => $date,
                    'mailbox' => $mailbox,
                    'forwarded_matched' => true,
                ];
                continue;
            }

            // Extract OTP
            $otp = $this->extractOtp($body);
            if ($otp) {
                imap_close($inbox);
                return [
                    'otp' => $otp,
                    'subject' => $subject,
                    'from' => $from,
                    'date' => $date,
                ];
            }

            $debugSubjects[] = [
                'subject' => $subject,
                'from' => $from,
                'date' => $date,
                'mailbox' => $mailbox,
                'forwarded_matched' => true,
            ];
        }

        imap_close($inbox);
        return [
            'otp' => null,
            'debug_subjects' => $debugSubjects,
        ];
    }

    /**
     * Check if FROM email matches target (quick, no body parsing)
     */
    private function isFromTargetEmail(string $from, string $forwardedTargetEmail): bool
    {
        if ($forwardedTargetEmail === '') {
            return true;
        }

        $fromEmails = $this->extractEmailAddresses($from);
        return in_array($forwardedTargetEmail, $fromEmails, true);
    }

    /**
     * Full service match on subject/from/body (after body parsing)
     */
    private function isServiceMatch(string $subject, string $from, string $body, string $serviceLower): bool
    {
        if (!$serviceLower) {
            return true;
        }

        $subjectLower = mb_strtolower($subject);
        $fromLower = mb_strtolower($from);
        $bodyLower = mb_strtolower($body);

        // Special case: Netflix
        if ($serviceLower === 'netflix') {
            return mb_stripos($subjectLower, 'netflix') !== false
                || mb_stripos($fromLower, 'netflix') !== false
                || mb_stripos($bodyLower, 'netflix') !== false;
        }

        // Special case: Disney+
        if ($serviceLower === 'disney+' || $serviceLower === 'disney') {
            return mb_stripos($fromLower, 'disney') !== false
                || mb_stripos($subjectLower, 'disney') !== false
                || mb_stripos($bodyLower, 'disney') !== false;
        }

        // Default: search in all fields
        return mb_stripos($subjectLower, $serviceLower) !== false
            || mb_stripos($fromLower, $serviceLower) !== false
            || mb_stripos($bodyLower, $serviceLower) !== false;
    }

    /**
     * Extract body text from message parts (plain text priority)
     */
    private function extractBodyFromParts(array $parts): string
    {
        $plainText = '';
        $htmlText = '';

        foreach ($parts as $part) {
            if ($part['subtype'] === 'plain') {
                $plainText .= "\n" . $part['content'];
            } elseif ($part['subtype'] === 'html') {
                $htmlText .= "\n" . strip_tags($part['content']);
            }
        }

        return trim($plainText) !== '' ? trim($plainText) : trim($htmlText);
    }

    /**
     * ดึง email addresses จาก text
     * Returns array of lowercase email addresses
     */
    private function extractEmailAddresses(string $text): array
    {
        $emails = [];

        // Pattern: <email@domain.com> หรือ email@domain.com
        if (preg_match_all('/[<\s]?([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})[>\s,;]?/i', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if (!empty($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Remove duplicates
        return array_values(array_unique($emails));
    }

    /**
     * ดึงเลข OTP (4-8 หลัก) จากเนื้อหาอีเมล
     */
    private function extractOtp($body)
    {
        $normalizedBody = $this->normalizeBody((string)$body);
        $keywordPattern = '(?:otp|one\s*time|verification|verify|security|passcode|pin|code|รหัส|ยืนยัน)';

        // Priority 1: code near OTP keywords, handles formats like 1 2 3 4 5 6 or 12-34-56.
        if (
            preg_match('/' . $keywordPattern . '\D{0,30}((?:\d[\s\-]?){4,8})/iu', $normalizedBody, $matches) ||
            preg_match('/((?:\d[\s\-]?){4,8})\D{0,30}' . $keywordPattern . '/iu', $normalizedBody, $matches)
        ) {
            $otp = $this->extractOtpCandidate((string)($matches[1] ?? $matches[0] ?? ''));
            if ($otp !== null) {
                return $otp;
            }
        }

        // Priority 2: any standalone 4-8 digit token.
        if (preg_match('/(?<!\d)(\d{4,8})(?!\d)/', $normalizedBody, $matches)) {
            return (string)$matches[1];
        }

        // Priority 3: grouped digits separated by spaces or hyphens.
        if (preg_match('/(?<!\d)((?:\d[\s\-]?){4,8})(?!\d)/', $normalizedBody, $matches)) {
            return $this->extractOtpCandidate((string)$matches[1]);
        }

        return null;
    }


    /**
     * ดึงทุก text part (plain, html) จากอีเมล
     */
    private function getAllTextParts($inbox, $msgNumber, $structure, $partNumber = '')
    {
        $results = [];
        if (!isset($structure->type)) {
            return $results;
        }
        if ($structure->type == 0) {
            $subtype = strtolower($structure->subtype ?? 'plain');
            if ($partNumber === '') {
                $body = imap_body($inbox, $msgNumber);
            } else {
                $body = imap_fetchbody($inbox, $msgNumber, $partNumber);
            }
            $decoded = $body;
            $encoding = (int)($structure->encoding ?? 0);

            if ($encoding === 3) {
                $decoded = base64_decode((string)$body, true);
                if ($decoded === false) {
                    $decoded = $body;
                }
            } elseif ($encoding === 4) {
                $decoded = quoted_printable_decode((string)$body);
            }

            if (!is_string($decoded)) {
                $decoded = (string)$body;
            }

            $decoded = $this->normalizeBody($decoded);
            $results[] = [
                'subtype' => $subtype,
                'content' => $decoded,
            ];
        }
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $currentPartNumber = $partNumber === ''
                    ? (string)($index + 1)
                    : $partNumber . '.' . ($index + 1);
                $results = array_merge(
                    $results,
                    $this->getAllTextParts($inbox, $msgNumber, $part, $currentPartNumber)
                );
            }
        }
        return $results;
    }

    /**
     * ดึงอีเมลทั้งหมดใน inbox (ไม่ filter OTP)
     */
    public function fetchInboxEmails($maxFetch = 30, $email = null, $appPassword = null, $mailbox = null)
    {
        $mailbox = $this->normalizeMailbox($mailbox ?: '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX');
        $inbox = @imap_open($mailbox, $email, $appPassword);
        if (!$inbox) {
            return [
                'error' => 'IMAP open failed',
                'imap_errors' => imap_errors(),
                'imap_alerts' => imap_alerts(),
            ];
        }

        $numMessages = imap_num_msg($inbox);
        if ($numMessages < 1) {
            imap_close($inbox);
            return [];
        }

        $maxFetch = min($numMessages, $maxFetch);
        $emails = collect();

        for ($i = $numMessages; $i > $numMessages - $maxFetch && $i > 0; $i--) {
            $overview = imap_fetch_overview($inbox, $i, 0);
            if (empty($overview)) continue;
            $structure = imap_fetchstructure($inbox, $i);
            $parts = $this->getAllTextParts($inbox, $i, $structure);
            $plainText = '';
            $htmlText = '';
            foreach ($parts as $part) {
                if ($part['subtype'] === 'plain') {
                    $plainText .= "\n" . $part['content'];
                } elseif ($part['subtype'] === 'html') {
                    $htmlText .= "\n" . strip_tags($part['content']);
                }
            }
            $emails->push([
                'subject' => $this->decodeMimeStr($overview[0]->subject ?? ''),
                'from' => $this->decodeMimeStr($overview[0]->from ?? ''),
                'date' => $overview[0]->date ?? '',
                'body' => trim($plainText) !== '' ? trim($plainText) : trim($htmlText),
            ]);
        }
        imap_close($inbox);
        return $emails->all();
    }

    /**
     * ถอดรหัส MIME header (subject, from) ให้เป็น UTF-8
     */
    private function decodeMimeStr($string, $charset = 'UTF-8')
    {
        $elements = imap_mime_header_decode((string)$string);
        $result = '';
        foreach ($elements as $element) {
            $text = $element->text;
            $fromCharset = $element->charset;
            if ($fromCharset !== 'default') {
                $converted = @iconv($fromCharset, $charset . '//IGNORE', $text);
                if ($converted !== false) {
                    $text = $converted;
                }
            }
            $result .= $text;
        }
        return $result;
    }
}
