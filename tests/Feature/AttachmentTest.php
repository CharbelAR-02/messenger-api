<?php

use App\Events\MessageSent;
use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Storage::fake('local'));

// Builds an attachment without any HTTP call, so access tests need only one acting user.
function makeAttachment(Message $message, bool $withThumbnail = true): Attachment
{
    $path = "conversations/{$message->conversation_id}/{$message->id}.jpg";
    Storage::disk('local')->put($path, 'file-bytes');

    $thumbnail = null;
    if ($withThumbnail) {
        $thumbnail = "conversations/{$message->conversation_id}/thumb_{$message->id}.jpg";
        Storage::disk('local')->put($thumbnail, 'thumb-bytes');
    }

    return $message->attachments()->create([
        'file_path' => $path,
        'thumbnail_path' => $thumbnail,
        'file_type' => 'image/jpeg',
        'file_size' => 10,
    ]);
}

it('stores an uploaded image with a thumbnail and exposes only signed URLs', function () {
    Event::fake([MessageSent::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');

    $response = $this->post("/api/conversations/{$conversation->id}/messages", [
        'type' => 'image',
        'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('type', 'image')
        ->assertJsonCount(1, 'attachments')
        ->assertJsonMissingPath('attachments.0.file_path')
        ->assertJsonMissingPath('attachments.0.thumbnail_path');

    expect($response->json('attachments.0.url'))->toContain('signature=')
        ->and($response->json('attachments.0.thumbnail_url'))->toContain('signature=');

    $attachment = Attachment::firstOrFail();
    Storage::disk('local')->assertExists([$attachment->file_path, $attachment->thumbnail_path]);
});

it('stores a document without a thumbnail', function () {
    Event::fake([MessageSent::class]);

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->post("/api/conversations/{$conversation->id}/messages", [
        'type' => 'file',
        'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('attachments.0.thumbnail_url', null);

    expect(Attachment::firstOrFail()->thumbnail_path)->toBeNull();
});

it('rejects a file on a text message', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->post("/api/conversations/{$conversation->id}/messages", [
        'type' => 'text',
        'body' => 'hi',
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('requires a file for image messages', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->post("/api/conversations/{$conversation->id}/messages", ['type' => 'image'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});


it('rejects a non-image file sent as an image', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->post("/api/conversations/{$conversation->id}/messages", [
        'type' => 'image',
        'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});


it('does not allow a caption on voice messages', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->post("/api/conversations/{$conversation->id}/messages", [
        'type' => 'voice',
        'body' => 'not allowed',
        'file' => UploadedFile::fake()->create('note.mp3', 50, 'audio/mpeg'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('serves the file and thumbnail to a member through signed URLs', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));

    $this->actingAs($member, 'web');

    $this->getJson($attachment->url)->assertOk();
    $this->getJson($attachment->thumbnail_url)->assertOk();
});

it('returns 404 for a thumbnail that does not exist', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'file', 'body' => null]), false);
    $url = URL::temporarySignedRoute('attachments.thumbnail', now()->addMinutes(5), ['attachment' => $attachment->id]);

    $this->actingAs($admin, 'web');

    $this->getJson($url)->assertNotFound();
});

it('forbids non-members even with a valid signature', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));
    $url = $attachment->url;

    $this->actingAs(makeUser(), 'web');

    $this->getJson($url)->assertForbidden();
});

it('stops serving a file to someone who left, even with a link generated earlier', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));
    $url = $attachment->url;
    participantOf($conversation, $member)->forceFill(['left_at' => now()])->save();

    $this->actingAs($member, 'web');

    $this->getJson($url)->assertForbidden();
});

it('rejects a tampered signature', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));

    $this->actingAs($admin, 'web');

    $this->getJson($attachment->url.'0')->assertForbidden();
});

it('rejects an expired link', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));
    $url = $attachment->url;

    $this->travel(31)->minutes();
    $this->actingAs($admin, 'web');

    $this->getJson($url)->assertForbidden();
});

it('requires authentication to fetch a file', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $attachment = makeAttachment(makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]));

    $this->getJson($attachment->url)->assertUnauthorized();
});