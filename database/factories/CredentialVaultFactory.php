<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CredentialVault;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<CredentialVault>
 */
class CredentialVaultFactory extends Factory
{
    protected $model = CredentialVault::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(['openai', 'anthropic', 'github', 'slack']),
            'key' => $this->faker->unique()->slug(2),
            'credential_type' => CredentialVault::TYPE_API_KEY,
            'name' => $this->faker->company().' API Key',
            'encrypted_value' => Crypt::encryptString(json_encode(['api_key' => $this->faker->sha256()])),
            'metadata' => null,
            'approval_mode' => CredentialVault::APPROVAL_SUPERVISED,
        ];
    }

    public function login(): static
    {
        return $this->state(fn () => [
            'credential_type' => CredentialVault::TYPE_LOGIN,
            'name' => $this->faker->domainName().' Login',
            'url' => $this->faker->url(),
            'encrypted_value' => Crypt::encryptString(json_encode([
                'username' => $this->faker->userName(),
                'password' => $this->faker->password(),
            ])),
        ]);
    }

    public function apiKey(): static
    {
        return $this->state(fn () => [
            'credential_type' => CredentialVault::TYPE_API_KEY,
            'name' => $this->faker->company().' API Key',
            'encrypted_value' => Crypt::encryptString(json_encode([
                'api_key' => $this->faker->sha256(),
            ])),
            'metadata' => ['base_url' => $this->faker->url(), 'auth_method' => 'header'],
        ]);
    }

    public function sshKey(): static
    {
        return $this->state(fn () => [
            'credential_type' => CredentialVault::TYPE_SSH_KEY,
            'name' => $this->faker->domainName().' SSH Key',
            'url' => 'ssh://'.$this->faker->domainName().':22',
            'encrypted_value' => Crypt::encryptString(json_encode([
                'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----'."\n".$this->faker->sha256()."\n".'-----END OPENSSH PRIVATE KEY-----',
                'passphrase' => $this->faker->password(),
            ])),
            'metadata' => ['key_type' => 'ed25519', 'host' => $this->faker->domainName(), 'port' => 22],
        ]);
    }

    public function oauthToken(): static
    {
        return $this->state(fn () => [
            'credential_type' => CredentialVault::TYPE_OAUTH_TOKEN,
            'name' => $this->faker->company().' OAuth',
            'encrypted_value' => Crypt::encryptString(json_encode([
                'access_token' => $this->faker->sha256(),
                'refresh_token' => $this->faker->sha256(),
            ])),
            'metadata' => ['token_url' => $this->faker->url().'/oauth/token', 'scopes' => ['read', 'write']],
            'expires_at' => now()->addHours(1),
        ]);
    }

    public function autonomous(): static
    {
        return $this->state(fn () => [
            'approval_mode' => CredentialVault::APPROVAL_AUTONOMOUS,
        ]);
    }

    public function supervised(): static
    {
        return $this->state(fn () => [
            'approval_mode' => CredentialVault::APPROVAL_SUPERVISED,
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn () => [
            'approval_mode' => CredentialVault::APPROVAL_RESTRICTED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
