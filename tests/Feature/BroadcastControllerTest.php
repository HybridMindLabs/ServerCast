<?php

namespace Hybridcore\ServerCast\Tests\Feature;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Services\Bridge\BridgeService;
use App\Services\Extensions\ExtensionAutoloader;
use Hybridcore\ServerCast\Models\Broadcast;
use Hybridcore\ServerCast\ServerCastServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BroadcastControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents(storage_path('installed.lock'), 'installed');

        // Routes normally register only for extensions the DB marks enabled
        // at boot — a fresh RefreshDatabase test has no such row, so wire
        // them by hand. Mirrors the staff/announcements extension tests.
        $base = base_path('extensions/hybridcore/servercast');
        ExtensionAutoloader::register($base, ['namespace' => 'Hybridcore\\ServerCast']);

        $this->app->register(ServerCastServiceProvider::class);

        Route::middleware(['web', 'auth', EnsureIsAdmin::class])
            ->prefix('admin')->group($base.'/routes/admin.php');

        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function server(): Server
    {
        $game = Game::factory()->create(['query_driver' => 'cs16']);

        return Server::factory()->create(['game_id' => $game->id, 'is_active' => true]);
    }

    public function test_index_requires_the_view_permission(): void
    {
        $this->get(route('admin.servercast.index'))->assertRedirect();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.servercast.index'))->assertOk();
    }

    public function test_admin_can_send_a_broadcast_to_all_servers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = $this->server();
        app(BridgeService::class)->issueToken($server);

        $this->actingAs($admin)->post(route('admin.servercast.store'), [
            'message' => 'Round starting soon!',
            'target' => 'all',
        ])->assertRedirect();

        $this->assertDatabaseHas('servercast_broadcasts', ['target_type' => 'all']);
    }

    public function test_selected_target_requires_at_least_one_server(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.servercast.store'), [
            'message' => 'Hi',
            'target' => 'selected',
            'server_ids' => [],
        ])->assertSessionHasErrors('server_ids');
    }

    public function test_admin_can_delete_a_broadcast_from_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $broadcast = Broadcast::create([
            'message' => 'Gone', 'target_type' => 'all', 'deliveries' => [], 'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.servercast.destroy', $broadcast))
            ->assertRedirect();

        $this->assertDatabaseMissing('servercast_broadcasts', ['id' => $broadcast->id]);
    }

    public function test_sending_requires_the_send_permission(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)->post(route('admin.servercast.store'), [
            'message' => 'Hi', 'target' => 'all',
        ])->assertRedirect('/admin/login');
    }
}
