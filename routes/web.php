<?php

use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\ChirpController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChirpReactionController;
use App\Http\Controllers\CommentController;

Route::get('/', [ChirpController::class, 'index']);

// Protected routes
Route::middleware('auth')->group(function () {
    Route::post('/chirps', [ChirpController::class, 'store']);
    Route::get('/chirps/{chirp}/edit', [ChirpController::class, 'edit']);
    Route::put('/chirps/{chirp}', [ChirpController::class, 'update']);
    Route::delete('/chirps/{chirp}', [ChirpController::class, 'destroy']);
});

// Registration routes
Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');
Route::post('/register', Register::class)
    ->middleware('guest');

// Login routes
Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');
Route::post('/login', Login::class)
    ->middleware('guest');

// Logout route
Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth'])->group(function () {
    // Chirp reactions (like/dislike)
    Route::post('/chirps/{chirp}/react', [ChirpReactionController::class, 'react'])->name('chirps.react');
    
    // Add comment to chirp
    Route::post('/chirps/{chirp}/comment', [ChirpReactionController::class, 'addComment'])->name('chirps.comment');
    
    // Get all comments for a chirp (for "View all comments" button)
    Route::get('/chirps/{chirp}/comments', [ChirpReactionController::class, 'getComments'])->name('chirps.comments.index');
    
    // Like a comment (you'll need to create this method)
    Route::post('/comments/{comment}/like', [CommentController::class, 'toggleLike'])->name('comments.like');
});
