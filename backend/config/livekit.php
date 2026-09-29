<?php

declare(strict_types=1);

/*
| LiveKit - self-hosted WebRTC SFU for voice/video calls.
| Values mirror docker/compose.yaml -> livekit and docker/livekit/livekit.yaml.
*/

return [
    'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
    'api_secret' => env('LIVEKIT_API_SECRET', 'devsecret'),
    'url' => env('LIVEKIT_URL', 'ws://127.0.0.1:7880'),
];
