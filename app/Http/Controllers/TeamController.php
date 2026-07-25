<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $users = $request->user()->company->users()->orderBy('email')->get();

        return view('team.index', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,member'],
        ]);

        $temporaryPassword = Str::password(12);

        $request->user()->company->users()->create([
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => $validated['role'],
        ]);

        return redirect()->route('team.index')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_email', $validated['email']);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 403);
        abort_if($user->isOwner(), 403, 'You cannot remove the account owner.');

        $user->delete();

        return redirect()->route('team.index');
    }
}
