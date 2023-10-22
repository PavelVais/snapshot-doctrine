<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Storage;

use Exception;
use Generator;
use Pavelvais\SnapshotDoctrine\Exception\StorageException;

class EncryptedStorage implements SnapshotStorageInterface
{

    public function __construct(
        protected string $filePath,
        protected string $encryptionKey,
        protected string $encryptionAlgorithm = 'aes-256-cbc',
    )
    {
    }

    /**
     * @throws StorageException
     */
    public function save(Generator $data): bool
    {
        $handle = fopen($this->filePath, 'w');
        if (!$handle) {
            throw new StorageException('EncryptedStorage', "Failed to open file for writing: {$this->filePath}");
        }

        try {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->encryptionAlgorithm));
            fwrite($handle, $iv); // Save IV for decryption

            foreach ($data as $row) {
                $jsonData = json_encode($row);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new StorageException('EncryptedStorage', "JSON encoding failed: " . json_last_error_msg());
                }

                $encryptedData = openssl_encrypt($jsonData, $this->encryptionAlgorithm, $this->encryptionKey, 0, $iv);
                if ($encryptedData === false) {
                    throw new StorageException('EncryptedStorage', "Encryption failed");
                }

                fwrite($handle, $encryptedData);
                fwrite($handle, "\n"); // Delimiter
            }

        } catch (Exception $e) {
            // Log or handle exception
            fclose($handle);
            throw new StorageException(
                storageName: 'EncryptedStorage',
                message: "Failed to save snapshot: {$e->getMessage()}",
                previous: $e
            );
        }

        fclose($handle);
        return true;
    }
}
