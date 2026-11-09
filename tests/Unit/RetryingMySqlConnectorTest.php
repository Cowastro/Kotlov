<?php

namespace Tests\Unit;

use App\Database\RetryingMySqlConnector;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

class RetryingMySqlConnectorTest extends TestCase
{
    public function test_it_retries_the_hosting_user_connection_limit(): void
    {
        $connector = new class extends RetryingMySqlConnector
        {
            public int $attempts = 0;

            /** @var list<int> */
            public array $delays = [];

            protected function connectOnce(array $config): PDO
            {
                $this->attempts++;

                if ($this->attempts < 3) {
                    throw new PDOException("User has exceeded the 'max_user_connections' resource", 1226);
                }

                return new PDO('sqlite::memory:');
            }

            protected function pause(int $microseconds): void
            {
                $this->delays[] = $microseconds;
            }
        };

        $connection = $connector->connect([]);

        $this->assertInstanceOf(PDO::class, $connection);
        $this->assertSame(3, $connector->attempts);
        $this->assertSame([100_000, 250_000], $connector->delays);
    }

    public function test_it_does_not_retry_unrelated_connection_errors(): void
    {
        $connector = new class extends RetryingMySqlConnector
        {
            public int $attempts = 0;

            protected function connectOnce(array $config): PDO
            {
                $this->attempts++;

                throw new PDOException('Access denied', 1045);
            }

            protected function pause(int $microseconds): void
            {
                self::fail('An unrelated connection error must not be delayed.');
            }
        };

        try {
            $connector->connect([]);
            self::fail('The original connection exception should be rethrown.');
        } catch (PDOException $exception) {
            $this->assertSame(1045, $exception->getCode());
            $this->assertSame(1, $connector->attempts);
        }
    }
}
