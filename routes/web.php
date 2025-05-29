<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/widget/chat.js', function () {
    return response()->file(public_path('js/chat-widget.js'), [
        'Content-Type' => 'application/javascript',
    ]);
});
