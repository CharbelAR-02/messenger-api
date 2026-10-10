<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{

    private function storeAvatar($file): string
    {
        $manager = ImageManager::usingDriver(Driver::class);
        $resized = $manager->decodeSplFileInfo($file)
            ->scaleDown(width: 500, height: 500)
            ->encodeUsingFormat(Format::JPEG, quality: 80);

        $filename = 'avatars/' . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, (string) $resized);

        return $filename;
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldAvatarPath = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            // Store the new avatar first.
            $newAvatarPath = $this->storeAvatar($request->file('avatar'));

            // Only update the database after the new file was stored successfully.
            $user->avatar_path = $newAvatarPath;
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        $user->save();

        // Only delete the old file after the new avatar has been saved successfully.
        if ($request->hasFile('avatar') && $oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return response()->json($user);
    }

      public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json($user);
    }
}
