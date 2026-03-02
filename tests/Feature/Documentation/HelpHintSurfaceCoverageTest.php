<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HelpHintSurfaceCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_surfaces_render_discoverable_docs_entry_points(): void
    {
        config()->set('delegation.ui_enabled', true);
        config()->set('agent.org.enabled', true);

        $user = User::factory()->create();

        $surfaces = [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'params' => [],
                'component' => 'Dashboard',
                'page' => 'Dashboard.vue',
            ],
            [
                'label' => 'Jobs',
                'route' => 'agent.jobs.index',
                'params' => [],
                'component' => 'Agent/Jobs/Index',
                'page' => 'Agent/Jobs/Index.vue',
            ],
            [
                'label' => 'Monitor',
                'route' => 'agent.monitor.index',
                'params' => [],
                'component' => 'Agent/Monitor/Index',
                'page' => 'Agent/Monitor/Index.vue',
            ],
            [
                'label' => 'Messenger',
                'route' => 'tools.messenger.index',
                'params' => [],
                'component' => 'Tools/Messenger/Index',
                'page' => 'Tools/Messenger/Index.vue',
            ],
            [
                'label' => 'Discovery',
                'route' => 'tools.discovery.index',
                'params' => [],
                'component' => 'Tools/Discovery/Index',
                'page' => 'Tools/Discovery/Index.vue',
            ],
            [
                'label' => 'Backups',
                'route' => 'tools.backups.settings',
                'params' => [],
                'component' => 'Tools/Backups/Settings',
                'page' => 'Tools/Backups/Settings.vue',
            ],
            [
                'label' => 'Feature Flags',
                'route' => 'tools.features.settings',
                'params' => [],
                'component' => 'Tools/Features/Settings',
                'page' => 'Tools/Features/Settings.vue',
            ],
            [
                'label' => 'Memory',
                'route' => 'tools.memory.index',
                'params' => [],
                'component' => 'Tools/Memory/Index',
                'page' => 'Tools/Memory/Index.vue',
            ],
            [
                'label' => 'Delegation',
                'route' => 'agent.delegation.index',
                'params' => [],
                'component' => 'Agent/Delegation/Index',
                'page' => 'Agent/Delegation/Index.vue',
            ],
            [
                'label' => 'Org',
                'route' => 'org.index',
                'params' => [],
                'component' => 'Agent/Org/Index',
                'page' => 'Agent/Org/Index.vue',
            ],
            [
                'label' => 'Profile/Security/Account',
                'route' => 'profile.show',
                'params' => [],
                'component' => 'Profile/Show',
                'page' => 'Profile/Show.vue',
            ],
            [
                'label' => 'Integration Link Flow',
                'route' => 'messenger.link.show',
                'params' => ['token' => 'help-hint-surface-test-token'],
                'component' => 'Messenger/LinkAccount',
                'page' => 'Messenger/LinkAccount.vue',
                'auth' => false,
            ],
        ];

        if (Route::has('api-tokens.index')) {
            $surfaces[] = [
                'label' => 'API Token Flow',
                'route' => 'api-tokens.index',
                'params' => [],
                'component' => 'API/Index',
                'page' => 'API/Index.vue',
            ];
        }

        foreach ($surfaces as $surface) {
            $this->assertTrue(
                Route::has($surface['route']),
                sprintf('Required surface route [%s] for [%s] is missing.', $surface['route'], $surface['label'])
            );

            $request = ($surface['auth'] ?? true) === false
                ? $this->get(route($surface['route'], $surface['params']))
                : $this->actingAs($user)->get(route($surface['route'], $surface['params']));

            $request
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component($surface['component'])
                );

            $this->assertSurfaceContainsDocsEntryPoint($surface['page'], $surface['label']);
        }
    }

    private function assertSurfaceContainsDocsEntryPoint(string $page, string $label): void
    {
        $path = resource_path('js/Pages/'.$page);
        $template = file_get_contents($path);

        $this->assertIsString($template, sprintf('Could not read page template for [%s]: %s', $label, $path));

        $markers = [
            '<HelpHint',
            "route('docs.index'",
            "route('docs.show'",
            'learn-more-href="/docs/',
            'href="/docs/',
        ];

        $hasEntryPoint = false;
        foreach ($markers as $marker) {
            if (str_contains($template, $marker)) {
                $hasEntryPoint = true;
                break;
            }
        }

        $this->assertTrue(
            $hasEntryPoint,
            sprintf(
                'Expected [%s] (%s) to render at least one discoverable docs entry point (HelpHint/helper/docs link).',
                $label,
                $path,
            )
        );
    }
}
