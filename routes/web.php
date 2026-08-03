<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BlockedEmailController as AdminBlockedEmailController;
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerRequestController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])
    ->name('sitemap');

// Permanent (301), not the framework default 302: the root has permanently
// moved to the default locale, and only a 301 consolidates link equity onto
// /nl instead of leaving both URLs alive in Google's index.
Route::permanentRedirect('/', '/nl');

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.submit');

Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->name('admin.logout');

Route::middleware('admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/requests/export', [AdminRequestController::class, 'exportCsv'])
            ->name('requests.export');

        Route::get('/requests', [AdminRequestController::class, 'index'])
            ->name('requests.index');

        Route::get('/requests/{customerRequest}', [AdminRequestController::class, 'show'])
            ->name('requests.show');

        Route::patch('/requests/{customerRequest}/status', [AdminRequestController::class, 'updateStatus'])
            ->name('requests.update-status');

        Route::post('/requests/{customerRequest}/notes', [AdminRequestController::class, 'storeNote'])
            ->name('requests.notes.store');

        Route::patch('/requests/{customerRequest}/notes/{note}', [AdminRequestController::class, 'updateNote'])
            ->name('requests.notes.update');

        Route::delete('/requests/{customerRequest}/notes/{note}', [AdminRequestController::class, 'destroyNote'])
            ->name('requests.notes.destroy');

        Route::post('/requests/{customerRequest}/appointments', [AdminRequestController::class, 'storeAppointment'])
            ->name('requests.appointments.store');

        Route::post('/requests/{customerRequest}/action', [AdminRequestController::class, 'performAction'])
            ->name('requests.action');

        Route::post('/requests/{customerRequest}/standard-reply', [AdminRequestController::class, 'sendStandardReply'])
            ->name('requests.standard-reply.send');

        Route::post('/requests/{customerRequest}/standard-reply/resend', [AdminRequestController::class, 'resendStandardReply'])
            ->name('requests.standard-reply.resend');

        Route::delete('/requests/{customerRequest}', [AdminRequestController::class, 'destroy'])
            ->name('requests.destroy');

        Route::patch('/requests/{customerRequest}/internal-notes', [AdminRequestController::class, 'updateInternalNotes'])
            ->name('requests.internal-notes.update');

        Route::get('/requests/{customerRequest}/attachments/{attachment}', [AdminRequestController::class, 'downloadAttachment'])
            ->name('requests.attachments.download');

        Route::get('/requests/{customerRequest}/quote/edit', [AdminQuoteController::class, 'edit'])
            ->name('requests.quote.edit');

        Route::post('/requests/{customerRequest}/quote', [AdminQuoteController::class, 'store'])
            ->name('requests.quote.store');

        Route::post('/requests/{customerRequest}/quote/action', [AdminQuoteController::class, 'performAction'])
            ->name('requests.quote.action');

        Route::get('/requests/{customerRequest}/quote/pdf', [AdminQuoteController::class, 'pdf'])
            ->name('requests.quote.pdf');

        Route::post('/requests/{customerRequest}/quote/send-email', [AdminQuoteController::class, 'sendEmail'])
            ->name('requests.quote.send-email');

        Route::get('/blocked-emails', [AdminBlockedEmailController::class, 'index'])
            ->name('blocked-emails.index');

        Route::post('/blocked-emails', [AdminBlockedEmailController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('blocked-emails.store');

        Route::patch('/blocked-emails/{blockedEmail}/unblock', [AdminBlockedEmailController::class, 'unblock'])
            ->name('blocked-emails.unblock');

        Route::get('/account', [AdminAccountController::class, 'edit'])
            ->name('account.edit');

        Route::patch('/account/email', [AdminAccountController::class, 'updateEmail'])
            ->middleware('throttle:10,1')
            ->name('account.email.update');

        Route::patch('/account/password', [AdminAccountController::class, 'updatePassword'])
            ->middleware('throttle:10,1')
            ->name('account.password.update');
    });

Route::post('/{locale}/requests', [CustomerRequestController::class, 'store'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('customer-requests.store');

Route::post('/{locale}/contact', [ContactController::class, 'store'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('contact.store');

Route::get('/{locale}', [PageController::class, 'home'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('pages.home');

Route::get('/{locale}/{slug}', [PageController::class, 'show'])
    ->whereIn('locale', ['nl', 'fr', 'en'])
    ->name('pages.show');