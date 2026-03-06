<?php

namespace App\Support\Agent;

use App\Models\AgentSystemState;
use Illuminate\Support\Str;

class InstanceFingerprint
{
    private const SALT_KEY = 'instance_install_salt';

    public function generate(): string
    {
        $components = [
            hash('sha256', (string) config('app.key', '')),
            (string) config('database.connections.'.config('database.default').'.database', ''),
            parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: '',
            $this->getOrCreateSalt(),
        ];

        return hash('sha256', implode('|', $components));
    }

    private function getOrCreateSalt(): string
    {
        $existing = AgentSystemState::where('key', self::SALT_KEY)->first();

        if ($existing) {
            return $existing->value;
        }

        $salt = Str::random(64);

        AgentSystemState::create([
            'key' => self::SALT_KEY,
            'value' => $salt,
        ]);

        return $salt;
    }
}
