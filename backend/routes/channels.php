<?php

declare(strict_types=1);

use App\Broadcasting\ConversationChannel;
use App\Broadcasting\ConversationPresenceChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Token-based (Sanctum) channel authorization. Membership is enforced on the
| server for every private conversation channel - the channel name alone is
| never enough to listen in.
|
| The `presence-` variants are the same membership check on a presence
| channel: the server answers with the joining member's identity so the
| client can list who is actually in the room.
|
*/

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('dm.{conversation}', ConversationChannel::class);
Broadcast::channel('group.{conversation}', ConversationChannel::class);
Broadcast::channel('user.{id}', UserChannel::class);
Broadcast::channel('presence-dm.{conversation}', ConversationPresenceChannel::class);
Broadcast::channel('presence-group.{conversation}', ConversationPresenceChannel::class);
