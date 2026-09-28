<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_and_desktop_navigation_match_authorized_routes_for_each_role(): void
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);

        $expected = [
            UserRole::AgencyOwner->value => [
                'dashboard', 'demands.index', 'notifications.index', 'organization-assistant.index',
                'team.index', 'service-access.index',
                'knowledge.index', 'api-tokens.index',
            ],
            UserRole::MarketingManager->value => [
                'dashboard', 'demands.index', 'notifications.index', 'organization-assistant.index',
                'team.activity', 'service-access.index', 'knowledge.index', 'api-tokens.index',
            ],
            UserRole::Professional->value => [
                'dashboard', 'demands.index', 'notifications.index', 'team.activity', 'service-access.index', 'knowledge.index', 'api-tokens.index',
            ],
            UserRole::Client->value => ['dashboard', 'demands.index', 'notifications.index'],
        ];

        foreach ($expected as $role => $routes) {
            $user = User::factory()->create([
                'organization_id' => $organization->id,
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $html = $this->get(route('dashboard'))->getContent();
            preg_match_all('/<a class="nav-item[^\"]*" href="([^"]+)"/', $html, $desktopLinks);
            preg_match_all('/<a href="([^"]+)">/', $this->between($html, 'aria-label="Navegação para celular"', '</nav>'), $mobileLinks);

            $desktopPaths = collect($desktopLinks[1])->map(fn (string $url): string => parse_url($url, PHP_URL_PATH))->all();
            $mobilePaths = collect($mobileLinks[1])->map(fn (string $url): string => parse_url($url, PHP_URL_PATH))->all();
            $expectedPaths = collect($routes)->map(fn (string $name): string => route($name, [], false))->all();

            $this->assertSame($expectedPaths, $desktopPaths, "Desktop links differ for role {$role}.");
            $this->assertSame($expectedPaths, $mobilePaths, "Mobile links differ for role {$role}.");
            $this->assertStringNotContainsString(route('approvals.index'), $html);
            $this->assertStringNotContainsString(route('demand-tasks.board'), $html);
            $this->assertStringNotContainsString(route('team.capacity'), $html);
            $this->assertStringNotContainsString(route('performance-reviews.index'), $html);

            foreach ($routes as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName));
                $this->assertSame(200, $response->getStatusCode(), "Route {$routeName} failed for role {$role}.");
            }

            if ($role === UserRole::Client->value) {
                $this->get(route('team.capacity'))->assertForbidden();
                $this->get(route('performance-reviews.index'))->assertForbidden();
                $this->get(route('service-access.index'))->assertForbidden();
            } elseif (in_array($role, [UserRole::MarketingManager->value, UserRole::Professional->value], true)) {
                $this->get(route('service-access.index'))->assertOk();
            }

            if ($role !== UserRole::Client->value) {
                $this->get(route('demands.index', ['view' => 'tasks']))->assertOk()->assertSee('Tarefas');
                $this->get(route('demands.index', ['view' => 'board']))->assertOk()->assertSee('Demandas');
                $this->get(route('approvals.index'))->assertRedirect(route('demands.index', ['view' => 'board']));
            }
        }
    }

    private function between(string $value, string $start, string $end): string
    {
        $offset = strpos($value, $start);
        $this->assertNotFalse($offset, "Could not find {$start} in rendered dashboard.");
        $value = substr($value, $offset);
        $length = strpos($value, $end);
        $this->assertNotFalse($length, "Could not find {$end} in rendered dashboard.");

        return substr($value, 0, $length + strlen($end));
    }
}
