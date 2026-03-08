<?php

declare(strict_types=1);

namespace App\Exceptions\Runtime;

use Exception;

class ConcurrentSessionLimitExceededException extends Exception {}
