<?php

namespace Hybridcore\ServerCast\Tests\Feature;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Services\Bridge\BridgeService;
use Hybridcore\ServerCast\Models\Broadcast;
use Hybridcore\ServerCast\Services\ServerCastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerCastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function server(bool $bridge = true): Server
    {
        $game = Game::factory()->create(['query_driver' => 'cs16']);
        $server = Server::factory()->create(['game_id' => $game->id, 'is_active' => true]);

        if ($bridge) {
            app(BridgeService::class)->issueToken($server);
            $server->refresh();
        }

        return $server;
    }

    private function service(): ServerCastService
    {
        return app(ServerCastService::class);
    }

    public function test_it_queues_a_prefixed_bridge_command_per_server(): void
    {
        $a = $this->server();
        $b = $this->server();

        $broadcast = $this->service()->send('Round starting soon!', null, null);

        $this->assertSame('all', $broadcast->target_type);
        $this->assertCount(2, $broadcast->deliveries);

        // Each reachable server got exactly one command, prefixed so the plugin
        // knows to print it rather than run it as a console command.
        $this->assertSame(2, ServerCommand::count());
        $this->assertSame('broadcast:Round starting soon!', ServerCommand::first()->command);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], ServerCommand::pluck('server_id')->all());
    }

    public function test_it_targets_only_the_selected_servers(): void
    {
        $chosen = $this->server();
        $this->server(); // another, not chosen

        $broadcast = $this->service()->send('Just you', [$chosen->id], null);

        $this->assertSame('selected', $broadcast->target_type);
        $this->assertSame(1, ServerCommand::count());
        $this->assertSame($chosen->id, ServerCommand::first()->server_id);
    }

    public function test_a_server_without_a_bridge_token_is_recorded_but_gets_no_command(): void
    {
        $this->server(bridge: false);

        $broadcast = $this->service()->send('Hi', null, null);

        // Still listed as a target (so the operator sees it), but nothing queued.
        $this->assertCount(1, $broadcast->deliveries);
        $this->assertNull($broadcast->deliveries[0]['command_id']);
        $this->assertSame(0, ServerCommand::count());
    }

    public function test_status_label_reflects_the_command_lifecycle(): void
    {
        $server = $this->server();
        $broadcast = $this->service()->send('x', null, null);

        $commandId = $broadcast->deliveries[0]['command_id'];
        $statuses = $this->service()->statusesFor(Broadcast::all());

        $this->assertSame('queued', $this->service()->label($commandId, $statuses));

        ServerCommand::find($commandId)->update(['status' => ServerCommand::STATUS_ACKED]);
        $statuses = $this->service()->statusesFor(Broadcast::all());
        $this->assertSame('confirmed', $this->service()->label($commandId, $statuses));
    }
}
