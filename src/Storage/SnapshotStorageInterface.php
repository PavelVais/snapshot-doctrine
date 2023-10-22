<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Storage;

use Generator;

interface SnapshotStorageInterface
{
    public function save(Generator $data): bool;
}
