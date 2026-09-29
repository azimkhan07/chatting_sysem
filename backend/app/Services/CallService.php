<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Chat\Contracts\CallService as CallServiceContract;
use App\Domain\Chat\Contracts\ChatRepository;
use App\Domain\Chat\Exceptions\ConversationNotFoundException;
use App\Domain\Chat\Exceptions\ConversationPermissionException;
use App\Domain\Chat\Models\Call;
use App\Domain\Chat\Models\CallParticipant;
use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Services\LiveKitTokenIssuer;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallOffered;
use App\Events\CallRejected;
use Illuminate\Support\Facades\DB;

final class CallService implements CallServiceContract
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly LiveKitTokenIssuer $liveKit,
        private readonly string $serverUrl,
    ) {}

    public function start(User $user, int $conversationId, string $kind): array
    {
        $conversation = $this->resolveConversation($user, $conversationId);

        $call = DB::transaction(function () use ($conversation, $user, $kind): Call {
            $call = Call::query()->create([
                'conversation_id' => $conversation->id,
                'initiator_id' => $user->id,
                'kind' => $kind,
                'status' => 'ringing',
            ]);

            // Offer every member (including the initiator) a participation slot.
            $memberIds = $conversation->members->pluck('user_id');
            CallParticipant::query()->insert(
                $memberIds->map(fn (int $memberId): array => [
                    'call_id' => $call->id,
                    'user_id' => $memberId,
                    'status' => $memberId === (int) $user->id ? 'joined' : 'ringing',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
            );

            return $call;
        });

        $room = $this->roomName($call->id);
        $token = $this->liveKit->issue((int) $user->id, $user->display_name, $this->liveKit->roomGrants($room));

        $call = $call->fresh();

        /** @var list<int> $recipientIds */
        $recipientIds = $conversation->members->pluck('user_id')->map(
            static fn ($memberId): int => (int) $memberId,
        )->all();

        CallOffered::dispatch(
            (int) $conversation->id,
            (int) $call->id,
            $conversation->type,
            $this->callerSummary($user),
            $kind,
            $room,
            $this->serverUrl,
            $recipientIds,
        );

        return [
            'call' => $call,
            'token' => $token,
            'room' => $room,
            'server_url' => $this->serverUrl,
        ];
    }

    public function answer(User $user, int $conversationId, int $callId): array
    {
        $conversation = $this->resolveConversation($user, $conversationId);
        $call = $this->resolveRingingCall($conversation, $callId);

        DB::transaction(function () use ($call, $user): void {
            if ($call->status === 'ringing') {
                $call->update(['status' => 'active', 'answered_at' => now()]);
            }

            CallParticipant::query()->updateOrCreate(
                ['call_id' => $call->id, 'user_id' => $user->id],
                ['status' => 'joined', 'joined_at' => now()],
            );
        });

        $room = $this->roomName($call->id);
        $token = $this->liveKit->issue((int) $user->id, $user->display_name, $this->liveKit->roomGrants($room));

        CallAccepted::dispatch(
            (int) $conversation->id,
            (int) $call->id,
            $conversation->type,
            $this->callerSummary($user),
            $room,
            (int) $call->initiator_id,
        );

        return [
            'call' => $call->fresh(),
            'token' => $token,
            'room' => $room,
            'server_url' => $this->serverUrl,
        ];
    }

    public function reject(User $user, int $conversationId, int $callId): void
    {
        $conversation = $this->resolveConversation($user, $conversationId);
        $call = $this->resolveRingingCall($conversation, $callId);

        CallParticipant::query()->updateOrCreate(
            ['call_id' => $call->id, 'user_id' => $user->id],
            ['status' => 'declined', 'left_at' => now()],
        );

        CallRejected::dispatch(
            (int) $conversation->id,
            (int) $call->id,
            $conversation->type,
            (int) $user->id,
            (int) $call->initiator_id,
        );
    }

    public function end(User $user, int $conversationId, int $callId): void
    {
        $conversation = $this->resolveConversation($user, $conversationId);

        $call = Call::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', $callId)
            ->first();

        if ($call === null) {
            return;
        }

        DB::transaction(function () use ($call, $user): void {
            CallParticipant::query()->updateOrCreate(
                ['call_id' => $call->id, 'user_id' => $user->id],
                ['left_at' => now()],
            );

            $activeParticipants = CallParticipant::query()
                ->where('call_id', $call->id)
                ->whereNull('left_at')
                ->count();

            if ($activeParticipants <= 1) {
                $call->update(['status' => 'ended', 'ended_at' => now()]);
            }
        });

        $call->refresh();

        CallEnded::dispatch(
            (int) $conversation->id,
            (int) $call->id,
            $conversation->type,
            (int) $user->id,
            (int) $call->initiator_id,
        );
    }

    private function resolveConversation(User $user, int $conversationId): Conversation
    {
        $conversation = $this->chatRepository->conversationForUser((int) $user->id, $conversationId);

        if ($conversation === null) {
            throw new ConversationNotFoundException;
        }

        return $conversation;
    }

    private function resolveRingingCall(Conversation $conversation, int $callId): Call
    {
        $call = Call::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', $callId)
            ->where('status', 'ringing')
            ->first();

        if ($call === null) {
            throw new ConversationPermissionException('This call is not accepting answers.');
        }

        return $call;
    }

    private function roomName(int $callId): string
    {
        return 'call-'.$callId;
    }

    /**
     * @return array{id: int, username: string, display_name: string, avatar_url: ?string}
     */
    private function callerSummary(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_path !== null
                ? url('storage/'.$user->avatar_path)
                : null,
        ];
    }
}
