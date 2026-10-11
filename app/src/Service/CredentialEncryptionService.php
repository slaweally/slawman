<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * AES-256-GCM (authenticated) şifreleme. Önceki sürüm `yandex-metrica-wordpress/includes/Encryption.php`'den
 * birebir taşınan AES-256-CTR kullanıyordu — CTR authenticated olmadığı için ciphertext bit-flipping'e
 * açıktı. GCM'e geçildi; decrypt() geriye dönük olarak eski CTR formatını da çözebilir, böylece
 * önceden kaydedilmiş değerler bir sonraki encrypt() çağrısında sorunsuzca yeni formata geçer.
 */
final class CredentialEncryptionService
{
    private const METHOD = 'aes-256-gcm';
    private const LEGACY_METHOD = 'aes-256-ctr';

    private readonly string $key;
    private readonly string $salt;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = $secret;
        $this->salt = hash('sha256', $secret . '_metrika_salt');
    }

    public function encrypt(string $value): string
    {
        if (!extension_loaded('openssl')) {
            return $value;
        }

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $tag = '';
        $rawValue = openssl_encrypt($value . $this->salt, self::METHOD, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($rawValue === false) {
            return $value;
        }

        return base64_encode($iv . $tag . $rawValue);
    }

    public function decrypt(string $value): ?string
    {
        if (!extension_loaded('openssl')) {
            return $value;
        }

        $rawValue = base64_decode($value, true);
        if ($rawValue === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $tagLength = 16;
        $iv = substr($rawValue, 0, $ivLength);
        $tag = substr($rawValue, $ivLength, $tagLength);
        $encrypted = substr($rawValue, $ivLength + $tagLength);

        $decrypted = openssl_decrypt($encrypted, self::METHOD, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted !== false && substr($decrypted, -strlen($this->salt)) === $this->salt) {
            return substr($decrypted, 0, -strlen($this->salt));
        }

        return $this->decryptLegacy($rawValue);
    }

    /** Eski AES-256-CTR formatıyla şifrelenmiş değerleri okuyabilmek için geriye dönük destek. */
    private function decryptLegacy(string $rawValue): ?string
    {
        $ivLength = openssl_cipher_iv_length(self::LEGACY_METHOD);
        $iv = substr($rawValue, 0, $ivLength);
        $encrypted = substr($rawValue, $ivLength);

        $decrypted = openssl_decrypt($encrypted, self::LEGACY_METHOD, $this->key, 0, $iv);
        if ($decrypted === false || substr($decrypted, -strlen($this->salt)) !== $this->salt) {
            return null;
        }

        return substr($decrypted, 0, -strlen($this->salt));
    }
}
