<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves deterministic filesystem paths for persistent browser profiles.
 *
 * Profiles are namespaced per user to prevent cross-user access:
 *   {base}/{user_id}/{slug(profile_name)}/
 *
 * Profile names are sanitized via Str::slug() to prevent directory traversal.
 */
class BrowserProfileResolver
{
    /**
     * Resolve the absolute filesystem path for a named browser profile.
     */
    public function resolveProfilePath(User $user, string $profileName): string
    {
        $basePath = rtrim((string) config('runtime.browser.profiles_base_path', storage_path('app/browser-profiles')), '/');
        $sanitized = Str::slug($profileName);

        if ($sanitized === '') {
            $sanitized = 'default';
        }

        return $basePath.'/'.$user->id.'/'.$sanitized;
    }

    /**
     * Generate a default profile name based on the connector account.
     *
     * When a connector is available, the name is derived from its provider
     * and name (e.g. "discord-axiomspark"). Without a connector, returns "default".
     */
    public function resolveDefaultProfileName(User $user, ?ConnectorAccount $connector): string
    {
        if ($connector !== null) {
            $provider = $connector->provider ?? '';
            $name = $connector->name ?? '';

            $combined = trim("{$provider}-{$name}", '-');

            if ($combined !== '') {
                return Str::slug($combined);
            }
        }

        return 'default';
    }

    /**
     * Ensure the profile directory exists on the filesystem.
     */
    public function ensureProfileDirectory(string $profilePath): void
    {
        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0755, true);
        }
    }

    /**
     * Check whether a profile directory already exists.
     */
    public function profileExists(string $profilePath): bool
    {
        return is_dir($profilePath);
    }

    /**
     * List all profile directory names for a user.
     *
     * @return array<int, string>
     */
    public function listProfiles(User $user): array
    {
        $basePath = rtrim((string) config('runtime.browser.profiles_base_path', storage_path('app/browser-profiles')), '/');
        $userDir = $basePath.'/'.$user->id;

        if (! is_dir($userDir)) {
            return [];
        }

        $entries = scandir($userDir) ?: [];

        return array_values(array_filter(
            $entries,
            fn (string $name) => $name !== '.' && $name !== '..' && is_dir($userDir.'/'.$name)
        ));
    }
}
