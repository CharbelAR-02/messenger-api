<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationReadController;
use App\Http\Controllers\ConversationTypingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    Route::get('/conversations/search', [ConversationController::class, 'search']);
    Route::apiResource('conversations', ConversationController::class)->except(['destroy']);
    Route::post('/conversations/{conversation}/participants', [ConversationController::class, 'addParticipant']);
    Route::delete('/conversations/{conversation}/participants/{user}', [ConversationController::class, 'removeParticipant']);
    Route::delete('/conversations/{conversation}/avatar', [ConversationController::class, 'deleteAvatar']);

    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:send-message');
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::patch('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);
    Route::post('/messages/{message}/hide', [MessageController::class, 'hide']);

    Route::middleware('signed')->group(function () {
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('/attachments/{attachment}/thumbnail', [AttachmentController::class, 'thumbnail'])->name('attachments.thumbnail');
    });

    Route::post('/messages/{message}/reactions', [ReactionController::class, 'toggle']);

    Route::post('/conversations/{conversation}/read', ConversationReadController::class);

    Route::post('/conversations/{conversation}/typing', ConversationTypingController::class)
        ->middleware('throttle:typing');

    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->middleware('throttle:presence');
});
