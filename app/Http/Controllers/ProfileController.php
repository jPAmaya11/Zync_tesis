<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\User\Models\User;


class ProfileController extends Controller
{
    /**
     * Handle avatar upload and deletion
     */
    private function handleAvatar(Request $request, User $user): ?string
    {
        if (!$request->hasFile('avatar')) {
            return null;
        }

        // Eliminar avatar anterior si existe
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Guardar nuevo avatar usando Laravel estándar
        return $request->file('avatar')->store('avatars', 'public');
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $user->load('roles');

        return Inertia::render('Profile/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'numero_documento' => $user->numero_documento,
                'avatar' => $user->avatar,
                'phone' => $user->phone,
                'position' => $user->position,
                'department' => $user->department,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'roles' => $user->roles,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Si solo se está subiendo un avatar
        if ($request->hasFile('avatar') && !$request->has(['name', 'email'])) {
            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            $avatarPath = $this->handleAvatar($request, $user);
            if ($avatarPath) {
                $user->update(['avatar' => $avatarPath]);
            }

            return back()->with('success', 'Avatar actualizado correctamente');
        }        // Validación para actualización completa del perfil
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'numero_documento' => 'nullable|string|max:255|unique:users,numero_documento,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $data = $request->only([
            'name',
            'email',
            'numero_documento',
            'phone',
            'position',
            'department',
        ]);

        // Manejar subida de avatar
        $avatarPath = $this->handleAvatar($request, $user);
        if ($avatarPath) {
            $data['avatar'] = $avatarPath;
        }

        $emailCambio = $request->email !== $user->email;

        $user->update($data);

        // email_verified_at no es asignable en masa: update() lo descartaría en silencio.
        if ($emailCambio) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        return back()->with('success', 'Perfil actualizado correctamente');
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $avatarPath = $this->handleAvatar($request, $user);
        if ($avatarPath) {
            $user->update(['avatar' => $avatarPath]);
        }

        return back()->with('success', 'Avatar actualizado correctamente');
    }
    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = $request->user();

        if (!$user) {
            abort(403, 'Usuario no autenticado');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('status', 'password-updated');
    }




    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
