<?php

namespace App\Services;

class ImapOtpService
{
    public function fetchLatestOtp($targetSender, $service = null)
    {
        $email = env('IMAP_USER');
        $appPassword = env('IMAP_PASSWORD');
        $mailbox = env('IMAP_MAILBOX', '{imap.gmail.com:993/imap/ssl}INBOX');

        $inbox = @imap_open($mailbox, $email, $appPassword);
        if (!$inbox) {
            return [
                'otp' => null,
                'error' => 'Connection failed: ' . imap_last_error(),
            ];
        }

        $numMessages = imap_num_msg($inbox);
        if ($numMessages < 1) {
            imap_close($inbox);
            return [
                'otp' => null,
                'error' => 'No messages found',
            ];
        }

        $foundOtp = null;
        $foundSubject = '';
        $foundFrom = '';
        $foundDate = '';
        $foundBody = '';
        $foundRawEmail = '';
        $foundHtmlEmail = '';

        for ($i = $numMessages; $i >= 1; $i--) {
            $overview = imap_fetch_overview($inbox, $i, 0);
            if (empty($overview)) {
                continue;
            }
            $subject = $this->decodeMimeStr($overview[0]->subject ?? '-');
            $from = $this->decodeMimeStr($overview[0]->from ?? '-');
            $date = $overview[0]->date ?? '-';
            if (!$this->isTargetForwardMail($from, $subject, $targetSender)) {
                continue;
            }
            $structure = imap_fetchstructure($inbox, $i);
            $parts = $this->getAllTextParts($inbox, $i, $structure);
            $plainText = '';
            $htmlText = '';
            $rawEmail = '';
            $htmlEmail = '';
            foreach ($parts as $part) {
                if ($part['subtype'] === 'plain') {
                    $plainText .= "\n" . $part['content'];
                    $rawEmail .= "\n" . $part['content'];
                } elseif ($part['subtype'] === 'html') {
                    $htmlText .= "\n" . strip_tags($part['content']);
                    $rawEmail .= "\n" . $part['content'];
                    $htmlEmail .= "\n" . $part['content'];
                }
            }
            $mailText = trim($plainText);
            if ($mailText === '') {
                $mailText = trim($htmlText);
            }
            // ถ้ามี $service ให้ filter ว่าใน header หรือ body มีชื่อ service หรือไม่
            if ($service) {
                $serviceLower = mb_strtolower($service);
                $headerAndBody = mb_strtolower($from . "\n" . $subject . "\n" . $mailText);
                if (strpos($headerAndBody, $serviceLower) === false) {
                    continue;
                }
            }
            $searchText = $subject . "\n" . $mailText;
            // ดึง OTP จาก html_email ก่อน ถ้าไม่มีค่อย fallback ไป plain text
            $otp = null;
            if (!empty($htmlEmail)) {
                $otp = $this->extractOtp($htmlEmail);
            }
            if ($otp === null) {
                $otp = $this->extractOtp($searchText);
            }
            if ($otp !== null) {
                $foundOtp = $otp;
                $foundSubject = $subject;
                $foundFrom = $from;
                $foundDate = $date;
                $foundBody = $mailText;
                $foundRawEmail = $rawEmail;
                $foundHtmlEmail = $htmlEmail;
                break;
            }
        }
        imap_close($inbox);
        return [
            'otp' => $foundOtp,
            'subject' => $foundSubject,
            'from' => $foundFrom,
            'date' => $foundDate,
            'body' => $foundBody,
            'raw_email' => $foundRawEmail,
            'html_email' => $foundHtmlEmail,
            'error' => null,
        ];
    }

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

    private function decodeBodyByEncoding($body, $encoding)
    {
        switch ((int)$encoding) {
            case 3: // BASE64
                return base64_decode($body);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    private function convertToUtf8($text, $charset = 'UTF-8')
    {
        $charset = $charset ?: 'UTF-8';
        if (strtoupper($charset) !== 'UTF-8') {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $text;
    }

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
            $decoded = $this->decodeBodyByEncoding($body, $structure->encoding ?? 0);
            $charset = 'UTF-8';
            if (!empty($structure->parameters)) {
                foreach ($structure->parameters as $param) {
                    if (strtolower($param->attribute ?? '') === 'charset') {
                        $charset = $param->value;
                        break;
                    }
                }
            }
            $decoded = $this->convertToUtf8($decoded, $charset);
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

    private function extractOtp($text)
    {
        $text = trim((string)$text);
        // 1. OTP ใน tag HTML เช่น <span>2418</span> หรือ <b>2418</b>
        if (preg_match('/<[^>]*(?:span|strong|b|h[1-6])[^>]*>\s*([0-9]{4,8})\s*<\/[^>]+>/iu', $text, $m)) {
            return $m[1];
        }
        // 2. OTP ที่มีคำว่า "รหัสยืนยันของคุณคือ"
        if (preg_match('/รหัสยืนยันของคุณคือ\s*([0-9]{4,8})/u', $text, $m)) {
            return $m[1];
        }
        // 3. OTP ที่มีคำว่า "รหัสยืนยัน" ใกล้เลข
        if (preg_match('/รหัสยืนยัน.*?([0-9]{4,8})/u', $text, $m)) {
            return $m[1];
        }
        // 4. OTP ที่มีคำว่า otp, code, verification code
        if (preg_match('/(?:otp|verification code|code).*?([0-9]{4,8})/iu', $text, $m)) {
            return $m[1];
        }
        // 5. เลข 4-8 หลักตัวแรกที่เจอ
        if (preg_match_all('/\b([0-9]{4,8})\b/', $text, $all) && !empty($all[1])) {
            return $all[1][0];
        }
        return null;
    }

    private function isTargetForwardMail($from, $subject, $targetEmail)
    {
        $from = strtolower((string)$from);
        $subject = strtolower(trim((string)$subject));
        $targetEmail = strtolower(trim((string)$targetEmail));
        $isFromTarget = strpos($from, $targetEmail) !== false;
        $isForward = preg_match('/^(fw|fwd)\s*:/i', $subject) === 1;
        return $isFromTarget && $isForward;
    }
}
