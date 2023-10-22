<?php declare(strict_types=1);


namespace Pavelvais\SnapshotDoctrine\Storage;

use Exception;
use Generator;
use Pavelvais\SnapshotDoctrine\Exception\StorageException;

class CompressedStorage implements SnapshotStorageInterface
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
            $fileHandler = gzopen($this->filePath, 'w9');
            foreach ($data as $item) {
                $jsonData = json_encode($item);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new StorageException('CompressedStorage', "JSON encoding failed: " . json_last_error_msg());
                }
                gzwrite($fileHandler, $jsonData . PHP_EOL);
            }
            gzclose($fileHandler);
        } catch (Exception $e) {
            throw new StorageException(
                storageName: 'CompressedStorage',
                message: "Failed to save snapshot: {$e->getMessage()}",
                previous: $e
            );
        }

        return true;
    }
}
