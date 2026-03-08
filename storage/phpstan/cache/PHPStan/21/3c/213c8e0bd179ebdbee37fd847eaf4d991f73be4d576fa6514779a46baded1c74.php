<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../stripe/stripe-php/lib/V2/Billing/MeterEvent.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Stripe\V2\Billing\MeterEvent
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4158bd45943be3215e5bbaafc9791f7d2251a2c59033f8eb84a30af24d7672bb-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Stripe\\V2\\Billing\\MeterEvent',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../stripe/stripe-php/lib/V2/Billing/MeterEvent.php',
      ),
    ),
    'namespace' => 'Stripe\\V2\\Billing',
    'name' => 'Stripe\\V2\\Billing\\MeterEvent',
    'shortName' => 'MeterEvent',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Fix me empty_doc_string.
 *
 * @property string $object String representing the object\'s type. Objects of the same type share the same value of the object field.
 * @property int $created The creation time of this meter event.
 * @property string $event_name The name of the meter event. Corresponds with the <code>event_name</code> field on a meter.
 * @property string $identifier A unique identifier for the event. If not provided, one will be generated. We recommend using a globally unique identifier for this. We’ll enforce uniqueness within a rolling 24 hour period.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property \\Stripe\\StripeObject $payload The payload of the event. This must contain the fields corresponding to a meter’s <code>customer_mapping.event_payload_key</code> (default is <code>stripe_customer_id</code>) and <code>value_settings.event_payload_key</code> (default is <code>value</code>). Read more about the payload.
 * @property int $timestamp The time of the event. Must be within the past 35 calendar days or up to 5 minutes in the future. Defaults to current timestamp if not specified.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 21,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Stripe\\ApiResource',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OBJECT_NAME' => 
      array (
        'declaringClassName' => 'Stripe\\V2\\Billing\\MeterEvent',
        'implementingClassName' => 'Stripe\\V2\\Billing\\MeterEvent',
        'name' => 'OBJECT_NAME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'v2.billing.meter_event\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 27,
            'startFilePos' => 1403,
            'endTokenPos' => 27,
            'endFilePos' => 1426,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 49,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
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