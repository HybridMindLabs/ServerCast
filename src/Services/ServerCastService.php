<?php

namespace Hybridcore\ServerCast\Services;

use App\Models\Server;
use App\Models\ServerCommand;
use App\Services\Bridge\BridgeService;
use Hybridcore\ServerCast\Models\Broadcast;
use Illuminate\Support\Collection;

class ServerCastService
{
    /** Bridge command prefix the game-server plugin recognises. */
    private const COMMAND_PREFIX = 'broadcast:';

    /** Where the bridge records these commands came from. */
    private const SOURCE = 'hybridcore/servercast';

    public function __construct(private readonly BridgeService $bridge) {}

    /**
     * Send a message to a set of servers.
     *
     * @param  list<int>|null  $serverIds  null or empty = every active server
     */
    public function send(string $message, ?array $serverIds, ?int $userId): Broadcast
    {
        $all = $serverIds === null || $serverIds === [];

        $servers = Server::query()
            ->where('is_active', true)
            ->when(! $all, fn ($q) => $q->whereIn('id', $serverIds))
            ->get();

        $command = self::COMMAND_PREFIX.$message;
        $deliveries = [];

        foreach ($servers as $server) {
            // A server without a bridge token can't receive commands — record
            // it as such rather than silently dropping it.
            if (! $server->bridge_enabled) {
                $deliveries[] = ['server_id' => $server->id, 'command_id' => null];

                continue;
            }

            $queued = $this->bridge->queue($server, $command, self::SOURCE);
            $deliveries[] = ['server_id' => $server->id, 'command_id' => $queued->id];
        }

        return Broadcast::create([
            'message' => $message,
            'target_type' => $all ? 'all' : 'selected',
            'deliveries' => $deliveries,
            'sent_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * Resolve the live delivery status for a set of broadcasts in one query,
     * keyed by command id, so the panel can show per-server progress.
     *
     * @param  Collection<int, Broadcast>  $broadcasts
     * @return array<int, string> command_id => status
     */
    public function statusesFor(Collection $broadcasts): array
    {
        $ids = $broadcasts->flatMap->commandIds()->unique()->all();

        if ($ids === []) {
            return [];
        }

        return ServerCommand::whereIn('id', $ids)
            ->pluck('status', 'id')
            ->all();
    }

    /**
     * A one-word summary of a delivery for display.
     * pending → queued, delivered → sent, acked → confirmed.
     */
    public function label(?int $commandId, array $statuses): string
    {
        if ($commandId === null) {
            return 'no-bridge';
        }

        return match ($statuses[$commandId] ?? ServerCommand::STATUS_PENDING) {
            ServerCommand::STATUS_ACKED => 'confirmed',
            ServerCommand::STATUS_DELIVERED => 'sent',
            ServerCommand::STATUS_EXPIRED => 'expired',
            default => 'queued',
        };
    }
}
