<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('profile.index', ['user' => $request->user()]);
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $path = $request->user()->profile_photo_path;

        abort_unless($path && str_starts_with($path, 'profile-photos/'.$request->user()->id.'/') && Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $changingEmail = mb_strtolower(trim((string) $request->input('email'))) !== mb_strtolower($user->email);
        $changingPassword = filled($request->input('password'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'position_title' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [$changingEmail || $changingPassword ? 'required' : 'nullable', 'current_password'],
            'password' => ['nullable', 'string', 'min:12', 'max:200', 'confirmed'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);

        $oldPhotoPath = $user->profile_photo_path;
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $request->file('photo')->store('profile-photos/'.$user->id, 'local');
            abort_unless($newPhotoPath, 500, 'Não foi possível salvar a foto do perfil.');
        }

        $user->fill([
            'name' => trim($data['name']),
            'position_title' => trim($data['position_title']),
            'email' => mb_strtolower(trim($data['email'])),
        ]);

        if (mb_strtolower(trim($data['email'])) !== mb_strtolower($user->getOriginal('email'))) {
            $user->email_verified_at = null;
        }

        if ($changingPassword) {
            $user->password = Hash::make($data['password']);
        }

        if ($newPhotoPath) {
            $user->profile_photo_path = $newPhotoPath;
        } elseif ($request->boolean('remove_photo')) {
            $user->profile_photo_path = null;
        }

        $user->save();

        if ($oldPhotoPath && str_starts_with($oldPhotoPath, 'profile-photos/'.$user->id.'/') && ($newPhotoPath || $request->boolean('remove_photo'))) {
            Storage::disk('local')->delete($oldPhotoPath);
        }
        $request->session()->regenerate();

        return to_route('profile.index')->with('success', 'Seu perfil foi atualizado.');
    }
}
