<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Console/InstallCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Jetstream\Console\InstallCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fd7180c4a68436a822f4ae924a75ec176cadf316e4a75a7a7c0262b32dc527af-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Console/InstallCommand.php',
      ),
    ),
    'namespace' => 'Laravel\\Jetstream\\Console',
    'name' => 'Laravel\\Jetstream\\Console\\InstallCommand',
    'shortName' => 'InstallCommand',
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
            'code' => '\'jetstream:install\'',
            'attributes' => 
            array (
              'startLine' => 24,
              'endLine' => 24,
              'startTokenPos' => 104,
              'startFilePos' => 726,
              'endTokenPos' => 104,
              'endFilePos' => 744,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 24,
    'endLine' => 901,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Console\\PromptsForMissingInput',
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
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'jetstream:install {stack : The development stack that should be installed (inertia,livewire)}
                                              {--dark : Indicate that dark mode support should be installed}
                                              {--teams : Indicates if team support should be installed}
                                              {--api : Indicates if API support should be installed}
                                              {--verification : Indicates if email verification support should be installed}
                                              {--pest : Indicates if Pest should be installed}
                                              {--ssr : Indicates if Inertia SSR support should be installed}
                                              {--composer=global : Absolute path to the Composer binary which should be used to install packages}\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 39,
            'startTokenPos' => 130,
            'startFilePos' => 944,
            'endTokenPos' => 130,
            'endFilePos' => 1827,
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
        'startLine' => 32,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 147,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Install the Jetstream components and resources\'',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 141,
            'startFilePos' => 1942,
            'endTokenPos' => 141,
            'endFilePos' => 1989,
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
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 78,
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
 * @return int|null
 */',
        'startLine' => 53,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'configureSession' => 
      array (
        'name' => 'configureSession',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Configure the session driver for Jetstream.
 *
 * @return void
 */',
        'startLine' => 139,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installLivewireStack' => 
      array (
        'name' => 'installLivewireStack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the Livewire stack into the application.
 *
 * @return bool
 */',
        'startLine' => 150,
        'endLine' => 288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installLivewireTeamStack' => 
      array (
        'name' => 'installLivewireTeamStack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the Livewire team stack into the application.
 *
 * @return void
 */',
        'startLine' => 295,
        'endLine' => 315,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'livewireRouteDefinition' => 
      array (
        'name' => 'livewireRouteDefinition',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the route definition(s) that should be installed for Livewire.
 *
 * @return string
 */',
        'startLine' => 322,
        'endLine' => 337,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installInertiaStack' => 
      array (
        'name' => 'installInertiaStack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the Inertia stack into the application.
 *
 * @return bool
 */',
        'startLine' => 344,
        'endLine' => 494,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installInertiaTeamStack' => 
      array (
        'name' => 'installInertiaTeamStack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the Inertia team stack into the application.
 *
 * @return void
 */',
        'startLine' => 501,
        'endLine' => 521,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'ensureApplicationIsTeamCompatible' => 
      array (
        'name' => 'ensureApplicationIsTeamCompatible',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the installed user model is ready for team usage.
 *
 * @return void
 */',
        'startLine' => 528,
        'endLine' => 570,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installInertiaSsrStack' => 
      array (
        'name' => 'installInertiaSsrStack',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the Inertia SSR stack into the application.
 *
 * @return void
 */',
        'startLine' => 577,
        'endLine' => 593,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'installMiddleware' => 
      array (
        'name' => 'installMiddleware',
        'parameters' => 
        array (
          'names' => 
          array (
            'name' => 'names',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 603,
            'endLine' => 603,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'group' => 
          array (
            'name' => 'group',
            'default' => 
            array (
              'code' => '\'web\'',
              'attributes' => 
              array (
                'startLine' => 603,
                'endLine' => 603,
                'startTokenPos' => 4089,
                'startFilePos' => 28499,
                'endTokenPos' => 4089,
                'endFilePos' => 28503,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 603,
            'endLine' => 603,
            'startColumn' => 50,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'modifier' => 
          array (
            'name' => 'modifier',
            'default' => 
            array (
              'code' => '\'append\'',
              'attributes' => 
              array (
                'startLine' => 603,
                'endLine' => 603,
                'startTokenPos' => 4096,
                'startFilePos' => 28518,
                'endTokenPos' => 4096,
                'endFilePos' => 28525,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 603,
            'endLine' => 603,
            'startColumn' => 66,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Install the given middleware names into the application.
 *
 * @param  array|string  $name
 * @param  string  $group
 * @param  string  $modifier
 * @return void
 */',
        'startLine' => 603,
        'endLine' => 630,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'getTestStubsPath' => 
      array (
        'name' => 'getTestStubsPath',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the path to the correct test stubs.
 *
 * @return string
 */',
        'startLine' => 637,
        'endLine' => 642,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'hasComposerPackage' => 
      array (
        'name' => 'hasComposerPackage',
        'parameters' => 
        array (
          'package' => 
          array (
            'name' => 'package',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 43,
            'endColumn' => 50,
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
 * Determine if the given Composer package is installed.
 *
 * @param  string  $package
 * @return bool
 */',
        'startLine' => 650,
        'endLine' => 656,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'requireComposerPackages' => 
      array (
        'name' => 'requireComposerPackages',
        'parameters' => 
        array (
          'packages' => 
          array (
            'name' => 'packages',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 664,
            'endLine' => 664,
            'startColumn' => 48,
            'endColumn' => 56,
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
 * Installs the given Composer Packages into the application.
 *
 * @param  mixed  $packages
 * @return bool
 */',
        'startLine' => 664,
        'endLine' => 682,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'removeComposerDevPackages' => 
      array (
        'name' => 'removeComposerDevPackages',
        'parameters' => 
        array (
          'packages' => 
          array (
            'name' => 'packages',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 690,
            'endLine' => 690,
            'startColumn' => 50,
            'endColumn' => 58,
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
 * Removes the given Composer Packages as "dev" dependencies.
 *
 * @param  mixed  $packages
 * @return bool
 */',
        'startLine' => 690,
        'endLine' => 708,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'requireComposerDevPackages' => 
      array (
        'name' => 'requireComposerDevPackages',
        'parameters' => 
        array (
          'packages' => 
          array (
            'name' => 'packages',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 716,
            'endLine' => 716,
            'startColumn' => 51,
            'endColumn' => 59,
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
 * Install the given Composer Packages as "dev" dependencies.
 *
 * @param  mixed  $packages
 * @return bool
 */',
        'startLine' => 716,
        'endLine' => 734,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'updateNodePackages' => 
      array (
        'name' => 'updateNodePackages',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 743,
            'endLine' => 743,
            'startColumn' => 50,
            'endColumn' => 67,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'dev' => 
          array (
            'name' => 'dev',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 743,
                'endLine' => 743,
                'startTokenPos' => 4931,
                'startFilePos' => 32943,
                'endTokenPos' => 4931,
                'endFilePos' => 32946,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 743,
            'endLine' => 743,
            'startColumn' => 70,
            'endColumn' => 80,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Update the "package.json" file.
 *
 * @param  callable  $callback
 * @param  bool  $dev
 * @return void
 */',
        'startLine' => 743,
        'endLine' => 764,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'runDatabaseMigrations' => 
      array (
        'name' => 'runDatabaseMigrations',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the database migrations.
 *
 * @return void
 */',
        'startLine' => 771,
        'endLine' => 780,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'replaceInFile' => 
      array (
        'name' => 'replaceInFile',
        'parameters' => 
        array (
          'search' => 
          array (
            'name' => 'search',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 790,
            'endLine' => 790,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'replace' => 
          array (
            'name' => 'replace',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 790,
            'endLine' => 790,
            'startColumn' => 47,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 790,
            'endLine' => 790,
            'startColumn' => 57,
            'endColumn' => 61,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Replace a given string within a given file.
 *
 * @param  string  $replace
 * @param  string|array  $search
 * @param  string  $path
 * @return void
 */',
        'startLine' => 790,
        'endLine' => 793,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'removeDarkClasses' => 
      array (
        'name' => 'removeDarkClasses',
        'parameters' => 
        array (
          'finder' => 
          array (
            'name' => 'finder',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Finder\\Finder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 801,
            'endLine' => 801,
            'startColumn' => 42,
            'endColumn' => 55,
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
 * Remove Tailwind dark classes from the given files.
 *
 * @param  \\Symfony\\Component\\Finder\\Finder  $finder
 * @return void
 */',
        'startLine' => 801,
        'endLine' => 806,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'phpBinary' => 
      array (
        'name' => 'phpBinary',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the path to the appropriate PHP binary.
 *
 * @return string
 */',
        'startLine' => 813,
        'endLine' => 820,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'runCommands' => 
      array (
        'name' => 'runCommands',
        'parameters' => 
        array (
          'commands' => 
          array (
            'name' => 'commands',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 828,
            'endLine' => 828,
            'startColumn' => 36,
            'endColumn' => 44,
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
 * Run the given commands.
 *
 * @param  array  $commands
 * @return void
 */',
        'startLine' => 828,
        'endLine' => 843,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'promptForMissingArgumentsUsing' => 
      array (
        'name' => 'promptForMissingArgumentsUsing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prompt for missing input arguments using the returned questions.
 *
 * @return array
 */',
        'startLine' => 850,
        'endLine' => 861,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'afterPromptingForMissingArguments' => 
      array (
        'name' => 'afterPromptingForMissingArguments',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Console\\Input\\InputInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 870,
            'endLine' => 870,
            'startColumn' => 58,
            'endColumn' => 78,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'output' => 
          array (
            'name' => 'output',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Console\\Output\\OutputInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 870,
            'endLine' => 870,
            'startColumn' => 81,
            'endColumn' => 103,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Interact further with the user if they were prompted for missing arguments.
 *
 * @param  \\Symfony\\Component\\Console\\Input\\InputInterface  $input
 * @param  \\Symfony\\Component\\Console\\Output\\OutputInterface  $output
 * @return void
 */',
        'startLine' => 870,
        'endLine' => 890,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'aliasName' => NULL,
      ),
      'isUsingPest' => 
      array (
        'name' => 'isUsingPest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine whether the project is already using Pest.
 *
 * @return bool
 */',
        'startLine' => 897,
        'endLine' => 900,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Jetstream\\Console',
        'declaringClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'implementingClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
        'currentClassName' => 'Laravel\\Jetstream\\Console\\InstallCommand',
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