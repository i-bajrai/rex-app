<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/contacts');

Route::view('/contacts', 'app')->name('contacts.spa');
Route::view('/contacts/{any?}', 'app')->where('any', '.*');
