<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GraphMailService
{
    private const DEFAULT_BASE_URL = 'https://graph.microsoft.com/v1.0';

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

    private function baseUrl(): string
    {
        return rtrim((string)config('services.microsoft_graph.base_url', self::DEFAULT_BASE_URL), '/');
    }

    /**
     * ต่ออายุ Access Token โดยใช้ Refresh Token
     * คืนค่า ['access_token' => '...', 'refresh_token' => '...'] หรือ ['error_type' => '...']
     */
    public function refreshAccessToken(
        string $refreshToken,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $tenantId = null
    ): array {
        $clientId = trim((string)($clientId ?? config('services.microsoft_graph.client_id', '')));
        $clientSecret = trim((string)($clientSecret ?? config('services.microsoft_graph.client_secret', '')));
        $tenantId = trim((string)($tenantId ?? config('services.microsoft_graph.tenant_id', 'common')));
        if ($tenantId === '') {
            $tenantId = 'common';
        }

        if ($clientId === '' || $clientSecret === '') {
            return [
                'error_type'    => 'config_missing',
                'error_message' => 'ไม่พบ MICROSOFT_CLIENT_ID หรือ MICROSOFT_CLIENT_SECRET ใน .env',
            ];
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()->timeout(20)->post($url, [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope'         => 'https://graph.microsoft.com/Mail.Read offline_access',
        ]);

        if ($response->successful()) {
            $body = $response->json();
            return [
                'access_token'  => (string)($body['access_token']  ?? ''),
                'refresh_token' => (string)($body['refresh_token'] ?? $refreshToken),
                'expires_in'    => (int)($body['expires_in'] ?? 3600),
            ];
        }

        $errorDesc = (string)($response->json()['error_description'] ?? '');
        $errorCode = (string)($response->json()['error'] ?? '');
        $errorType = in_array($errorCode, ['invalid_grant', 'interaction_required'], true)
            ? 'refresh_token_expired'
            : 'auth_failed';

        return [
            'error_type'    => $errorType,
            'error_message' => $errorDesc ?: ('Token refresh failed with status ' . $response->status()),
        ];
    }

    private function graphGet(string $path, string $accessToken, array $query = []): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get($this->baseUrl() . $path, $query);

        if ($response->successful()) {
            return [
                'ok' => true,
                'data' => $response->json(),
            ];
        }

        $errorMessage = data_get($response->json(), 'error.message')
            ?: ('Graph request failed with status ' . $response->status());

        $errorType = $response->status() === 401 ? 'auth_failed' : 'graph_unavailable';

        return [
            'ok' => false,
            'status' => $response->status(),
            'error_type' => $errorType,
            'error_message' => $errorMessage,
        ];
    }

    private function normalizeMessage(array $message): array
    {
        $subject = (string)($message['subject'] ?? '');
        $fromName = (string)data_get($message, 'from.emailAddress.name', '');
        $fromAddress = (string)data_get($message, 'from.emailAddress.address', '');
        $from = trim($fromName . ' <' . $fromAddress . '>');

        $bodyType = strtolower((string)data_get($message, 'body.contentType', ''));
        $bodyContent = (string)data_get($message, 'body.content', '');
        $bodyPreview = (string)($message['bodyPreview'] ?? '');

        $body = $bodyContent !== '' ? $bodyContent : $bodyPreview;
        if ($bodyType === 'html') {
            $body = strip_tags($body);
        }
        $body = $this->normalizeBody($body);

        return [
            'subject' => $subject,
            'from' => $from,
            'date' => (string)($message['receivedDateTime'] ?? ''),
            'body' => trim($body),
        ];
    }

    private function extractOtp(string $body): ?string
    {
        $normalizedBody = $this->normalizeBody($body);
        $keywordPattern = '(?:otp|one\s*time|verification|verify|security|passcode|pin|code|รหัส|ยืนยัน)';

        if (
            preg_match('/' . $keywordPattern . '\D{0,30}((?:\d[\s\-]?){4,8})/iu', $normalizedBody, $matches) ||
            preg_match('/((?:\d[\s\-]?){4,8})\D{0,30}' . $keywordPattern . '/iu', $normalizedBody, $matches)
        ) {
            $otp = $this->extractOtpCandidate((string)($matches[1] ?? $matches[0] ?? ''));
            if ($otp !== null) {
                return $otp;
            }
        }

        if (preg_match('/(?<!\d)(\d{4,8})(?!\d)/', $normalizedBody, $matches)) {
            return (string)$matches[1];
        }

        if (preg_match('/(?<!\d)((?:\d[\s\-]?){4,8})(?!\d)/', $normalizedBody, $matches)) {
            return $this->extractOtpCandidate((string)$matches[1]);
        }

        return null;
    }

    public function fetchInboxEmails(int $maxFetch, string $accessToken, string $mailFolder = 'inbox'): array
    {
        $query = [
            '$top' => min(max($maxFetch, 1), 50),
            '$orderby' => 'receivedDateTime desc',
            '$select' => 'subject,from,receivedDateTime,bodyPreview,body',
        ];

        $result = $this->graphGet('/me/mailFolders/' . rawurlencode($mailFolder) . '/messages', $accessToken, $query);
        if (!$result['ok']) {
            return [
                'error_type' => $result['error_type'],
                'error_message' => $result['error_message'],
            ];
        }

        $messages = (array)($result['data']['value'] ?? []);

        return array_map(fn(array $message) => $this->normalizeMessage($message), $messages);
    }

    public function fetchLatestOtpFromFolders(string $accessToken, string $service, array $folders = ['inbox', 'junkemail'], int $maxSearch = 20): array
    {
        $bestResult = null;
        $bestTimestamp = 0;
        $lastError = null;
        $combinedDebugSubjects = [];

        foreach ($folders as $folder) {
            $result = $this->fetchLatestOtpFromInbox($accessToken, $service, $folder, $maxSearch);

            if (!is_array($result)) {
                continue;
            }
            if (!empty($result['error_type'])) {
                $lastError = $result;
                continue;
            }
            if (!empty($result['debug_subjects'])) {
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
            return $bestResult;
        }

        return $lastError ?? ['otp' => null, 'debug_subjects' => $combinedDebugSubjects];
    }

    public function fetchLatestOtpFromInbox(string $accessToken, string $service, string $mailFolder = 'inbox', int $maxSearch = 20): array
    {
        $emails = $this->fetchInboxEmails($maxSearch, $accessToken, $mailFolder);

        if (isset($emails['error_type'])) {
            return $emails;
        }

        if (empty($emails)) {
            return [];
        }

        $serviceLower = mb_strtolower($service);
        $debugSubjects = [];

        foreach ($emails as $email) {
            $subject = (string)($email['subject'] ?? '');
            $from = (string)($email['from'] ?? '');
            $body = (string)($email['body'] ?? '');
            $debugSubjects[] = [
                'subject' => $subject,
                'from' => $from,
            ];

            $match = false;
            if ($serviceLower === 'netflix') {
                $match = (mb_stripos($subject, 'netflix') !== false) || (mb_stripos($from, 'netflix') !== false);
            } elseif ($serviceLower === 'disney+' || $serviceLower === 'disney') {
                $match = (mb_stripos($from, 'disney') !== false) || (mb_stripos($subject, 'disney') !== false);
            } else {
                $match = $serviceLower !== '' && (
                    mb_stripos($subject, $serviceLower) !== false ||
                    mb_stripos($from, $serviceLower) !== false ||
                    mb_stripos($body, $serviceLower) !== false
                );
            }

            if (!$match) {
                continue;
            }

            $otp = $this->extractOtp($body);
            if ($otp) {
                return [
                    'otp' => $otp,
                    'subject' => $subject,
                    'from' => $from,
                    'date' => $email['date'] ?? '',
                ];
            }
        }

        return [
            'otp' => null,
            'debug_subjects' => $debugSubjects,
        ];
    }
}
