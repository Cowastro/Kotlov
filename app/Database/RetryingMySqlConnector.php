<?php

namespace App\Database;

use Illuminate\Database\Connectors\MySqlConnector;
use PDO;
use PDOException;

class RetryingMySqlConnector extends MySqlConnector
{
    /** @var list<int> */
    private const RETRY_DELAYS_MICROSECONDS = [100_000, 250_000, 500_000, 1_000_000, 2_000_000];

    public function connect(array $config): PDO
    {
        foreach (self::RETRY_DELAYS_MICROSECONDS as $delay) {
            try {
                return $this->connectOnce($config);
            } catch (PDOException $exception) {
                if (! $this->isUserConnectionLimit($exception)) {
                    throw $exception;
                }

                $this->pause($delay);
            }
        }

        return $this->connectOnce($config);
    }

    protected function connectOnce(array $config): PDO
    {
        return parent::connect($config);
    }

    protected function pause(int $microseconds): void
    {
        usleep($microseconds);
    }

    private function isUserConnectionLimit(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '1226'
            || str_contains($exception->getMessage(), 'max_user_connections')
            || str_contains($exception->getMessage(), 'exceeded the');
    }
}
