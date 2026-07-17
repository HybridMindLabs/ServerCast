<?php

namespace Hybridcore\ServerCast\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message sent to a set of game servers. The per-server delivery status
 * lives in the core bridge queue — this model only records what was sent, to
 * whom, and by whom, plus the command ids to resolve status against.
 */
class Broadcast extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'servercast_broadcasts';

    protected $fillable = [
        'message', 'target_type', 'deliveries', 'sent_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'deliveries' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return list<int> The bridge command ids this broadcast created. */
    public function commandIds(): array
    {
        return array_values(array_filter(array_map(
            fn (array $d) => $d['command_id'] ?? null,
            $this->deliveries ?? [],
        )));
    }
}
