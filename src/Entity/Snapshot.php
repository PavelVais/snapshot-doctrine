<?php declare(strict_types=1);

namespace Pavelvais\SnapshotDoctrine\Entity;

class Snapshot
{
    private ?array $data = null;
    private ?array $arguments;

    public function __construct(private string $snapshotPath)
    {
        $this->snapshotPath = rtrim($this->snapshotPath, '.json') . '.json';
    }

    public function saveSnapshot(\Generator $snapshotData, ?array $arguments): bool
    {
        $jsonData = json_encode([
            'arguments' => $arguments,
            'data' => $snapshotData,
        ], JSON_PRETTY_PRINT);

        if ($jsonData !== false) {
            if (!file_exists($this->snapshotPath)) {
                $dir = dirname($this->snapshotPath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
            }
            return file_put_contents($this->snapshotPath, $jsonData) !== false;
        }

        return false;
    }

    public function hasSnapshot(): bool
    {
        return file_exists($this->snapshotPath);
    }

    public function getData(): array
    {
        $this->loadData();
        return $this->data;
    }

    public function getArguments(): ?array
    {
        $this->loadData();
        return $this->arguments;
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . filemtime($this->snapshotPath));
    }

    public function getPath(): string
    {
        return $this->snapshotPath;
    }

    private function loadData(): void
    {
        if ($this->data === null && $this->hasSnapshot()) {
            $data = json_decode(file_get_contents($this->snapshotPath), true);
            $this->data = $data['data'];
            $this->arguments = $data['arguments'];
        }
    }
}
