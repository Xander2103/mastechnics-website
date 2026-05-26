<?php
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\PageController;
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

Route::get('/admin/requests', [AdminRequestController::class, 'index'])
    ->name('admin.requests.index');

Route::get('/admin/requests/{customerRequest}', [AdminRequestController::class, 'show'])
    ->name('admin.requests.show');

Route::patch('/admin/requests/{customerRequest}/status', [AdminRequestController::class, 'updateStatus'])
    ->name('admin.requests.update-status');