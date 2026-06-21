<?php

namespace App\Logging;

class ApplyProcessors
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new \App\Logging\Processors\PiiScrubberProcessor());
        }
    }
}
