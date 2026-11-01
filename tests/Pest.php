<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/


/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

// $admin becomes the admin; $members are plain members.
function makeConversation(User $admin, array $members = [], string $type = 'group'): Conversation
{
    $conversation = Conversation::create([
        'type' => $type,
        'name' => $type === 'group' ? 'Test group' : null,
        'created_by' => $admin->id,
    ]);

    $conversation->participants()->create(['user_id' => $admin->id, 'role' => 'admin']);

    foreach ($members as $member) {
        $conversation->participants()->create(['user_id' => $member->id, 'role' => 'member']);
    }

    return $conversation;
}

function makeMessage(Conversation $conversation, User $sender, array $attributes = []): Message
{
    // Read pointers compare UUIDv7 ids, so no two messages may share a millisecond.
    usleep(2000);

    return $conversation->messages()->create(array_merge([
        'user_id' => $sender->id,
        'type' => 'text',
        'body' => 'hello',
    ], $attributes));
}

function participantOf(Conversation $conversation, User $user): Participant
{
    return Participant::where('conversation_id', $conversation->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
}

function setReadPointer(Conversation $conversation, User $user, ?Message $message): void
{
    Participant::where('conversation_id', $conversation->id)
        ->where('user_id', $user->id)
        ->update(['last_read_message_id' => $message?->id]);
}

function userChannel(User $user): string
{
    return (string) new PrivateChannel("user.{$user->id}");
}

function channelNames(array $channels): array
{
    return array_map(fn ($channel) => (string) $channel, $channels);
}