<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\PlaceCallController;
use App\Http\Controllers\Api\V1\SearchContactsController;
use Illuminate\Support\Facades\Route;

Route::prefix('contacts')->name('api.v1.contacts.')->group(function (): void {
    Route::get('/', [ContactController::class, 'index'])->name('index');
    Route::post('/', [ContactController::class, 'store'])->name('store');
    Route::get('search', SearchContactsController::class)->name('search');
    Route::get('{contact}', [ContactController::class, 'show'])->whereNumber('contact')->name('show');
    Route::put('{contact}', [ContactController::class, 'update'])->whereNumber('contact')->name('update');
    Route::delete('{contact}', [ContactController::class, 'destroy'])->whereNumber('contact')->name('destroy');
    Route::post('{contact}/call', PlaceCallController::class)
        ->whereNumber('contact')
        ->middleware('throttle:10,1')
        ->name('call');
});
