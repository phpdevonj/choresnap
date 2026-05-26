<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserDataDeletionController extends Controller
{
    /**
     * Show deletion instructions page
     */
    public function index()
    {
        return view('user-data-deletion');
    }

    /**
     * Handle deletion request
     */
    public function request(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // OPTIONAL:
        // Here you can:
        // - find user by email
        // - soft delete user
        // - queue background deletion job

        return redirect()->back()->with(
            'success',
            'Your data deletion request has been received. We will process it within 7 working days.'
        );
    }
}
