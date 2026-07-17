<?php

namespace Hybridcore\ServerCast;

use App\Models\ServerCommand;
use App\Services\Extensions\Registries\ExtensionRegistry;
use Hybridcore\ServerCast\Services\ServerCastService;
use Illuminate\Support\ServiceProvider;

class ServerCastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ServerCastService::class);
    }

    public function boot(ExtensionRegistry $registry): void
    {
        // ── Permissions ───────────────────────────────────────────────
        $registry->permissions()->register('servercast.view', 'View ServerCast', 'servercast');
        $registry->permissions()->register('servercast.send', 'Send broadcasts', 'servercast');

        // ── Admin navigation ──────────────────────────────────────────
        $registry->navigation()->register(
            label: 'ServerCast',
            route: 'admin.servercast.index',
            icon: 'Radio',
            section: 'Servers',
            permission: 'servercast.view',
            sort: 60,
        );

        // ── Dashboard widget: broadcasts sent today ───────────────────
        $registry->widgets()->register(
            id: 'servercast.today',
            title: 'Broadcasts Today',
            component: 'stat',
            data: fn () => [
                'value' => Models\Broadcast::whereDate('created_at', today())->count(),
                'icon' => 'Radio',
                'label' => 'sent today',
                'accent' => 'blue',
            ],
            permission: 'servercast.view',
            sort: 20,
        );

        // ── Quick action: jump straight to composing a broadcast ──────
        $registry->quickActions()->register(
            label: 'New broadcast',
            route: 'admin.servercast.index',
            icon: 'Radio',
            permission: 'servercast.send',
        );

        // Referenced so the model is autoloaded where the widget closure runs.
        class_exists(ServerCommand::class);
    }
}
