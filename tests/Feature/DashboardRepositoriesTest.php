<?php

namespace Ibrohim\Changelog\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ibrohim\Changelog\Tests\TestCase;
use Ibrohim\Changelog\Models\ChangelogRepository;

class DashboardRepositoriesTest extends TestCase
{
    /**
     * Override environment to disable auth middleware for dashboard tests.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('changelog.dashboard_middleware', ['web']);
    }

    public function test_can_view_repositories_page(): void
    {
        $this->createRepository(['name' => 'owner/repo']);

        $response = $this->get('/changelog/dashboard/repositories');

        $response->assertStatus(200);
        $response->assertSee('owner/repo');
        $response->assertSee('Add New Repository');
    }

    public function test_can_store_new_repository(): void
    {
        $response = $this->post('/changelog/dashboard/repositories', [
            'owner' => 'newowner',
            'repo' => 'newrepo',
            'webhook_secret' => 'supersecret',
            'default_branch' => 'main',
        ]);

        $response->assertRedirect('/changelog/dashboard/repositories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('changelog_repositories', [
            'name' => 'newowner/newrepo',
            'owner' => 'newowner',
            'repo' => 'newrepo',
            'default_branch' => 'main',
            'github_id' => null,
            'is_active' => true,
        ]);

        // Secret should be encrypted, not stored in plaintext
        $repo = ChangelogRepository::where('name', 'newowner/newrepo')->first();
        $this->assertNotEquals('supersecret', $repo->getRawOriginal('webhook_secret'));
        $this->assertEquals('supersecret', $repo->webhook_secret);
    }

    public function test_store_repository_validates_input(): void
    {
        $response = $this->post('/changelog/dashboard/repositories', [
            'owner' => '', // invalid
            'repo' => 'newrepo',
            'webhook_secret' => 'supersecret',
            'default_branch' => 'main',
        ]);

        $response->assertSessionHasErrors('owner');
        $this->assertDatabaseCount('changelog_repositories', 0);
    }

    public function test_can_destroy_repository_and_cascades_entries(): void
    {
        $repo = $this->createRepository(['name' => 'owner/repo']);
        $this->createEntry(['changelog_repository_id' => $repo->id]);
        $this->createEntry(['changelog_repository_id' => $repo->id]);

        $this->assertDatabaseCount('changelog_repositories', 1);
        $this->assertDatabaseCount('changelog_entries', 2);

        $response = $this->delete("/changelog/dashboard/repositories/{$repo->id}");

        $response->assertRedirect('/changelog/dashboard/repositories');
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('changelog_repositories', 0);
        $this->assertDatabaseCount('changelog_entries', 0); // Cascaded delete
    }
}
