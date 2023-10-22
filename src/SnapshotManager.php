<?php declare(strict_types=1);

use Pavelvais\SnapshotDoctrine\Entity\Snapshot;
use Pavelvais\SnapshotDoctrine\Exception\FailedDataReceivedException;
use Pavelvais\SnapshotDoctrine\Exception\RollbackException;
use Pavelvais\SnapshotDoctrine\Exception\SnapshotException;
use Pavelvais\SnapshotDoctrine\Provider\SnapshotProvider;
use Psr\Log\LoggerInterface;


/**
 * DataSnapshotManager is responsible for taking and rolling back snapshots of any kind of data.
 * @var callable|null
 */
class SnapshotManager
{
    /**
     * @var callable|null
     */
    private $previousErrorHandler;
    private ?Snapshot $lastSnapshot = null;
    private bool $rollbackExecuted = false;
    /**
     * If rollback happens, this callback is called. Snapshot entity is passed as argument.
     * @var callable|null
     */
    private $rollbackCallback = null;
    private ?SnapshotProvider $errorHandlerSnapshotProvider = null;

    public function __construct(private string $snapshotFolder)
    {
        $this->snapshotFolder = rtrim($this->snapshotFolder, '/');
    }

    /**
     * @param SnapshotProvider $snapshotProvider
     * @param string $snapshotName
     * @param array|null $arguments
     * @return Snapshot
     * @throws FailedDataReceivedException
     */
    public function takeSnapshot(
        SnapshotProvider $snapshotProvider,
        string $snapshotName,
        ?array $arguments,
    ): Snapshot
    {
        $snapshotData = $snapshotProvider->takeSnapshotData($arguments);
        $snapshotName = rtrim($snapshotName, '.json');

        $this->errorHandlerSnapshotProvider = $snapshotProvider;

        return $this->lastSnapshot = $this->save(
            maxSnapshotCount: $snapshotProvider->getMaximalSnapshotCount(),
            snapshotName: $snapshotName,
            snapshotData: $snapshotData,
            arguments: $arguments
        );
    }

    /**
     * @throws RollbackException
     */
    public function rollbackSnapshot(SnapshotProvider $snapshotProvider, Snapshot $snapshot): int
    {
        try {
            $getRollbackCount = $snapshotProvider->rollbackSnapshotData($snapshot);
            $this->rollbackExecuted = true;

            if ($this->rollbackCallback) {
                call_user_func($this->rollbackCallback, $snapshot);
            }

            return $getRollbackCount;
        } catch (RollbackException $e) {
            captureException($e);
            throw $e;
        }
    }


    /**
     * @throws SnapshotException
     * @throws RollbackException
     */
    public function rollbackLastSnapshot(SnapshotProvider $snapshotProvider, string $snapshotName): ?int
    {
        $snapshots = $this->getSnapshots($snapshotName);
        if (!count($snapshots)) {
            return null;
        }
        return $this->rollbackSnapshot($snapshotProvider, end($snapshots));
    }

    /**
     * Registers an error handler that will rollback the last snapshot when an error occurs.
     * It cannot be called twice. If there is no snaphot taken during this request, it does nothing.
     * @param LoggerInterface|null $logger
     * @return void
     */
    public function registerErrorRollback(?LoggerInterface $logger): void
    {
        // Save the previous error handler
        $this->previousErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($logger) {

            // Ignore warning or notice errors
            if (!(error_reporting() & $errno)) {
                return;
            }

            $logger?->warning(
                message: 'Rollback initiated by error started. Error: ' . $errstr,
                context: [
                    'error' => $errstr,
                    'file' => $errfile,
                    'line' => $errline,
                ]
            );

            $this->handleErrorSignalRollback($logger);

            // Call the previous error handler, if available
            if ($this->previousErrorHandler) {
                call_user_func($this->previousErrorHandler, $errno, $errstr, $errfile, $errline);
            }
        });

