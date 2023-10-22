<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Provider;

use Doctrine\ORM\EntityManager;
use Generator;
use Pavelvais\SnapshotDoctrine\Entity\Snapshot;
use Pavelvais\SnapshotDoctrine\Exception\FailedDataReceivedException;

class CommonSnapshotProvider implements SnapshotProvider
{
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array|null $arguments
     * @param int|null $limit
     * @param int|null $offset
     * @return Generator|array[]
     * @throws FailedDataReceivedException
     */
    public function takeSnapshotData(
        ?array $arguments,
        ?int $limit = null,
        ?int $offset = null,
    ): Generator
    {
        // Your database logic here
        $query = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\YourEntity', 't');

        // Apply limit and offset if they are set
        if ($limit !== null) {
            $query->setMaxResults($limit);
        }

        if ($offset !== null) {
            $query->setFirstResult($offset);
        }

        $result = $query->getQuery()->toIterable();

        foreach ($result as $row) {
            yield $row;
        }
    }

    public function rollbackSnapshotData(Snapshot $snapshot): int
    {
        // TODO: Implement rollbackSnapshotData() method.
    }

    public function getMaximalSnapshotCount(): int
    {
       return 0;
    }
}
