<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    $email = $request->query('email', '');

    return redirect()->away(
        "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($email)
    );
})->name('password.reset')->middleware('guest');
