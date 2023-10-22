<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Provider;


use Doctrine\ORM\EntityManager;
use Generator;
use Pavelvais\SnapshotDoctrine\Entity\Snapshot;
use Pavelvais\SnapshotDoctrine\Exception\FailedDataReceivedException;
use Pavelvais\SnapshotDoctrine\Exception\RollbackException;

interface SnapshotProvider
{

    public function __construct(EntityManager $entityManager);

    /**
     * @throws FailedDataReceivedException
     */
    public function takeSnapshotData(?array $arguments, ?int $limit = null, ?int $offset = null): Generator;

    /**
     * @return int Number of recovered rows
     * @throws RollbackException
     */
    public function rollbackSnapshotData(Snapshot $snapshot): int;

    /**
     * Returns maximal number of snapshots to be stored. if zero is returned, no limit is applied.
     * @return int
     */
    public function getMaximalSnapshotCount(): int;
}
