<?php

declare(strict_types=1);

use App\Broadcasting\ConversationChannel;
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
*/

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('dm.{conversation}', ConversationChannel::class);
Broadcast::channel('group.{conversation}', ConversationChannel::class);
Broadcast::channel('user.{id}', UserChannel::class);
