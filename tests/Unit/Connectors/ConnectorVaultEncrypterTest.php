<?php

namespace Tests\Unit\Connectors;

use App\Models\Team;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Tests\TestCase;

class ConnectorVaultEncrypterTest extends TestCase
{
    private ConnectorVaultEncrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encrypter = new ConnectorVaultEncrypter;
    }

    public function test_encrypts_and_decrypts_roundtrip(): void
    {
        $team = $this->makeTeamWithVaultKey('test-vault-key-1234567890abcdef');

        $plaintext = '{"access_token":"sk-abc123","refresh_token":"rt-xyz789"}';
        $ciphertext = $this->encrypter->encrypt($team, $plaintext);

        $this->assertNotEquals($plaintext, $ciphertext);
        $this->assertEquals($plaintext, $this->encrypter->decrypt($team, $ciphertext));
    }

    public function test_different_teams_produce_different_ciphertext(): void
    {
        $teamA = $this->makeTeamWithVaultKey('vault-key-team-alpha-1234567890');
        $teamB = $this->makeTeamWithVaultKey('vault-key-team-bravo-0987654321');

        $plaintext = 'shared-secret-value';
        $ciphertextA = $this->encrypter->encrypt($teamA, $plaintext);
        $ciphertextB = $this->encrypter->encrypt($teamB, $plaintext);

        $this->assertNotEquals($ciphertextA, $ciphertextB);
    }

    public function test_wrong_team_key_fails_decryption(): void
    {
        $teamA = $this->makeTeamWithVaultKey('vault-key-team-alpha-1234567890');
        $teamB = $this->makeTeamWithVaultKey('vault-key-team-bravo-0987654321');

        $ciphertext = $this->encrypter->encrypt($teamA, 'secret-data');

        $this->expectException(DecryptException::class);
        $this->encrypter->decrypt($teamB, $ciphertext);
    }

    public function test_handles_empty_string(): void
    {
        $team = $this->makeTeamWithVaultKey('vault-key-empty-string-test-1234');

        $ciphertext = $this->encrypter->encrypt($team, '');
        $this->assertEquals('', $this->encrypter->decrypt($team, $ciphertext));
    }

    public function test_throws_when_team_has_no_vault_key(): void
    {
        $team = new Team;
        $team->id = 1;
        $team->connector_vault_key = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no connector vault key configured');
        $this->encrypter->encrypt($team, 'test');
    }

    private function makeTeamWithVaultKey(string $vaultKey): Team
    {
        $team = new Team;
        $team->id = rand(1, 99999);
        $team->connector_vault_key = $vaultKey;

        return $team;
    }
}
