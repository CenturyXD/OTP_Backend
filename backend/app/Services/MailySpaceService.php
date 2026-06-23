<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MailySpaceService
{
    private const API_URL = 'https://api.maily.space/v1/mails';

    public function __construct(private ImapOtpService $imapOtpService = new ImapOtpService()) {}

    /**
     * ดึง OTP ล่าสุดจาก Maily Space API (mail_type = domain)
     */
    public function fetchLatestOtp(
        string $apiKey,
        string $email,
        string $search,
        int $size = 1,
        int $page = 1
    ): array {
        try {
            $client = Http::timeout(30)->acceptJson()->asJson();

            if (config('app.env') === 'local') {
                $client = $client->withoutVerifying();
            }

            $response = $client->post(self::API_URL, [
                'apiKey' => $apiKey,
                'email'  => $email,
                'size'   => $size,
                'page'   => $page,
                'search' => $search,
            ]);
        } catch (\Throwable $e) {
            return [
                'error_type'    => 'maily_unavailable',
                'error_message' => $e->getMessage(),
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return [
                'error_type'    => 'auth_failed',
                'error_message' => $response->json('message') ?? 'Maily Space API key ไม่ถูกต้อง',
            ];
        }

        if (!$response->successful()) {
            return [
                'error_type'    => 'maily_unavailable',
                'error_message' => $response->json('message') ?? ('HTTP ' . $response->status()),
            ];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return [
                'error_type'    => 'maily_unavailable',
                'error_message' => 'รูปแบบข้อมูลจาก Maily Space ไม่ถูกต้อง',
            ];
        }

        $mails = $payload['data']['mails'] ?? $payload['mails'] ?? [];
        if (!is_array($mails) || count($mails) === 0) {
            return [
                'otp'            => null,
                'debug_subjects' => [],
            ];
        }

        $debugSubjects = [];
        foreach ($mails as $mail) {
            if (!is_array($mail)) {
                continue;
            }

            $subject = (string)($mail['subject'] ?? '');
            $from    = (string)($mail['from'] ?? '');
            $date    = (string)($mail['createdAt'] ?? '');
            $body    = trim((string)($mail['text'] ?? ''));

            if ($body === '') {
                $body = trim((string)($mail['snippet'] ?? ''));
            }

            if ($body === '') {
                $body = trim(strip_tags((string)($mail['html'] ?? '')));
            }

            $debugSubjects[] = [
                'subject' => $subject,
                'from'    => $from,
                'date'    => $date,
            ];

            $otp = $this->imapOtpService->extractOtpFromBody($body);
            if ($otp) {
                return [
                    'otp'     => $otp,
                    'subject' => $subject,
                    'from'    => $from,
                    'date'    => $date,
                ];
            }
        }

        return [
            'otp'            => null,
            'debug_subjects' => $debugSubjects,
        ];
    }
}
