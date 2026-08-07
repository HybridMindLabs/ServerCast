<?php

namespace Hybridcore\ServerCast\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Hybridcore\ServerCast\Http\Requests\BroadcastRequest;
use Hybridcore\ServerCast\Models\Broadcast;
use Hybridcore\ServerCast\Services\ServerCastService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function __construct(private readonly ServerCastService $service) {}

    public function index(): Response
    {
        $broadcasts = Broadcast::with('sender:id,name')->latest('id')->limit(30)->get();
        $statuses = $this->service->statusesFor($broadcasts);

        return Inertia::render('Extensions/hybridcore/servercast/Admin/Index', [
            // The servers the operator can target — bridge-enabled ones can
            // actually receive; the rest are shown disabled with a hint.
            'servers' => Server::with('game:id,name,slug')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Server $s) => [
                    'id' => $s->id,
                    'name' => $s->name ?: $s->address,
                    'address' => $s->address,
                    'game' => $s->game?->name,
                    'game_slug' => $s->game?->slug,
                    'bridge_enabled' => $s->bridge_enabled,
                    'online' => $s->bridge_last_seen_at !== null
                        && $s->bridge_last_seen_at->gt(now()->subMinutes(2)),
                ]),

            'history' => $broadcasts->map(fn (Broadcast $b) => [
                'id' => $b->id,
                'message' => $b->message,
                'target_type' => $b->target_type,
                'sent_by' => $b->sender?->name,
                'created_at' => $b->created_at?->diffForHumans(),
                'deliveries' => collect($b->deliveries)->map(fn (array $d) => [
                    'server_id' => $d['server_id'],
                    'status' => $this->service->label($d['command_id'] ?? null, $statuses),
                ])->values(),
            ]),
        ]);
    }

    public function store(BroadcastRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $broadcast = $this->service->send(
            message: $data['message'],
            serverIds: $data['target'] === 'all' ? null : ($data['server_ids'] ?? []),
            userId: $request->user()->id,
        );

        $count = count($broadcast->deliveries);

        return back()->with('success', trans('servercast::messages.sent', ['count' => $count]));
    }

    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        // Only removes the record from the log; any pending bridge commands keep
        // their own lifecycle and expire on their own.
        $broadcast->delete();

        return back()->with('success', trans('servercast::messages.deleted'));
    }
}
