<?php

use App\Http\Controllers\Admin\AccountRequestAccountController;
use App\Http\Controllers\Admin\AccountRequestController as AdminAccountRequestController;
use App\Http\Controllers\Auth\AccountRequestController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Core\FamilyController;
use App\Http\Controllers\Core\HomeController;
use App\Http\Controllers\Core\MemberController;
use App\Http\Controllers\Garage\GarageController;
use App\Http\Controllers\Garage\ToolController;
use App\Http\Controllers\Garage\ToolShelfController;
use App\Http\Controllers\Garage\ToolShelfPositionController;
use App\Http\Controllers\Library\BookController;
use App\Http\Controllers\Library\BookshelfController;
use App\Http\Controllers\Library\BookshelfPositionController;
use App\Http\Controllers\Library\GoogleBooksLookupController;
use App\Http\Controllers\Library\LibraryController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

// Authentication: Fortify controllers, routes declared here (Fortify::ignoreRoutes())
// so that only the needed ones exist, each with its rate limiter.
Route::middleware('guest')->group(function () {
    Route::controller(AuthPageController::class)->group(function () {
        Route::get('/login', 'login')->name('login');
        Route::get('/forgot-password', 'forgotPassword')->name('password.request');
        Route::get('/reset-password/{token}', 'resetPassword')->name('password.reset');
    });

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset-link')
        ->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    // Signed link of the welcome email (SetPasswordNotification).
    Route::middleware('invitation')->controller(InvitationController::class)->group(function () {
        Route::get('/invitation/{user}', 'show')->name('invitation.show');
        Route::post('/invitation/{user}', 'store')
            ->middleware('throttle:password-reset')
            ->name('invitation.store');
    });

    Route::controller(AccountRequestController::class)->group(function () {
        Route::get('/account-request', 'create')->name('account-request.create');
        Route::post('/account-request', 'store')
            ->middleware('throttle:account-request')
            ->name('account-request.store');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', HomeController::class)->name('home');

    Route::prefix('library')->name('library.')->group(function () {
        Route::get('/', LibraryController::class)->name('index');

        Route::resource('bookshelves', BookshelfController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/bookshelves/{bookshelf}/move', [BookshelfPositionController::class, 'update'])
            ->name('bookshelves.move');

        Route::resource('books', BookController::class);
        Route::get('/google-books', GoogleBooksLookupController::class)
            ->middleware('throttle:google-books')
            ->name('google-books.lookup');
    });

    Route::prefix('garage')->name('garage.')->group(function () {
        Route::get('/', GarageController::class)->name('index');

        Route::resource('shelves', ToolShelfController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/shelves/{shelf}/move', [ToolShelfPositionController::class, 'update'])
            ->name('shelves.move');

        Route::resource('tools', ToolController::class);
    });

    // Read-only pages of the family: every member sees the others' library and garage.
    Route::prefix('family')->name('family.')->group(function () {
        Route::get('/', FamilyController::class)->name('index');
        Route::get('/{member}', MemberController::class)->name('members.show');
    });
});

Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/account-requests', [AdminAccountRequestController::class, 'index'])
        ->name('account-requests.index');
    Route::post('/account-requests/{accountRequest}/reject', [AdminAccountRequestController::class, 'reject'])
        ->name('account-requests.reject');

    Route::controller(AccountRequestAccountController::class)->group(function () {
        Route::get('/account-requests/{accountRequest}/account', 'create')->name('account-requests.account.create');
        Route::post('/account-requests/{accountRequest}/account', 'store')->name('account-requests.account.store');
    });
});