        // if pcntl extension is not loaded, we cannot register signal handlers
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, function () use ($logger) {
                $logger?->warning('Rollback initiated by signal SIGTERM');
                sleep(1);
                $this->handleErrorSignalRollback($logger);
            });

            pcntl_signal(SIGINT, function () use ($logger) {
                $logger?->warning('Rollback initiated by signal SIGINT');
                sleep(1);
                $this->handleErrorSignalRollback($logger);
            });
        } else {
            $logger?->warning('Error handling for rollback operation was registered, but pcntl extension is not loaded.');
        }
    }


    public function registerRollbackCallback(callable $callback): static
    {
        $this->rollbackCallback = $callback;
        return $this;
    }


    /**
     * Its called from error handler and signal handler when error is detected.
     * It takes last snapshot and rollback it. When snapshot is not available, it does nothing.
     * It cannot be called twice.
     * @param LoggerInterface|null $logger
     * @return bool
     */
    public function handleErrorSignalRollback(?LoggerInterface $logger): bool
    {
        if ($this->rollbackExecuted || $this->lastSnapshot === null) {
            $logger?->info('Rollback was not executed: No actual snapshot available.');
            return false;
        }

        try {
            $ms = microtime(true);
            $this->rollbackSnapshot($this->errorHandlerSnapshotProvider, $this->lastSnapshot);
            $logger?->info('Rollback was successful finished. Time: ' . (microtime(true) - $ms) . 's');

        } catch (Throwable $e) {
            // we must catch all exceptions here, because we are in error handler
            $logger?->critical('Disaster recovery failed. Error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return false;
        }
        return true;
    }

    /**
     * @throws SnapshotException
     */
    public function deleteAllSnapshots(?string $snapshotName): int
    {
        $snapshots = $this->getSnapshots($snapshotName);
        foreach ($snapshots as $snapshot) {
            unlink($snapshot->getPath());
        }
        return count($snapshots);
    }

    private function save(
        int $maxSnapshotCount,
        string $snapshotName,
        Generator $snapshotData,
        ?array $arguments,
    ): Snapshot
    {
        $lastIndex = $this->manageSnapshotCount($maxSnapshotCount, $snapshotName);
        $snapshot = new Snapshot($this->snapshotFolder . '/' . $snapshotName . '_' . (++$lastIndex));
        $snapshot->saveSnapshot($snapshotData, $arguments);
        return $snapshot;
    }

    /**
     * Simple implementation of snapshot count management. Maybe in the future we will need something more
     * sophisticated like a snapshot strategies.
     * @param int $maxSnapshotCount
     * @param string $snapshotName
     * @return int Number of last snapshot
     * @throws SnapshotException
     */
    private function manageSnapshotCount(int $maxSnapshotCount, string $snapshotName): int
    {
        $snapshots = $this->getSnapshots($snapshotName);
        if (count($snapshots) > $maxSnapshotCount && $maxSnapshotCount > 0) {
            // Delete excess snapshots to maintain the limit
            $snapshotsToDelete = count($snapshots) - $maxSnapshotCount;
            for ($i = 0; $i < $snapshotsToDelete; $i++) {
                unlink($snapshots[$i]->getPath());
            }
        }
        if (count($snapshots) === 0) {
            return 0;
        }

        // getting index of last snapshot
        return (int)explode('_', $snapshots[count($snapshots) - 1]->getPath())[1];
    }

    /**
     * Retrieves an array of snapshots based on the provided snapshot name.
     * The snapshots are sorted by creation time (oldest first).
     *
     * @param string|null $snapshotName The name of the snapshot to retrieve.
     * @return Snapshot[] An array of Snapshot objects representing the found snapshots.
     * @throws SnapshotException
     */
    public function getSnapshots(?string $snapshotName): array
    {
        $snapshots = [];
        $globPath = $this->snapshotFolder . '/' . $snapshotName . '*.json';
        $existingSnapshots = glob($globPath);

        if ($existingSnapshots === false) {
            throw new SnapshotException(sprintf('Unable to get valid path for snapshots: %s', $globPath));
        }

        // Sort the snapshot files by creation time (oldest first)
        usort($existingSnapshots, fn($a, $b) => filemtime($a) - filemtime($b));

        foreach ($existingSnapshots as $snapshot) {
            $snapshots[] = new Snapshot($snapshot);
        }

        return $snapshots;
    }
}
