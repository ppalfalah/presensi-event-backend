<?php

namespace App\Support\E2E;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class E2EEnvironmentGuard
{
    public function assertSafe(): void
    {
        $environment = app()->environment();
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver");
        $configuredDatabase = (string) config("database.connections.{$connectionName}.database");
        $expectedDatabase = (string) config('e2e.database');

        // Reject an unsafe configuration before opening a database connection.
        self::validateConfiguration($environment, $driver, $configuredDatabase, $expectedDatabase);

        $connection = DB::connection($connectionName);

        self::validate(
            environment: $environment,
            driver: $connection->getDriverName(),
            configuredDatabase: $configuredDatabase,
            activeDatabase: $this->activeDatabase($connection),
            expectedDatabase: $expectedDatabase,
        );

        self::validateStorage(
            configuredRoot: (string) config('filesystems.disks.public.root'),
            expectedRoot: (string) config('e2e.public_storage_root'),
        );
    }

    public static function validateStorage(string $configuredRoot, string $expectedRoot): void
    {
        $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', rtrim($path, '/\\')));

        if ($expectedRoot === '' || $normalize($configuredRoot) !== $normalize($expectedRoot)) {
            throw new RuntimeException(
                "Refusing E2E operation: public storage root \"{$configuredRoot}\" must equal isolated root \"{$expectedRoot}\"."
            );
        }
    }

    public static function validate(
        string $environment,
        string $driver,
        string $configuredDatabase,
        string $activeDatabase,
        string $expectedDatabase,
    ): void {
        self::validateConfiguration($environment, $driver, $configuredDatabase, $expectedDatabase);

        if ($activeDatabase !== $expectedDatabase) {
            throw new RuntimeException(
                "Refusing E2E database operation: configured database \"{$configuredDatabase}\" and active database \"{$activeDatabase}\" must both equal \"{$expectedDatabase}\"."
            );
        }
    }

    private static function validateConfiguration(
        string $environment,
        string $driver,
        string $configuredDatabase,
        string $expectedDatabase,
    ): void {
        if ($environment !== 'e2e') {
            throw new RuntimeException("Refusing E2E database operation: APP_ENV must be e2e, got \"{$environment}\".");
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Refusing E2E database operation: driver \"{$driver}\" is not MySQL/MariaDB.");
        }

        if ($expectedDatabase === '' || ! preg_match('/(?:^|_)e2e$/i', $expectedDatabase)) {
            throw new RuntimeException("Refusing E2E database operation: configured E2E database \"{$expectedDatabase}\" must end with _e2e.");
        }

        if ($configuredDatabase !== $expectedDatabase) {
            throw new RuntimeException(
                "Refusing E2E database operation: configured database \"{$configuredDatabase}\" must equal \"{$expectedDatabase}\"."
            );
        }
    }

    private function activeDatabase(ConnectionInterface $connection): string
    {
        return (string) $connection->scalar('select database()');
    }
}
