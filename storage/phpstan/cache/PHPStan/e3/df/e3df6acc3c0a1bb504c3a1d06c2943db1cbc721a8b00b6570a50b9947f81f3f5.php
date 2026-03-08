<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Searchable.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Scout\Searchable
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-f373fc13316ef5d9a4b2c2b13a380abd2e369a71730172c1f150d2446540fc33-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Scout\\Searchable',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/scout/src/Searchable.php',
      ),
    ),
    'namespace' => 'Laravel\\Scout',
    'name' => 'Laravel\\Scout\\Searchable',
    'shortName' => 'Searchable',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 511,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
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
      'scoutMetadata' => 
      array (
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'name' => 'scoutMetadata',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 44,
            'startFilePos' => 341,
            'endTokenPos' => 45,
            'endFilePos' => 342,
          ),
        ),
        'docComment' => '/**
 * Additional metadata attributes managed by Scout.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 34,
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
      'bootSearchable' => 
      array (
        'name' => 'bootSearchable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Boot the trait.
 *
 * @return void
 */',
        'startLine' => 23,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'registerSearchableMacros' => 
      array (
        'name' => 'registerSearchableMacros',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register the searchable macros.
 *
 * @return void
 */',
        'startLine' => 45,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'queueMakeSearchable' => 
      array (
        'name' => 'queueMakeSearchable',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Dispatch the job to make the given models searchable.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Collection  $models
 * @return void
 */',
        'startLine' => 72,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'syncMakeSearchable' => 
      array (
        'name' => 'syncMakeSearchable',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 40,
            'endColumn' => 46,
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
 * Synchronously make the given models searchable.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Collection  $models
 * @return void
 */',
        'startLine' => 93,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'queueRemoveFromSearch' => 
      array (
        'name' => 'queueRemoveFromSearch',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 43,
            'endColumn' => 49,
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
 * Dispatch the job to make the given models unsearchable.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Collection  $models
 * @return void
 */',
        'startLine' => 108,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'syncRemoveFromSearch' => 
      array (
        'name' => 'syncRemoveFromSearch',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 129,
            'endLine' => 129,
            'startColumn' => 42,
            'endColumn' => 48,
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
 * Synchronously make the given models unsearchable.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Collection  $models
 * @return void
 */',
        'startLine' => 129,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'shouldBeSearchable' => 
      array (
        'name' => 'shouldBeSearchable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the model should be searchable.
 *
 * @return bool
 */',
        'startLine' => 143,
        'endLine' => 146,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'searchIndexShouldBeUpdated' => 
      array (
        'name' => 'searchIndexShouldBeUpdated',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * When updating a model, this method determines if we should update the search index.
 *
 * @return bool
 */',
        'startLine' => 153,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'search' => 
      array (
        'name' => 'search',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 654,
                'startFilePos' => 4143,
                'endTokenPos' => 654,
                'endFilePos' => 4144,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 661,
                'startFilePos' => 4159,
                'endTokenPos' => 661,
                'endFilePos' => 4162,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 48,
            'endColumn' => 63,
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
 * Perform a search against the model\'s indexed data.
 *
 * @param  string  $query
 * @param  \\Closure  $callback
 * @return \\Laravel\\Scout\\Builder<static>
 */',
        'startLine' => 165,
        'endLine' => 173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'makeAllSearchable' => 
      array (
        'name' => 'makeAllSearchable',
        'parameters' => 
        array (
          'chunk' => 
          array (
            'name' => 'chunk',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 181,
                'endLine' => 181,
                'startTokenPos' => 747,
                'startFilePos' => 4622,
                'endTokenPos' => 747,
                'endFilePos' => 4625,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 181,
            'endLine' => 181,
            'startColumn' => 46,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Make all instances of the model searchable.
 *
 * @param  int  $chunk
 * @return void
 */',
        'startLine' => 181,
        'endLine' => 184,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'makeAllSearchableQuery' => 
      array (
        'name' => 'makeAllSearchableQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a query builder for making all instances of the model searchable.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder
 */',
        'startLine' => 191,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'makeSearchableUsing' => 
      array (
        'name' => 'makeSearchableUsing',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Collection',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 41,
            'endColumn' => 62,
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
 * Modify the collection of models being made searchable.
 *
 * @param  \\Illuminate\\Support\\Collection  $models
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 215,
        'endLine' => 218,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'makeAllSearchableUsing' => 
      array (
        'name' => 'makeAllSearchableUsing',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 226,
            'endLine' => 226,
            'startColumn' => 47,
            'endColumn' => 68,
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
 * Modify the query used to retrieve models when making all of the models searchable.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder  $query
 * @return \\Illuminate\\Database\\Eloquent\\Builder
 */',
        'startLine' => 226,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'searchable' => 
      array (
        'name' => 'searchable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Make the given model instance searchable.
 *
 * @return void
 */',
        'startLine' => 236,
        'endLine' => 239,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'searchableSync' => 
      array (
        'name' => 'searchableSync',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Synchronously make the given model instance searchable.
 *
 * @return void
 */',
        'startLine' => 246,
        'endLine' => 249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'removeAllFromSearch' => 
      array (
        'name' => 'removeAllFromSearch',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove all instances of the model from the search index.
 *
 * @return void
 */',
        'startLine' => 256,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'unsearchable' => 
      array (
        'name' => 'unsearchable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the given model instance from the search index.
 *
 * @return void
 */',
        'startLine' => 268,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'unsearchableSync' => 
      array (
        'name' => 'unsearchableSync',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Synchronously remove the given model instance from the search index.
 *
 * @return void
 */',
        'startLine' => 278,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'wasSearchableBeforeUpdate' => 
      array (
        'name' => 'wasSearchableBeforeUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the model existed in the search index prior to an update.
 *
 * @return bool
 */',
        'startLine' => 288,
        'endLine' => 291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'wasSearchableBeforeDelete' => 
      array (
        'name' => 'wasSearchableBeforeDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the model existed in the search index prior to deletion.
 *
 * @return bool
 */',
        'startLine' => 298,
        'endLine' => 301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'getScoutModelsByIds' => 
      array (
        'name' => 'getScoutModelsByIds',
        'parameters' => 
        array (
          'builder' => 
          array (
            'name' => 'builder',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Scout\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ids' => 
          array (
            'name' => 'ids',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 59,
            'endColumn' => 68,
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
 * Get the requested models from an array of object IDs.
 *
 * @param  \\Laravel\\Scout\\Builder  $builder
 * @param  array  $ids
 * @return mixed
 */',
        'startLine' => 310,
        'endLine' => 313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'queryScoutModelsByIds' => 
      array (
        'name' => 'queryScoutModelsByIds',
        'parameters' => 
        array (
          'builder' => 
          array (
            'name' => 'builder',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Scout\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 322,
            'endLine' => 322,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ids' => 
          array (
            'name' => 'ids',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 322,
            'endLine' => 322,
            'startColumn' => 61,
            'endColumn' => 70,
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
 * Get a query builder for retrieving the requested models from an array of object IDs.
 *
 * @param  \\Laravel\\Scout\\Builder  $builder
 * @param  array  $ids
 * @return mixed
 */',
        'startLine' => 322,
        'endLine' => 339,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'enableSearchSyncing' => 
      array (
        'name' => 'enableSearchSyncing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable search syncing for this model.
 *
 * @return void
 */',
        'startLine' => 346,
        'endLine' => 349,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'disableSearchSyncing' => 
      array (
        'name' => 'disableSearchSyncing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disable search syncing for this model.
 *
 * @return void
 */',
        'startLine' => 356,
        'endLine' => 359,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'withoutSyncingToSearch' => 
      array (
        'name' => 'withoutSyncingToSearch',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 367,
            'endLine' => 367,
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
 * Temporarily disable search syncing for the given callback.
 *
 * @param  callable  $callback
 * @return mixed
 */',
        'startLine' => 367,
        'endLine' => 376,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'searchableAs' => 
      array (
        'name' => 'searchableAs',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the index name for the model when searching.
 *
 * @return string
 */',
        'startLine' => 383,
        'endLine' => 386,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'indexableAs' => 
      array (
        'name' => 'indexableAs',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the index name for the model when indexing.
 *
 * @return string
 */',
        'startLine' => 393,
        'endLine' => 396,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'toSearchableArray' => 
      array (
        'name' => 'toSearchableArray',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the indexable data array for the model.
 *
 * @return array
 */',
        'startLine' => 403,
        'endLine' => 406,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'searchableUsing' => 
      array (
        'name' => 'searchableUsing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the Scout engine for the model.
 *
 * @return mixed
 */',
        'startLine' => 413,
        'endLine' => 416,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'syncWithSearchUsing' => 
      array (
        'name' => 'syncWithSearchUsing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queue connection that should be used when syncing.
 *
 * @return string
 */',
        'startLine' => 423,
        'endLine' => 426,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'syncWithSearchUsingQueue' => 
      array (
        'name' => 'syncWithSearchUsingQueue',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queue that should be used with syncing.
 *
 * @return string
 */',
        'startLine' => 433,
        'endLine' => 436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'pushSoftDeleteMetadata' => 
      array (
        'name' => 'pushSoftDeleteMetadata',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sync the soft deleted status for this model into the metadata.
 *
 * @return $this
 */',
        'startLine' => 443,
        'endLine' => 446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'scoutMetadata' => 
      array (
        'name' => 'scoutMetadata',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all Scout related metadata.
 *
 * @return array
 */',
        'startLine' => 453,
        'endLine' => 456,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'withScoutMetadata' => 
      array (
        'name' => 'withScoutMetadata',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 465,
            'endLine' => 465,
            'startColumn' => 39,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 465,
            'endLine' => 465,
            'startColumn' => 45,
            'endColumn' => 50,
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
 * Set a Scout related metadata.
 *
 * @param  string  $key
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 465,
        'endLine' => 470,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'getScoutKey' => 
      array (
        'name' => 'getScoutKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value used to index the model.
 *
 * @return mixed
 */',
        'startLine' => 477,
        'endLine' => 480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'getScoutKeyType' => 
      array (
        'name' => 'getScoutKeyType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the auto-incrementing key type for querying models.
 *
 * @return string
 */',
        'startLine' => 487,
        'endLine' => 490,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'getScoutKeyName' => 
      array (
        'name' => 'getScoutKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the key name used to index the model.
 *
 * @return mixed
 */',
        'startLine' => 497,
        'endLine' => 500,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
        'aliasName' => NULL,
      ),
      'usesSoftDelete' => 
      array (
        'name' => 'usesSoftDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the current class should use soft deletes with searching.
 *
 * @return bool
 */',
        'startLine' => 507,
        'endLine' => 510,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Laravel\\Scout',
        'declaringClassName' => 'Laravel\\Scout\\Searchable',
        'implementingClassName' => 'Laravel\\Scout\\Searchable',
        'currentClassName' => 'Laravel\\Scout\\Searchable',
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