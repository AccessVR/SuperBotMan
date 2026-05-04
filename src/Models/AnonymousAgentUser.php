<?php

namespace OrchestrateXR\SuperBotMan\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A placeholder Authenticatable used for visitors who have no host-app
 * user record. Exists only to satisfy the Laravel AI SDK's requirement
 * that agent_conversations.user_id reference a real persisted user.
 *
 * Host apps that already authenticate anonymous visitors as a real
 * App\Models\User (e.g. via a global "Anonymous User" account) will
 * never instantiate this model — their SuperBotManConfigurator's
 * agentUser() returns Auth::user() directly.
 */
class AnonymousAgentUser extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use HasUlids;

    protected $table = 'super_botman_anonymous_users';

    protected $fillable = [
        'session_token',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
