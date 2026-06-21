<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SamplingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CreateSampledLogger
{
    /**
     * Create a custom Monolog instance with a SamplingHandler.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $factor = $config['factor'] ?? 10;
        $path = $config['path'] ?? storage_path('logs/sampled.log');
        $level = $config['level'] ?? Logger::DEBUG;

        // The underlying handler that actually writes the log (with 14 days retention)
        $streamHandler = new \Monolog\Handler\RotatingFileHandler($path, 14, $level);
        $streamHandler->setFormatter(new JsonFormatter());

        // The sampling handler that wraps the stream handler and drops events
        $samplingHandler = new SamplingHandler($streamHandler, $factor);

        return new Logger('sampled', [$samplingHandler]);
    }
}
