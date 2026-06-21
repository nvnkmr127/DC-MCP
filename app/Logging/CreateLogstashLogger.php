<?php

namespace App\Logging;

use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\SocketHandler;
use Monolog\Logger;

class CreateLogstashLogger
{
    /**
     * Create a custom Monolog instance targeting Logstash/ELK over TCP/UDP.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5000;
        $level = $config['level'] ?? Logger::DEBUG;

        // Establish socket connection to centralized log aggregator (ELK, Fluentd, etc.)
        $socketHandler = new SocketHandler("tcp://{$host}:{$port}", $level);
        
        // Format the log explicitly for Logstash parsing
        $socketHandler->setFormatter(new LogstashFormatter(env('APP_NAME', 'laravel')));

        return new Logger('logstash', [$socketHandler]);
    }
}
