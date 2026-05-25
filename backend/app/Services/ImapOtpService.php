<?php

namespace App\Services;


class ImapOtpService
{

    /**
     * ดึง OTP ล่าสุดจากกล่องเมลที่ subject/body มีชื่อ service
     */
    public function fetchLatestOtpFromInbox($email, $password, $service, $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX')
    {
        $inbox = @imap_open($mailbox, $email, $password);
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
            return null;
        }
        $serviceLower = mb_strtolower($service);
        $debugSubjects = [];
        $maxSearch = 10; // จำกัดวนหา 10 ฉบับล่าสุด
        $start = max($numMessages - $maxSearch + 1, 1);
        for ($i = $numMessages; $i >= $start; $i--) {
            $overview = imap_fetch_overview($inbox, $i, 0);
            if (empty($overview)) continue;
            $subject = $this->decodeMimeStr($overview[0]->subject ?? '');
            $from = $this->decodeMimeStr($overview[0]->from ?? '');
            $debugSubjects[] = [
                'subject' => $subject,
                'from' => $from
            ];
            $match = false;
            if ($serviceLower === 'netflix') {
                // Netflix: match เฉพาะ subject
                $match = mb_stripos($subject, 'netflix') !== false;
            } elseif ($serviceLower === 'disney+' || $serviceLower === 'disney') {
                // Disney+: match เฉพาะ from หรือ subject มี disney
                $match = (mb_stripos($from, 'disney') !== false) || (mb_stripos($subject, 'disney') !== false);
            } else {
                // อื่นๆ: match ทั้ง subject และ from
                $match = ($serviceLower && (mb_stripos($subject, $serviceLower) !== false || mb_stripos($from, $serviceLower) !== false));
            }
            if (!$match) continue;
            $date = $overview[0]->date ?? '';
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
            $body = trim($plainText) !== '' ? trim($plainText) : trim($htmlText);
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
        }
        imap_close($inbox);
        // ส่ง subject+from ทั้งหมดกลับมาด้วยถ้าไม่พบ OTP เพื่อ debug
        return [
            'otp' => null,
            'debug_subjects' => $debugSubjects,
        ];
    }

    /**
     * ดึงเลข OTP (4-8 หลัก) จากเนื้อหาอีเมล
     */
    private function extractOtp($body)
    {
        if (preg_match('/\b\d{4,8}\b/', $body, $matches)) {
            return $matches[0];
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
        $mailbox = $mailbox ?: '{imap.gmail.com:993/imap/ssl}INBOX';
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
