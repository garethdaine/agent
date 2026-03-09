<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

class GetRuntimePolicyAction
{
    /**
     * @return array{default_mode: string, modes: list<string>, tool_deny: list<string>, tool_allow: list<string>, tool_allowlist_active: bool}
     */
    public function execute(): array
    {
        $toolDeny = config('runtime.tool_deny', []);
        $toolAllow = config('runtime.tool_allow', []);

        return [
            'default_mode' => config('runtime.default_mode', 'safe'),
            'modes' => array_keys(config('runtime.modes', [])),
            'tool_deny' => array_values($toolDeny),
            'tool_allow' => array_values($toolAllow),
            'tool_allowlist_active' => $toolAllow !== [],
        ];
    }
}
