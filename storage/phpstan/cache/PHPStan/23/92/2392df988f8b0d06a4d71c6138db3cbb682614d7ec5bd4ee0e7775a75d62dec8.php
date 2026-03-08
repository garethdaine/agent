<?php declare(strict_types = 1);

// ftm-/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      '1e96f92bd64b59108359417d95885135' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
      'fb5f7257bfc66db249248bbc922ef79f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Queue',
         'uses' => 
        array (
          'queueablebybus' => 'Illuminate\\Bus\\Queueable',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
         'typeAliasClassName' => 'Illuminate\\Foundation\\Queue\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Queue\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '399d433dd3a464e384f48d57108e5be3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9290d733f0eb5e8fbe0b3b2898388d5f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c1db7affcddb204311a86abad2bba8be' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '34fc46532a9b0662d01975a092679121' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd6a9a80e39640f7cf5aed713b6a59f3d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '31c3ba6b98cd9dd37c960b8c0849b5e7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '364b55ebd39e62efa7a9173daf21bf3e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3dfb57621a0ac1149e7dde8460b16b4e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd2b717595496cc54620b51c947c6d9c2' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3e1a9cafbd713c39df33cd9f0d893fe3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '1440a3398d324a7b52b98cc75c844d7b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'b0838a6fe590339cd346e09d19c6bb2b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'cf2ab24c6a945028d9600a282c9e5e0e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'e657bb65a90f4334727e2264391d0279' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'e66f59276efa2f01caa9e3e8177ec065' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '34e3af7d57d0b6266a5d530eb5020393' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '49df80a5ed93e96e3c8d51601f623cc2' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7eb207c2d8a0fd4059a7015e362edd33' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '71da6a37b5fefd154ade63d869dcf880' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '60ef7ade1a58664fe52293aaf99bb3ed' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '1832c38e16f9bcc29c104166e3614661' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '50cba1c42e5be25ee75f2d17c52d8ce2' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9a088e747ce6ba3245779ee7ccd5e679' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b67bb2100b970a7d4d83e15f97c96002' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3f8960643578fbecd459ffce82a7cd32' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'bfa9f58e47fdb9f33949026d4fee28b5' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '42c1cfaca30f1aa922ed68424b6306a1' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0e47c70fda39068501d577bfc72bc8ee' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9a8a555603c6934d6467519e0a55f99e' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '1bf5d23b611a60f709bcc119a518a127' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9aac0f647f61bf9c097c6129f5ff5c7a' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c6c5ff5772449b6de3486b6ff6fdad73' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c0fda482bb048b595f429f198b1a5897' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7bc456d2c9ef17928db392d43c418e97' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c95c3947d1d6c1949dbe2ba752732f3a' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '72b093a91acb05ff3cf537f9372efe26' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '4668438b4020d07ff138cd46e740afeb' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '81ac61112ef0fb258ace48d96bd64b50' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7e43c5179eb44cee00e551e975c01e7a' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'cd366023ccc8d4a6691d3a0c2d436b37' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b8829efa53ab235fd089a725abea2aa0' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd35e37f8b01cda350ccaf0ed6952bd06' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '51501c629ca1957765b9a963ef785778' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '51c9f266e2213161cf48549ab6011f00' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '15b88c2746804d3f9f4885569f043f24' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5e039b04b0fcc2f8dd9cf0ecd1ecb036' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'bb7220bea3cde623adce04878ebf15ca' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5e7ab8ee14a8cbaf68ad727a29bf66f9' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9ab0d34a644d2cb1feee4c5b11bc543d' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd4ad6a96a1f7eca5a5b32b1d8f86c34c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '682a6d64fe1074fc9e688a420e73fe84' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '44c62598932e48eec5552aaf92466b73' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'a61d3c05264c96eeee95933fbf63f2ad' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '11f1bfde36efdcb0f4b7987c396709d3' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'b1aa92bec7fc2628c181252d476cf882' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '4a365adbd4bec9d099defd34370d106e' => 
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
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'feff5f533b5b0bf284432a2b3f96b437' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '97e4207dc00c3d22e206dbaa5d15da4f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5d6cc7f1e2f60a43635aa5190c0fc3d7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php',
          1 => 'App\\Jobs\\ExecuteAgentRunJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '237d72fada3f09788ac4f039fa679bb1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
      '85d331017321962487b0cd1cf5284116' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
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
      '6831bc0acd6dbfd4f78c06442ee73570' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'mergedEnvironment',
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
      '2ff04efff08008753e9442f68de50ddd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'finalizeTerminal',
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
      '388d804af8d4079ac5904da762225fe9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'recordRunCostFromEvents',
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
      'd13d7a0d23ea3566d0b52cbbee09c8f5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'extractUsageFromEvents',
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
      '60b16210aab78ee485f8033c1fa997a2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'attemptTargetedRetry',
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
      '4524ab127f02b4b37b98c500731da7ad' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'resolveRateLimitHoldUntil',
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
      '645cfb857be5937f7456c3ea6150910f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'applyUsageLimitPolicy',
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
      '4fe7dc91aa7efe75bbd798dca9546437' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'applyPathFailurePolicy',
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
      '71315ea9f53087edb5d45fe6d69e73ab' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'signalProcess',
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
      '32f337dbe3a3dc0854ecea28fb7e9e9c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'failRunSafely',
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
      '69cc1da144d87dfb560c4f8e7da6c904' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'resolvePermissionBlockerSummary',
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
      '7fac4528504ab6705e694be8364e8b30' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'extractRunContext',
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
      'ede1e552189cb3fb10fad298d953ffa9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'recordComplianceMetadata',
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
      '055fb6120cdb8ac58a178c29143804cc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'prepareEnhancedTaskMarkdown',
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
      'c408875e95bd452a1b1b3f7026e5e55c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'prepareEnhancedTaskMarkdownFromContent',
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
      '45b29233d7b849a90d670e0f618c2b37' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'updateMetadata',
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
      '458a34d58fe4eff28e378c6ad1d262a6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'orchestrationpolicyservicecontract' => 'App\\Contracts\\OrchestrationPolicyServiceContract',
          'agentjobrunfinished' => 'App\\Events\\AgentJobRunFinished',
          'runstatuschanged' => 'App\\Events\\RunStatusChanged',
          'memoryformationjob' => 'App\\Jobs\\Memory\\MemoryFormationJob',
          'agentjobrun' => 'App\\Models\\AgentJobRun',
          'memoryproviderusage' => 'App\\Models\\MemoryProviderUsage',
          'workflowbudgetenforcer' => 'App\\Services\\Cost\\WorkflowBudgetEnforcer',
          'commandtemplaterenderer' => 'App\\Support\\Agent\\CommandTemplateRenderer',
          'databaseisolationenvironment' => 'App\\Support\\Agent\\DatabaseIsolationEnvironment',
          'duration' => 'App\\Support\\Agent\\Duration',
          'envpolicy' => 'App\\Support\\Agent\\EnvPolicy',
          'failuremodeclassifier' => 'App\\Support\\Agent\\FailureModeClassifier',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'prerundatabasebackup' => 'App\\Support\\Agent\\PreRunDatabaseBackup',
          'reasoningstepparser' => 'App\\Support\\Agent\\ReasoningStepParser',
          'runeventwriter' => 'App\\Support\\Agent\\RunEventWriter',
          'runstatetransitionservice' => 'App\\Support\\Agent\\RunStateTransitionService',
          'runtimevalidation' => 'App\\Support\\Agent\\RuntimeValidation',
          'starpreamblegenerator' => 'App\\Support\\Agent\\StarPreambleGenerator',
          'targetedretryservice' => 'App\\Support\\Agent\\TargetedRetryService',
          'usagelimitstate' => 'App\\Support\\Agent\\UsageLimitState',
          'skillcontextinjector' => 'App\\Services\\Skills\\SkillContextInjector',
          'skillresolver' => 'App\\Services\\Skills\\SkillResolver',
          'policyevaluationresult' => 'App\\Support\\Compliance\\DTOs\\PolicyEvaluationResult',
          'memorycontextbuilder' => 'App\\Support\\Memory\\MemoryContextBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'file' => 'Illuminate\\Support\\Facades\\File',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'str' => 'Illuminate\\Support\\Str',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteAgentRunJob',
         'functionName' => 'cleanupEnhancedTaskFile',
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
      '/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php' => '34a288adb3f7c238c542c91cc3e8134e16dc351b780e191011cb9b3b57550692',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Queue/Queueable.php' => '3f02abd5d38d7cf07e64a46b9cc5e578004e5ff10401432f683ef354bc8f3419',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/Dispatchable.php' => '551294291775e57fbd590f0ed288a91cca683d42fac08e60c87e39b73617d47b',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php' => '8d300c3adb967aa56c0827ba587e456e32e40fbb1c0d9f649f6bf7c0d876e937',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Support/InteractsWithTime.php' => 'ee4ef3a2e714fa539b223287a3a62b618b1d3a9e44f2e1f92981f2c3e2773ad5',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Bus/Queueable.php' => '7df8b51aab8bd3196229be1a8e398c2c2ec636ae1767ce499a64bfdbf5675c47',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesModels.php' => '29ff50de875925956c56b217ef9b78643cef0e12e23885fdf37e0ed9b697e51d',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php' => 'd4cb97259a134d2089c54c969c2176704f0df2fa2483f149b61029c5c993f82d',
    ),
  ),
));