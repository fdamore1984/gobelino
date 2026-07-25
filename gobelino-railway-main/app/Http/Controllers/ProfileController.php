<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Mostra la pagina del profilo
     */
    public function show()
    {
        return view('profile.show');
    }

    /**
     * Aggiorna i dati del profilo (email)
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email,' . auth()->id(),
        ]);

        auth()->user()->update($validated);

        return redirect()->route('profile.show')
            ->with('success', 'Email aggiornata con successo.');
    }

    /**
     * Cambia la password
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
            ->with('success', 'Password cambiata con successo.');
    }

    /**
     * Elimina l'account (solo se owner)
     */
    public function deleteAccount(Request $request)
    {
        // Solo l'owner può eliminare l'account
        if (!auth()->user()->isOwner()) {
            return redirect()->route('profile.show')
                ->with('error', 'Non hai i permessi per eliminare l\'account.');
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = auth()->user();

        // Logout
        auth()->logout();

        // Eliminazione dell'utente e della sua azienda
        $company = $user->company;
        $user->delete();
        $company->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Account eliminato con successo.');
    }
}
