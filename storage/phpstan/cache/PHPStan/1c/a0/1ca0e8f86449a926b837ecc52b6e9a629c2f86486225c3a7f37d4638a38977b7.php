<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/WorkCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\WorkCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-ee93b9c5bac8c699818ddf7658d7037501422978f5505a178a5757581d35ad6d-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/WorkCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\WorkCommand',
    'shortName' => 'WorkCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 54,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Queue\\Console\\WorkCommand',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:work
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the worker}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--daemon : Run the worker in daemon mode (Deprecated)}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--queue= : The names of the queues to work}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--supervisor= : The name of the supervisor the worker belongs to}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--json : Output the queue worker information as JSON}\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 32,
            'startTokenPos' => 34,
            'startFilePos' => 251,
            'endTokenPos' => 34,
            'endFilePos' => 1861,
          ),
        ),
        'docComment' => '/**
 * The console command name.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 84,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hidden' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'name' => 'hidden',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 45,
            'startFilePos' => 2011,
            'endTokenPos' => 45,
            'endFilePos' => 2014,
          ),
        ),
        'docComment' => '/**
 * Indicates whether the command should be shown in the Artisan command list.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the console command.
 *
 * @return void
 */',
        'startLine' => 46,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\WorkCommand',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));