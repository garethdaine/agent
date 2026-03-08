<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/SupervisorCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\Console\SupervisorCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-90c9896b514c8083c5f6a6cda6700801bef6321bf2afd04ec9036619ae24e251-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/horizon/src/Console/SupervisorCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon\\Console',
    'name' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
    'shortName' => 'SupervisorCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'Symfony\\Component\\Console\\Attribute\\AsCommand',
        'isRepeated' => false,
        'arguments' => 
        array (
          'name' => 
          array (
            'code' => '\'horizon:supervisor\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 38,
              'startFilePos' => 238,
              'endTokenPos' => 38,
              'endFilePos' => 257,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 11,
    'endLine' => 159,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'horizon:supervisor
                            {name : The name of supervisor}
                            {connection : The name of the connection to work}
                            {--balance= : The balancing strategy the supervisor should apply}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping a child process}
                            {--max-time=0 : The maximum number of seconds a child process should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--max-processes=1 : The maximum number of total workers to start}
                            {--min-processes=1 : The minimum number of workers to assign per queue}
                            {--memory=128 : The memory limit in megabytes}
                            {--nice=0 : The process priority}
                            {--paused : Start the supervisor in a paused state}
                            {--queue= : The names of the queues to work}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--auto-scaling-strategy=time : If supervisor should scale by jobs or time to complete}
                            {--balance-cooldown=3 : The number of seconds to wait in between auto-scaling attempts}
                            {--balance-max-shift=1 : The maximum number of processes to increase or decrease per one scaling}
                            {--workers-name=default : The name that should be assigned to the workers}
                            {--parent-id=0 : The parent process ID}
                            {--rest=0 : Number of seconds to rest between jobs}\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 42,
            'startTokenPos' => 60,
            'startFilePos' => 426,
            'endTokenPos' => 60,
            'endFilePos' => 2587,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 81,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Start a new supervisor\'',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 71,
            'startFilePos' => 2702,
            'endTokenPos' => 71,
            'endFilePos' => 2725,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hidden' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'name' => 'hidden',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 56,
            'startTokenPos' => 82,
            'startFilePos' => 2875,
            'endTokenPos' => 82,
            'endFilePos' => 2878,
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
        'startLine' => 56,
        'endLine' => 56,
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
          'factory' => 
          array (
            'name' => 'factory',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Horizon\\SupervisorFactory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 28,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the console command.
 *
 * @param  \\Laravel\\Horizon\\SupervisorFactory  $factory
 * @return int|null
 */',
        'startLine' => 64,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'aliasName' => NULL,
      ),
      'start' => 
      array (
        'name' => 'start',
        'parameters' => 
        array (
          'supervisor' => 
          array (
            'name' => 'supervisor',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 30,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Start the given supervisor.
 *
 * @param  \\Laravel\\Horizon\\Supervisor  $supervisor
 * @return void
 */',
        'startLine' => 87,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'aliasName' => NULL,
      ),
      'supervisorOptions' => 
      array (
        'name' => 'supervisorOptions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the supervisor options.
 *
 * @return \\Laravel\\Horizon\\SupervisorOptions
 */',
        'startLine' => 112,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'aliasName' => NULL,
      ),
      'getQueue' => 
      array (
        'name' => 'getQueue',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 33,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queue name for the worker.
 *
 * @param  string  $connection
 * @return string
 */',
        'startLine' => 153,
        'endLine' => 158,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon\\Console',
        'declaringClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'implementingClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
        'currentClassName' => 'Laravel\\Horizon\\Console\\SupervisorCommand',
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