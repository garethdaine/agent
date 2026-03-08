<?php declare(strict_types = 1);

// ftm-/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      'caa56b38e34742c7cce14ae8e4dfc4fd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'd72b2035aba9f29157c377759e84c901' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ae0e439c5dd67e6947ef201cd567cd01' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '19db00d38e09bfc83764fd8247e45742' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatchIf',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '23be5e5c3766c4e45c352d384746afda' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatchUnless',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2f70114b0c9d35116abce9c26feba398' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatchSync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '6176b64746d832b2a04c5b5cf18b3532' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatchAfterResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2984b1084a44ae1714f635b4ec3c5a16' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'withChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd3f4d624fd8e04182ce63a67ff71a507' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'newPendingDispatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ae9ba3eff89b9e56b5e255312c2a4efb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '22a9b8cc4ef40949b6b172a1cffa1822' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'a7e86674b9203cbfdf0ad5dc1c73cb20' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'secondsUntil',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '899d1a3dd19844e21aa07a11b1105b1b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'availableAt',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '99b086fc7bfa6c7d21c1e6fa4489a97d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'parseDateInterval',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'b20df4185b1c02feca2ae2e222d5c40a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'currentTime',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '315d5f2432847b0a8c2acf9a23486ae1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'runTimeForHumans',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'e8396e76b8931c8531e1fe210af390f9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'attempts',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2992a238e1be3d120a0e26db2da02c80' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'delete',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'e63b3aa448278ca1a6a15b17344f8bdc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'fail',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd8dbf782e397231a6e37e8912648fbed' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'release',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '946bf2e6c3aa40805096b2d5ac730761' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'withFakeQueueInteractions',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '93c389cdca49d1b02242e8df42db6aea' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertDeleted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ee91d5301a7c14c970c95edd45776168' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertNotDeleted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '0ce118aac5771afb53d61e68907079db' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertFailed',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '37bd8812c36cffb53cfa63e0bae7126b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertFailedWith',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'f4787ef19fc81d0f2089e42f35df9901' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertNotFailed',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '231f8b49f0829385b51737d0a67ff141' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertReleased',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'c89e0e0b25fdbbec0459094ff14999f5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertNotReleased',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ee901664194a00a9122e8cf15c704f2e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'ensureQueueInteractionsHaveBeenFaked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'eb36eedf00b29b8c55f80eba65cfc47e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'setJob',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '716170f3cb377f45b93c9ed9aeb03f32' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '5e022e44ed892654ebe186e1434c8a3f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'onConnection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd58c5382668394abbbd953d0009c5289' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'onQueue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'c0a6336daa5cd9634eeb93fc54818d9b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'onGroup',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '18e5feb3d0e21701f44e6f8b63d4961d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'withDeduplicator',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '8372962f4496c58a2d74f69f4e1c0fdd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'allOnConnection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '7f27a81f81b6dd6e653ae1fa6cf9da30' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'allOnQueue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'b7843be9f097ba53de5dd5e7a57502d3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'delay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '301d72e08f6a12da2fc74dc16d3f9e21' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'withoutDelay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '416e7e731538ac68ce4ca48c34bec512' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'afterCommit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ba9d618b677ef262d4c4d208b1dd39ff' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'beforeCommit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '7b803cdde6fa5dc351090c34a9dd3752' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'through',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '284f579fb53943e92fbc4712b3cb3c66' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'chain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'e69acf7daed3996351f857fa200e18b0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'prependToChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '3c61f6568bebcb499b9b7fcb80de502e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'appendToChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '0bf1e2f6feb13b407cfc332f48d16cdd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'serializeJob',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '2d30ada9de096a988f2ab0b7a8b55dce' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'dispatchNextJobInChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '1211af0377fe668f94559bbf2f440e24' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'invokeChainCatchCallbacks',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'a3b6b54997dc7e1795589abc5967f7de' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertHasChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'a84aedaaccadf80ebf7a36caa4dc2eb6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'assertDoesntHaveChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'eca9be9e3dc37e3433ab8a52e64f23a3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '9cf7ad92d2c7362070142bd274c3e600' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '98bcf5cfa362cca5da84b32d8a1b7f60' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'getSerializedPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '7cfe9f1a0974541fa3e94f2b1169de94' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'getRestoredPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '94edcf25ed12f3db5cc2366bae2aac61' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'restoreCollection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '32c6117b4e79fb328a0e2ae279c20eb9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'restoreModel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'e01a3164d339786aaf769f497bc01b10' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'getQueryForModelRestoration',
         'templatePhpDocNodes' => 
        array (
          'TModel' => 
          array (
            0 => '@template',
            1 => 
            \PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode::__set_state(array(
               'name' => 'TModel',
               'bound' => 
              \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode::__set_state(array(
                 'name' => '\\Illuminate\\Database\\Eloquent\\Model',
                 'attributes' => 
                array (
                  'startLine' => 4,
                  'endLine' => 4,
                ),
              )),
               'default' => NULL,
               'lowerBound' => NULL,
               'description' => '',
               'attributes' => 
              array (
                'startLine' => 4,
                'endLine' => 4,
              ),
            )),
          ),
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '0ac5aafccaffa32a7d0ed30916be0fd6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => '__serialize',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '1092bfdaee7fcb706ac5cbd17da30e86' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => '__unserialize',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '7f781dbdb342bf4c14b49fea6c8bd2c1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'getPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php',
          1 => 'App\\Jobs\\Messenger\\ProcessChatIntent',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'bc04b50090533541f02a35e60df26bbc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '1fe5c874fb25626e541696f4f3394f2c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'handle',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3d4abd99079a9956ce4670ad8f6fc8d7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'executeAction',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8dc4fb91ab959e9dbb84dc9b40aa5750' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'executeStreamingAction',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'fbdfa2494c0c10317a857f94049529f4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'executeSyncAction',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '048225b169fd8b4e250e8dff7b833855' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'sendPlaceholder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '1f6bbe035afaa148128cd2c40bcbccdf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'sendOrEditResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '4dea7f07cb47797c3dfbb9839e9fb059' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'sendResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '579bed01b47822887df8c3fe382dad4f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'handleButtonCallback',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '70a56f793e4543ddc86401ad8b489341' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\Messenger',
         'uses' => 
        array (
          'connectoradapterinterface' => 'App\\Contracts\\Messenger\\ConnectorAdapterInterface',
          'outboundpayload' => 'App\\DTOs\\Messenger\\OutboundPayload',
          'providerresponse' => 'App\\DTOs\\Messenger\\ProviderResponse',
          'chatactiontype' => 'App\\Enums\\Messenger\\ChatActionType',
          'runtimetoolcallstatus' => 'App\\Enums\\Runtime\\RuntimeToolCallStatus',
          'processruntimeturnjob' => 'App\\Jobs\\Runtime\\ProcessRuntimeTurnJob',
          'chataction' => 'App\\Models\\ChatAction',
          'chatmessage' => 'App\\Models\\ChatMessage',
          'chatsession' => 'App\\Models\\ChatSession',
          'connectoraccount' => 'App\\Models\\ConnectorAccount',
          'runtimetoolcall' => 'App\\Models\\Runtime\\RuntimeToolCall',
          'user' => 'App\\Models\\User',
          'agentrouter' => 'App\\Services\\Messenger\\AgentRouter',
          'chatactionexecutor' => 'App\\Services\\Messenger\\ChatActionExecutor',
          'chatintentparser' => 'App\\Services\\Messenger\\ChatIntentParser',
          'chatresponseformatter' => 'App\\Services\\Messenger\\ChatResponseFormatter',
          'commandrouter' => 'App\\Services\\Messenger\\CommandRouter',
          'confirmationmanager' => 'App\\Services\\Messenger\\ConfirmationManager',
          'streamingresponsewriter' => 'App\\Services\\Messenger\\StreamingResponseWriter',
          'approvalgate' => 'App\\Services\\Runtime\\ApprovalGate',
          'runtimesessionmanager' => 'App\\Services\\Runtime\\RuntimeSessionManager',
          'connectormanager' => 'App\\Support\\Messenger\\ConnectorManager',
          'queueable' => 'Illuminate\\Bus\\Queueable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
        ),
         'className' => 'App\\Jobs\\Messenger\\ProcessChatIntent',
         'functionName' => 'tags',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      '/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php' => 'bd21c01a1188e276fc0a12cee6af233bd33dbcc17eecb73818e7781f927cfbc7',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/Dispatchable.php' => '551294291775e57fbd590f0ed288a91cca683d42fac08e60c87e39b73617d47b',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php' => '8d300c3adb967aa56c0827ba587e456e32e40fbb1c0d9f649f6bf7c0d876e937',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Support/InteractsWithTime.php' => 'ee4ef3a2e714fa539b223287a3a62b618b1d3a9e44f2e1f92981f2c3e2773ad5',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Bus/Queueable.php' => '7df8b51aab8bd3196229be1a8e398c2c2ec636ae1767ce499a64bfdbf5675c47',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesModels.php' => '29ff50de875925956c56b217ef9b78643cef0e12e23885fdf37e0ed9b697e51d',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php' => 'd4cb97259a134d2089c54c969c2176704f0df2fa2483f149b61029c5c993f82d',
    ),
  ),
));