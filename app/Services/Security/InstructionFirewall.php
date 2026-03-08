<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Security\FirewallDecision;
use App\Enums\Runtime\RuntimeMode;

class InstructionFirewall
{
    private const array SENSITIVE_MUTATION_TOOLS = [
        'fs.write',
        'fs.edit',
        'fs.delete',
        'fs.move',
        'runtime.exec',
        'runtime.spawn',
        'runtime.kill',
        'runtime.run',
        'runtime.stop',
        'agent_api.run_now',
        'agent_api.stop_run',
        'discovery.start',
    ];

    private const array SENSITIVE_EXTERNAL_TOOLS = [
        'web.fetch',
        'browser.navigate',
        'mcp.call',
    ];

    /** @var array<string> */
    private readonly array $allowedHosts;

    /**
     * @param  array<string>|null  $allowedHosts  Override for testing; defaults to config('runtime.web.allowed_hosts')
     */
    public function __construct(
        private readonly SecurityConfigProvider $config,
        ?array $allowedHosts = null,
    ) {
        $this->allowedHosts = $allowedHosts ?? (function_exists('config') ? (array) config('runtime.web.allowed_hosts', []) : []);
    }

    public function evaluate(
        string $qualifiedToolName,
        array $args,
        TurnSecurityContext $turnContext,
        RuntimeMode $mode,
        ?int $accountId = null,
    ): FirewallDecision {
        if (! $turnContext->isTainted()) {
            return new FirewallDecision(
                allowed: true,
                requiresEscalation: false,
                reason: null,
                taintSource: null,
            );
        }

        $isSensitive = $this->isSensitiveTool($qualifiedToolName, $args);

        if ($isSensitive) {
            return new FirewallDecision(
                allowed: false,
                requiresEscalation: true,
                reason: "Sensitive tool '{$qualifiedToolName}' called during tainted turn",
                taintSource: $turnContext->getTaintSource(),
            );
        }

        $defaultDeny = (bool) $this->config->get('default_deny_external', $accountId);

        if ($defaultDeny) {
            return new FirewallDecision(
                allowed: false,
                requiresEscalation: true,
                reason: "Tool '{$qualifiedToolName}' blocked by default_deny_external during tainted turn",
                taintSource: $turnContext->getTaintSource(),
            );
        }

        return new FirewallDecision(
            allowed: true,
            requiresEscalation: false,
            reason: null,
            taintSource: $turnContext->getTaintSource(),
        );
    }

    public function isSensitiveTool(string $qualifiedToolName, array $args): bool
    {
        if (in_array($qualifiedToolName, self::SENSITIVE_MUTATION_TOOLS, true)) {
            return true;
        }

        if ($qualifiedToolName === 'web.fetch') {
            $url = $args['url'] ?? '';
            $host = parse_url($url, PHP_URL_HOST);

            if ($host === null || $host === false) {
                return true;
            }

            return ! in_array($host, $this->allowedHosts, true);
        }

        if (in_array($qualifiedToolName, self::SENSITIVE_EXTERNAL_TOOLS, true)) {
            return true;
        }

        return false;
    }

    private function isMutationOrExternal(string $qualifiedToolName): bool
    {
        $allMutations = self::SENSITIVE_MUTATION_TOOLS;
        $allExternal = [
            'web.search',
            'web.fetch',
            'browser.navigate',
            'browser.click',
            'browser.type',
            'browser.screenshot',
            'browser.extract',
            'mcp.call',
        ];

        return in_array($qualifiedToolName, $allMutations, true)
            || in_array($qualifiedToolName, $allExternal, true);
    }
}
