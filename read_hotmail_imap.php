<?php

$email = 'sattawat.ticket@gmail.com';
$appPassword = 'xhgn dljd hxle mlki';
$mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';

function decodeMimeStr($string, $charset = 'UTF-8')
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

function decodeBodyByEncoding($body, $encoding)
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

function convertToUtf8($text, $charset = 'UTF-8')
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

function getAllTextParts($inbox, $msgNumber, $structure, $partNumber = '')
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

        $decoded = decodeBodyByEncoding($body, $structure->encoding ?? 0);

        $charset = 'UTF-8';

        if (!empty($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute ?? '') === 'charset') {
                    $charset = $param->value;
                    break;
                }
            }
        }

        $decoded = convertToUtf8($decoded, $charset);

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
                getAllTextParts($inbox, $msgNumber, $part, $currentPartNumber)
            );
        }
    }

    return $results;
}

function extractOtp($text)
{
    $text = trim((string)$text);

    // แบบไทยตรง ๆ
    if (preg_match('/รหัสยืนยันของคุณคือ\s*([0-9]{4,8})/u', $text, $m)) {
        return $m[1];
    }

    // แบบไทยเผื่อข้อความคั่น
    if (preg_match('/รหัสยืนยัน.*?([0-9]{4,8})/u', $text, $m)) {
        return $m[1];
    }

    // แบบอังกฤษ
    if (preg_match('/(?:otp|verification code|code).*?([0-9]{4,8})/iu', $text, $m)) {
        return $m[1];
    }

    // fallback: เอาเลข 4-8 หลักตัวสุดท้าย
    if (preg_match_all('/\b([0-9]{4,8})\b/', $text, $all) && !empty($all[1])) {
        return end($all[1]);
    }

    return null;
}

function isTargetForwardMail($from, $subject, $targetEmail)
{
    $from = strtolower((string)$from);
    $subject = strtolower(trim((string)$subject));
    $targetEmail = strtolower(trim((string)$targetEmail));

    $isFromTarget = strpos($from, $targetEmail) !== false;
    $isForward = preg_match('/^(fw|fwd)\s*:/i', $subject) === 1;

    return $isFromTarget && $isForward;
}

$inbox = imap_open($mailbox, $email, $appPassword);

if (!$inbox) {
    die('Connection failed: ' . imap_last_error());
}

$numMessages = imap_num_msg($inbox);

if ($numMessages < 1) {
    imap_close($inbox);
    die('No messages found');
}

$foundOtp = null;
$foundSubject = '';
$foundFrom = '';
$foundDate = '';
$foundBody = '';
$targetSender = 'sattawat.test@hotmail.com';

for ($i = $numMessages; $i >= 1; $i--) {
    $overview = imap_fetch_overview($inbox, $i, 0);

    if (empty($overview)) {
        continue;
    }

    $subject = decodeMimeStr($overview[0]->subject ?? '-');
    $from = decodeMimeStr($overview[0]->from ?? '-');
    $date = $overview[0]->date ?? '-';

    if (!isTargetForwardMail($from, $subject, $targetSender)) {
        continue;
    }

    $structure = imap_fetchstructure($inbox, $i);
    $parts = getAllTextParts($inbox, $i, $structure);

    $plainText = '';
    $htmlText = '';

    foreach ($parts as $part) {
        if ($part['subtype'] === 'plain') {
            $plainText .= "\n" . $part['content'];
        } elseif ($part['subtype'] === 'html') {
            $htmlText .= "\n" . strip_tags($part['content']);
        }
    }

    $mailText = trim($plainText);
    if ($mailText === '') {
        $mailText = trim($htmlText);
    }

    $searchText = $subject . "\n" . $mailText;
    $otp = extractOtp($searchText);

    if ($otp !== null) {
        $foundOtp = $otp;
        $foundSubject = $subject;
        $foundFrom = $from;
        $foundDate = $date;
        $foundBody = $mailText;
        break;
    }
}

imap_close($inbox);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>OTP ล่าสุด</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #111;
        }

        h1 {
            margin-bottom: 10px;
        }

        .otp {
            font-size: 56px;
            font-weight: bold;
            color: #00a7b5;
            margin-bottom: 20px;
        }

        .box {
            border: 1px solid #ddd;
            padding: 16px;
            background: #fafafa;
        }

        .meta {
            line-height: 1.8;
            margin-bottom: 20px;
        }

        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #fff;
            border: 1px solid #eee;
            padding: 12px;
            overflow: auto;
        }
    </style>
</head>

<body>
    <?php if ($foundOtp !== null): ?>
        <div class="otp">OTP:<?= htmlspecialchars($foundOtp) ?></div>
    <?php else: ?>
        <div class="otp">ไม่พบ OTP</div>
    <?php endif; ?>
</body>

</html>