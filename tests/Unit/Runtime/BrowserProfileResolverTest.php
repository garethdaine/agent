<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Runtime\BrowserProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    private BrowserProfileResolver $resolver;

    private string $tempBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new BrowserProfileResolver;

        $this->tempBasePath = sys_get_temp_dir().'/browser-profiles-test-'.uniqid();
        config(['runtime.browser.profiles_base_path' => $this->tempBasePath]);
    }

    protected function tearDown(): void
    {
        // Clean up temp directories
        if (is_dir($this->tempBasePath)) {
            $this->recursiveRemoveDir($this->tempBasePath);
        }
        parent::tearDown();
    }

    public function test_resolves_profile_path_with_user_id_and_slug(): void
    {
        $user = User::factory()->create();

        $path = $this->resolver->resolveProfilePath($user, 'twitter-login');

        $this->assertEquals(
            $this->tempBasePath.'/'.$user->id.'/twitter-login',
            $path
        );
    }

    public function test_resolves_profile_path_with_spaces_and_special_chars(): void
    {
        $user = User::factory()->create();

        $path = $this->resolver->resolveProfilePath($user, 'My Profile Name!');

        $this->assertStringEndsWith('/my-profile-name', $path);
        $this->assertStringContainsString((string) $user->id, $path);
    }

    public function test_empty_profile_name_defaults_to_default(): void
    {
        $user = User::factory()->create();

        $path = $this->resolver->resolveProfilePath($user, '');

        $this->assertStringEndsWith('/default', $path);
    }

    public function test_profile_path_sanitizes_directory_traversal(): void
    {
        $user = User::factory()->create();

        $path = $this->resolver->resolveProfilePath($user, '../../../etc/passwd');

        // Str::slug removes dots and slashes
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('etc/passwd', $path);
    }

    public function test_default_profile_name_from_connector(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->make([
            'provider' => 'discord',
            'name' => 'AxiomSpark',
        ]);

        $name = $this->resolver->resolveDefaultProfileName($user, $connector);

        $this->assertEquals('discord-axiomspark', $name);
    }

    public function test_default_profile_name_without_connector(): void
    {
        $user = User::factory()->create();

        $name = $this->resolver->resolveDefaultProfileName($user, null);

        $this->assertEquals('default', $name);
    }

    public function test_ensure_profile_directory_creates_missing_dirs(): void
    {
        $user = User::factory()->create();
        $path = $this->resolver->resolveProfilePath($user, 'test-profile');

        $this->assertFalse(is_dir($path));

        $this->resolver->ensureProfileDirectory($path);

        $this->assertTrue(is_dir($path));
    }

    public function test_ensure_profile_directory_no_error_if_exists(): void
    {
        $user = User::factory()->create();
        $path = $this->resolver->resolveProfilePath($user, 'test-profile');

        $this->resolver->ensureProfileDirectory($path);
        $this->resolver->ensureProfileDirectory($path); // second call should be safe

        $this->assertTrue(is_dir($path));
    }

    public function test_profile_exists_returns_false_for_missing(): void
    {
        $this->assertFalse(
            $this->resolver->profileExists($this->tempBasePath.'/nonexistent/profile')
        );
    }

    public function test_profile_exists_returns_true_for_existing(): void
    {
        $user = User::factory()->create();
        $path = $this->resolver->resolveProfilePath($user, 'existing');
        $this->resolver->ensureProfileDirectory($path);

        $this->assertTrue($this->resolver->profileExists($path));
    }

    public function test_list_profiles_returns_empty_for_new_user(): void
    {
        $user = User::factory()->create();

        $profiles = $this->resolver->listProfiles($user);

        $this->assertEquals([], $profiles);
    }

    public function test_list_profiles_returns_created_profiles(): void
    {
        $user = User::factory()->create();

        // Create two profiles
        $path1 = $this->resolver->resolveProfilePath($user, 'twitter');
        $path2 = $this->resolver->resolveProfilePath($user, 'linkedin');
        $this->resolver->ensureProfileDirectory($path1);
        $this->resolver->ensureProfileDirectory($path2);

        $profiles = $this->resolver->listProfiles($user);

        $this->assertCount(2, $profiles);
        $this->assertContains('twitter', $profiles);
        $this->assertContains('linkedin', $profiles);
    }

    private function recursiveRemoveDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->recursiveRemoveDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
