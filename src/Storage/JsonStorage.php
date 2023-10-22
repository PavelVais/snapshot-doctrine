<?php declare(strict_types=1);


namespace Pavelvais\SnapshotDoctrine\Storage;

use Exception;
use Generator;
use Pavelvais\SnapshotDoctrine\Exception\StorageException;

class JsonStorage implements SnapshotStorageInterface
{

    public function __construct(protected string $filePath)
    {
    }

    /**
     * @throws StorageException
     */
    public function save(Generator $data): bool
    {
        try {
            $handle = fopen($this->filePath, 'w');
            if (!$handle) {
                throw new StorageException('JsonStorage', "Failed to open file for writing: {$this->filePath}");
            }

            fwrite($handle, '[');
            $first = true;

            foreach ($data as $row) {
                $jsonData = json_encode($row);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new StorageException('JsonStorage', "JSON encoding failed: " . json_last_error_msg());

                }

                if (!$first) {
                    fwrite($handle, ',');
                } else {
                    $first = false;
                }

                if (fwrite($handle, $jsonData) === false) {
                    throw new StorageException('JsonStorage', "Failed to write to file: {$this->filePath}");
                }
            }

            fwrite($handle, ']');

            if (fclose($handle) === false) {
                throw new StorageException('JsonStorage', "Failed to close file: {$this->filePath}");
            }
        } catch (Exception $e) {
            throw new StorageException(
                storageName: 'JsonStorage',
                message: "Failed to save snapshot: {$e->getMessage()}",
                previous: $e
            );
        }

        return true;
    }
}

