<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Exception;

use Pavelvais\SnapshotDoctrine\Storage\SnapshotStorageInterface;

class SnapshotException extends \Exception
{

    public function __construct(
        protected string $storageName,
        string $message = "",
        int $code = 0,
        \Throwable $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
