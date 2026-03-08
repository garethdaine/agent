<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Jobs/Org/OrgDispatchDueRitualsJob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Jobs\Org\OrgDispatchDueRitualsJob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-864bf3b6ae53f53c56a7aca56721f686971278c6ba27607366c1e1b6ce0a43c8',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'filename' => '/Users/garethdaine/Code/agent/app/Jobs/Org/OrgDispatchDueRitualsJob.php',
      ),
    ),
    'namespace' => 'App\\Jobs\\Org',
    'name' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
    'shortName' => 'OrgDispatchDueRitualsJob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 70,
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
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
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
        'startLine' => 21,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'aliasName' => NULL,
      ),
      'getDueTemplates' => 
      array (
        'name' => 'getDueTemplates',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Collection',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all ritual templates that are due to run now.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Collection<int, OrgRitualTemplate>
 */',
        'startLine' => 40,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'aliasName' => NULL,
      ),
      'isDue' => 
      array (
        'name' => 'isDue',
        'parameters' => 
        array (
          'template' => 
          array (
            'name' => 'template',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualTemplate',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 30,
            'endColumn' => 56,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if a ritual template is due to run based on its cron expression.
 */',
        'startLine' => 54,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Jobs\\Org',
        'declaringClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'implementingClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
        'currentClassName' => 'App\\Jobs\\Org\\OrgDispatchDueRitualsJob',
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