<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment)
    {
        Gate::authorize('view', $attachment->message);
        return Storage::disk('local')->response($attachment->file_path);
    }

    public function thumbnail(Request $request, Attachment $attachment)
    {
        Gate::authorize('view', $attachment->message);
        abort_unless($attachment->thumbnail_path, 404);
        return Storage::disk('local')->response($attachment->thumbnail_path);
    }
}
