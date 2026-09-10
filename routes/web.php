<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverabilityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Root Redirection
Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:login');

    // Public signup is opt-in — see config/mailflow.php. The routes are still
    // registered when it is off so route('register') keeps resolving; the
    // controller returns 404 rather than leaking that the feature exists.
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:register');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Platform Routes
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Account Profile (every signed-in user manages their own — no permission needed)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/profile/{user}/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');

    // User & Role Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->middleware('permission:users.update')->name('users.toggle-active');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    });

    // SMTP Relays & Diagnostics
    Route::get('/smtp', [SmtpController::class, 'index'])->name('smtp.index')->middleware('permission:smtp.view');
    Route::post('/smtp', [SmtpController::class, 'store'])->name('smtp.store')->middleware('permission:smtp.create');
    Route::put('/smtp/{smtp}', [SmtpController::class, 'update'])->name('smtp.update')->middleware('permission:smtp.update');
    Route::delete('/smtp/{smtp}', [SmtpController::class, 'destroy'])->name('smtp.destroy')->middleware('permission:smtp.delete');
    Route::post('/smtp/{smtp}/test-send', [SmtpController::class, 'testSend'])->name('smtp.test-send')->middleware(['permission:smtp.test', 'throttle:smtp-test']);

    // Contacts & 200MB CSV Importer
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index')->middleware('permission:contacts.view');
    Route::post('/contacts/list', [ContactController::class, 'storeList'])->name('contacts.store-list')->middleware('permission:contacts.create');
    Route::get('/contacts/list/{list}', [ContactController::class, 'show'])->name('contacts.show')->middleware('permission:contacts.view');
    Route::post('/contacts/list/{list}/contact', [ContactController::class, 'storeContact'])->name('contacts.store-contact')->middleware('permission:contacts.create|contacts.update');
    Route::post('/contacts/upload-csv-preview', [ContactController::class, 'uploadCsvPreview'])->name('contacts.upload-csv-preview')->middleware(['permission:contacts.import', 'throttle:csv-upload']);
    Route::post('/contacts/process-csv-import', [ContactController::class, 'processCsvImport'])->name('contacts.process-csv-import')->middleware(['permission:contacts.import', 'throttle:csv-upload']);
    Route::get('/contacts/list/{list}/export', [ContactController::class, 'exportCsv'])->name('contacts.export-csv')->middleware('permission:contacts.export');
    Route::delete('/contacts/contact/{contact}', [ContactController::class, 'destroyContact'])->name('contacts.destroy-contact')->middleware('permission:contacts.delete');
    Route::delete('/contacts/list/{list}', [ContactController::class, 'destroyList'])->name('contacts.destroy-list')->middleware('permission:contacts.delete');

    // Email Templates & Previewer
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index')->middleware('permission:templates.view');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create')->middleware('permission:templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store')->middleware('permission:templates.create');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit')->middleware('permission:templates.update');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update')->middleware('permission:templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy')->middleware('permission:templates.delete');
    Route::post('/templates/render-live-preview', [TemplateController::class, 'renderLivePreview'])->name('templates.render-live-preview')->middleware(['permission:templates.create|templates.update', 'throttle:preview']);
    Route::post('/templates/upload-logo', [TemplateController::class, 'uploadLogo'])->name('templates.upload-logo')->middleware('permission:templates.create|templates.update');
    Route::get('/templates/logo/{filename}', [TemplateController::class, 'showLogo'])->name('templates.logo')->middleware('permission:templates.view');

    // Campaigns & Dispatcher
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index')->middleware('permission:campaigns.view');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create')->middleware('permission:campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store')->middleware('permission:campaigns.create');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show')->middleware('permission:campaigns.view');
    Route::post('/campaigns/{campaign}/launch', [CampaignController::class, 'launchCampaign'])->name('campaigns.launch')->middleware(['permission:campaigns.launch', 'throttle:campaign-launch']);
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause')->middleware('permission:campaigns.launch');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume')->middleware(['permission:campaigns.launch', 'throttle:campaign-launch']);
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel')->middleware('permission:campaigns.launch');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy')->middleware('permission:campaigns.delete');
    Route::get('/campaigns/{campaign}/status-json', [CampaignController::class, 'statusJson'])->name('campaigns.status-json')->middleware('permission:campaigns.view');

    // Anti-Spam & Deliverability Inspector
    Route::get('/deliverability', [DeliverabilityController::class, 'index'])->name('deliverability.index')->middleware(['permission:deliverability.view', 'throttle:deliverability']);
    Route::post('/deliverability/check-spam-score', [DeliverabilityController::class, 'checkSpamScore'])->name('deliverability.check-spam-score')->middleware(['permission:deliverability.view', 'throttle:deliverability']);
});

// Public Tracking & Unsubscribe Endpoints
Route::middleware('throttle:tracking')->group(function () {
    Route::get('/track/open/{token}.png', [TrackingController::class, 'open'])->name('track.open');
    Route::get('/track/click/{token}', [TrackingController::class, 'click'])->name('track.click');
});

// GET only confirms — link scanners and browser prefetch fire GETs, and a GET
// that suppressed the recipient would unsubscribe people who never clicked.
// POST is the acting route, and is what RFC 8058 one-click actually calls.
Route::middleware('throttle:unsubscribe')->group(function () {
    Route::get('/unsubscribe/{token}', [TrackingController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/unsubscribe/{token}', [TrackingController::class, 'unsubscribePost'])->name('unsubscribe.confirm');
});
