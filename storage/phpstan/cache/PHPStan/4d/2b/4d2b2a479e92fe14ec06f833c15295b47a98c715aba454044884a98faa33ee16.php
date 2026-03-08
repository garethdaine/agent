<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Jobs/Org/OrgEscalationTimeoutJob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Jobs\Org\OrgEscalationTimeoutJob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-0309dd603369eed1941309d2ace9856735b7e0ce08d6897ae188532ce0fd2f2a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'filename' => '/Users/garethdaine/Code/agent/app/Jobs/Org/OrgEscalationTimeoutJob.php',
      ),
    ),
    'namespace' => 'App\\Jobs\\Org',
    'name' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
    'shortName' => 'OrgEscalationTimeoutJob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Job that processes timed-out escalations.
 *
 * This job runs on the scheduler (every minute or as configured) and marks
 * any pending escalations that have exceeded their timeout as timed_out.
 * Timeout results in auto-fail, not auto-approve.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 68,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Foundation\\Queue\\Queueable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'escalationService' => 
          array (
            'name' => 'escalationService',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Org\\OrgEscalationService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 28,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'aliasName' => NULL,
      ),
      'handleTimedOutEscalation' => 
      array (
        'name' => 'handleTimedOutEscalation',
        'parameters' => 
        array (
          'escalation' => 
          array (
            'name' => 'escalation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgEscalation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 49,
            'endColumn' => 73,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle a single timed-out escalation.
 */',
        'startLine' => 53,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgEscalationTimeoutJob',
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