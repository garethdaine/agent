<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class RedactSensitiveTap
{
    public function __invoke(Logger $logger): void
    {
        if (! config('logging.redact_sensitive', false)) {
            return;
        }

        $logger->pushProcessor(new RedactSensitiveProcessor);
    }
}
