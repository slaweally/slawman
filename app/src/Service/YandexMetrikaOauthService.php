<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuth akışı `yandex-metrica-wordpress/includes/Yandex_Oauth.php`'den birebir taşındı.
 */
final class YandexMetrikaOauthService
{
    private const OAUTH_TOKEN_URL = 'https://oauth.yandex.com/token';

    // Eklentideki sabit app_id/app_secret kasıtlı olarak aynen kullanılıyor
    // (üçüncü taraf OAuth app'i, bilinçli tercih).
    public const YANDEX_APP_ID = 'e1a0017805e24d7b9395f969b379b7bf';
    public const YANDEX_APP_SECRET = '410e753d1ab9478eaa21aa2c3f9a7d88';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsService $settingsService,
        private readonly CredentialEncryptionService $encryptionService,
    ) {
    }

    public function connect(string $confirmationCode): bool
    {
        try {
            $response = $this->httpClient->request('POST', self::OAUTH_TOKEN_URL, [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $confirmationCode,
                    'client_id' => self::YANDEX_APP_ID,
                    'client_secret' => self::YANDEX_APP_SECRET,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200 && $statusCode !== 400) {
                return false;
            }

            $result = $response->toArray(false);
        } catch (\Throwable) {
            return false;
        }

        if (!empty($result['error']) || empty($result['access_token'])) {
            return false;
        }

        $this->settingsService->set('metrika_oauth_access_token', $this->encryptionService->encrypt((string) $result['access_token']));
        $this->settingsService->set('metrika_oauth_authorized', 'true');

        return true;
    }

    public function isAuthorized(): bool
    {
        return $this->settingsService->isTruthy('metrika_oauth_authorized');
    }

    public function getAccessToken(): ?string
    {
        $encrypted = $this->settingsService->get('metrika_oauth_access_token', '');
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        return $this->encryptionService->decrypt($encrypted);
    }
}
