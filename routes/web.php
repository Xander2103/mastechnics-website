<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\CustomerRequestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/nl');

Route::get('/{locale}', [PageController::class, 'home'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('pages.home');

Route::get('/{locale}/{slug}', [PageController::class, 'show'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('pages.show');

Route::post('/{locale}/requests', [CustomerRequestController::class, 'store'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('customer-requests.store');