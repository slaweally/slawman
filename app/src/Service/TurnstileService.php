<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settingsService->isTruthy('turnstile_enabled')
            && $this->settingsService->get('turnstile_site_key') !== ''
            && $this->settingsService->get('turnstile_secret_key') !== '';
    }

    public function getSiteKey(): ?string
    {
        $key = $this->settingsService->get('turnstile_site_key');

        return $key !== '' && $key !== null ? $key : null;
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $secret = $this->settingsService->get('turnstile_secret_key');
        if ($secret === null || $secret === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
                'body' => array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]),
            ]);

            /** @var array{success?: bool} $data */
            $data = $response->toArray(false);

            return ($data['success'] ?? false) === true;
        } catch (HttpClientExceptionInterface|\Throwable) {
            // Timeout / bağlantı hatası / geçersiz yanıt — Turnstile devre dışı gibi davran,
            // sunucuyu asla bloklamaz (eski file_get_contents senkron açık soket tutuyordu).
            return false;
        }
    }
}
