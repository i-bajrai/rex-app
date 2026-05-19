<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): View => view('welcome'));

Route::view('/contacts', 'app')->name('contacts.spa');
Route::view('/contacts/{any?}', 'app')->where('any', '.*');
