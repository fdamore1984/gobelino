<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Shows the profile page
     */
    public function show()
    {
        return view('profile.show');
    }

    /**
     * Updates the profile data (email)
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email,' . auth()->id(),
        ]);

        auth()->user()->update($validated);

        return redirect()->route('profile.show')
            ->with('success', 'Email updated successfully.');
    }

    /**
     * Changes the password
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|confirmed|min:8',
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Deletes the account (owner only)
     */
    public function deleteAccount(Request $request)
    {
        // Only the owner can delete the account
        if (!auth()->user()->isOwner()) {
            return redirect()->route('profile.show')
                ->with('error', 'You do not have permission to delete the account.');
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = auth()->user();

        // Log out
        auth()->logout();

        // Delete the user and their company
        $company = $user->company;
        $user->delete();
        $company->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Account deleted successfully.');
    }
}
