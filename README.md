# Snapshot Doctrine

WIP: This library is still in development and not ready for production use.

## Description

This library provides an easy way to take snapshots of your database using Doctrine ORM in PHP.
It allows you to save and rollback snapshots, offering various storage options like clean JSON storage, compressed, and encrypted storage.

## Why to use this solution

- **Efficiency**: Utilizes generators for handling large datasets without exhausting memory.
- **Flexibility**: Provides multiple storage options, including JSON, compressed, and encrypted storage.
- **Extensibility**: Easily extendable to support additional storage mechanisms.
- **Simplicity**: No additional dependencies required. Easy to use and understand.
- **Easy to Integrate**: Designed to work seamlessly with Doctrine ORM.

### Scenarios

#### Replacement for database transactions

When some scripts takes a long time to run, it's impossible to run it in a single transaction. In this case, you can use this library to take a snapshot of the database before running the script. If the script fails, you can rollback the database to the snapshot.

## Installation

```bash
composer require pavelvais/snapshot-doctrine
```

## Usage

### Basic Usage

```php
use Pavelvais\SnapshotDoctrine\Provider\CommonSnapshotProvider;

// Initialize with Doctrine's EntityManager
$provider = new CommonSnapshotProvider($entityManager);
$manager = new SnapshotManager('var/snapshots');

// Take a snapshot
$snapshot = $manager->takeSnapshot($provider);

// Rollback to a snapshot
$provider->rollbackSnapshotData($snapshot);
```

### There are other storage options available

### With JSON Storage
### Compressed Storage
```php
use Pavelvais\SnapshotDoctrine\Storage\CompressedStorage;

$storage = new CompressedStorage('/path/to/compressed/file.gz');
```
### Encrypted Storage
```php
use Pavelvais\SnapshotDoctrine\Storage\EncryptedStorage;

$storage = new EncryptedStorage('/path/to/encrypted/file', 'your-encryption-key');
```

## Storage Implementations

You can extend this library by implementing your own storage solutions. Implement the `SnapshotStorageInterface` to get started.

## Contributing

Please feel free to contribute by opening issues or creating pull requests.

## License

This project is licensed under the MIT License.
