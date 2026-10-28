<?php

namespace App\Services;

use Exception;
use RuntimeException;
use Illuminate\Support\Str;

class EnvelopeEncryptionService
{
    protected string $masterKey;
    protected string $cipher = 'aes-256-gcm';

    public function __construct()
    {
        $rawKey = config('app.fdr_kek_master_key', env('FDR_KEK_MASTER_KEY', 'base64:4NfV9jQ1P8aT6wE0kX5zR2yU4oL7mI9vS1bA3cE5gH8='));
        if (str_starts_with($rawKey, 'base64:')) {
            $this->masterKey = base64_decode(substr($rawKey, 7));
        } else {
            $this->masterKey = hash('sha256', $rawKey, true);
        }

        if (strlen($this->masterKey) !== 32) {
            throw new RuntimeException("Master Key Encryption Key (KEK) must be 256 bits (32 bytes).");
        }
    }

    /**
     * Encrypts plaintext binary data using Envelope Encryption (AES-256-GCM).
     * Returns encrypted ciphertext, wrapped DEK, nonce, auth tag, and raw sha256 hash.
     */
    public function encryptDocument(string $fileContents): array
    {
        // 1. Calculate raw file SHA-256 hash for integrity validation
        $rawSha256 = hash('sha256', $fileContents);

        // 2. Generate ephemeral 256-bit Data Encryption Key (DEK)
        $dek = random_bytes(32);

        // 3. Generate 12-byte Nonce (IV) for AES-256-GCM
        $nonce = random_bytes(12);

        // 4. Encrypt document with DEK
        $tag = '';
        $ciphertext = openssl_encrypt(
            $fileContents,
            $this->cipher,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '', // AAD
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Document encryption failed: " . openssl_error_string());
        }

        // 5. Wrap (encrypt) the DEK using master KEK
        $kekNonce = random_bytes(12);
        $kekTag = '';
        $wrappedDekRaw = openssl_encrypt(
            $dek,
            $this->cipher,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $kekNonce,
            $kekTag,
            '',
            16
        );

        if ($wrappedDekRaw === false) {
            throw new RuntimeException("DEK key wrapping failed: " . openssl_error_string());
        }

        // Combine wrapped DEK payload (kekNonce + kekTag + wrappedDekRaw)
        $wrappedDekPayload = base64_encode($kekNonce . $kekTag . $wrappedDekRaw);

        return [
            'ciphertext' => $ciphertext,
            'encrypted_dek' => $wrappedDekPayload,
            'nonce' => base64_encode($nonce),
            'auth_tag' => base64_encode($tag),
            'sha256' => $rawSha256,
            'algorithm' => 'AES-256-GCM',
            'kek_version' => 'v1',
        ];
    }

    /**
     * Decrypts ciphertext using Envelope Encryption.
     * Throws an exception immediately if the ciphertext or authentication tag is tampered with!
     */
    public function decryptDocument(
        string $ciphertext,
        string $wrappedDekPayload,
        string $nonceBase64,
        string $authTagBase64
    ): string {
        // 1. Unwrap DEK using KEK
        $rawPayload = base64_decode($wrappedDekPayload);
        if (strlen($rawPayload) < 28) {
            throw new RuntimeException("Invalid wrapped DEK payload structure.");
        }

        $kekNonce = substr($rawPayload, 0, 12);
        $kekTag = substr($rawPayload, 12, 16);
        $wrappedDekRaw = substr($rawPayload, 28);

        $dek = openssl_decrypt(
            $wrappedDekRaw,
            $this->cipher,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $kekNonce,
            $kekTag
        );

        if ($dek === false) {
            throw new RuntimeException("Master KEK failed to unwrap DEK. Possible key mismatch or tampering.");
        }

        // 2. Decrypt document with unwrapped DEK
        $nonce = base64_decode($nonceBase64);
        $tag = base64_decode($authTagBase64);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException("Document decryption failed: Authentication tag mismatch or ciphertext tampering detected!");
        }

        return $plaintext;
    }
}
