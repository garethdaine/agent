<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Features.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Jetstream\Features
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-3cf2c833d5f66dfa5eb018c655f70bc24809f462cdd54d998807034d22fd75dd-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Jetstream\\Features',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/jetstream/src/Features.php',
      ),
    ),
    'namespace' => 'Laravel\\Jetstream',
    'name' => 'Laravel\\Jetstream\\Features',
    'shortName' => 'Features',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 145,
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
    ),
    'immediateMethods' => 
    array (
      'enabled' => 
      array (
        'name' => 'enabled',
        'parameters' => 
        array (
          'feature' => 
          array (
            'name' => 'feature',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 13,
            'endLine' => 13,
            'startColumn' => 36,
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
 * Determine if the given feature is enabled.
 *
 * @param  string  $feature
 * @return bool
 */',
        'startLine' => 13,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'optionEnabled' => 
      array (
        'name' => 'optionEnabled',
        'parameters' => 
        array (
          'feature' => 
          array (
            'name' => 'feature',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 42,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'option' => 
          array (
            'name' => 'option',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 59,
            'endColumn' => 72,
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
 * Determine if the feature is enabled and has a given option enabled.
 *
 * @param  string  $feature
 * @param  string  $option
 * @return bool
 */',
        'startLine' => 25,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'managesProfilePhotos' => 
      array (
        'name' => 'managesProfilePhotos',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is allowing profile photo uploads.
 *
 * @return bool
 */',
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'hasApiFeatures' => 
      array (
        'name' => 'hasApiFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is using any API features.
 *
 * @return bool
 */',
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'hasTeamFeatures' => 
      array (
        'name' => 'hasTeamFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is using any team features.
 *
 * @return bool
 */',
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'sendsTeamInvitations' => 
      array (
        'name' => 'sendsTeamInvitations',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if invitations are sent to team members.
 *
 * @return bool
 */',
        'startLine' => 66,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'hasTermsAndPrivacyPolicyFeature' => 
      array (
        'name' => 'hasTermsAndPrivacyPolicyFeature',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application has terms of service / privacy policy confirmation enabled.
 *
 * @return bool
 */',
        'startLine' => 76,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'hasAccountDeletionFeatures' => 
      array (
        'name' => 'hasAccountDeletionFeatures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the application is using any account deletion features.
 *
 * @return bool
 */',
        'startLine' => 86,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'profilePhotos' => 
      array (
        'name' => 'profilePhotos',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the profile photo upload feature.
 *
 * @return string
 */',
        'startLine' => 96,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'api' => 
      array (
        'name' => 'api',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the API feature.
 *
 * @return string
 */',
        'startLine' => 106,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'teams' => 
      array (
        'name' => 'teams',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 117,
                'endLine' => 117,
                'startTokenPos' => 345,
                'startFilePos' => 2601,
                'endTokenPos' => 346,
                'endFilePos' => 2602,
              ),
            ),
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
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 34,
            'endColumn' => 52,
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
 * Enable the teams feature.
 *
 * @param  array  $options
 * @return string
 */',
        'startLine' => 117,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'termsAndPrivacyPolicy' => 
      array (
        'name' => 'termsAndPrivacyPolicy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the terms of service and privacy policy feature.
 *
 * @return string
 */',
        'startLine' => 131,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
        'aliasName' => NULL,
      ),
      'accountDeletion' => 
      array (
        'name' => 'accountDeletion',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable the account deletion feature.
 *
 * @return string
 */',
        'startLine' => 141,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Laravel\\Jetstream',
        'declaringClassName' => 'Laravel\\Jetstream\\Features',
        'implementingClassName' => 'Laravel\\Jetstream\\Features',
        'currentClassName' => 'Laravel\\Jetstream\\Features',
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