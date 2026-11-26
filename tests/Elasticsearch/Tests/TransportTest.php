<?php

namespace Elasticsearch\Tests;

use Elasticsearch\ConnectionPool\AbstractConnectionPool;
use Elasticsearch\Connections\Connection;
use Elasticsearch\Transport;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Future\CompletedFutureArray;
use Mockery as m;
use Psr\Log\NullLogger;

class TransportTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown(): void
    {
        m::close();
    }

    /**
     * @dataProvider failures
     */
    public function testHandlesAFailedRequest(\Throwable $failure, int $checks)
    {
        $future = Core::proxy(new CompletedFutureArray([]), function () use ($failure) {
            throw $failure;
        });
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('performRequest')->once()->andReturn($future);
        $pool = m::mock(AbstractConnectionPool::class);
        $pool->shouldReceive('nextConnection')->once()->andReturn($connection);
        $pool->shouldReceive('scheduleCheck')->times($checks);

        $transport = new Transport(1, false, $pool, new NullLogger());
        $result = $transport->performRequest('GET', '/');

        $thrown = null;
        try {
            $result->wait();
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $this->assertSame($failure, $thrown);
    }

    public function failures(): array
    {
        return [
            'exception' => [new \RuntimeException('Connection refused'), 1],
            'error' => [new \TypeError('bug in the response callback'), 1],
            'client error' => [new \RuntimeException('Not Found', 404), 0],
        ];
    }
}
