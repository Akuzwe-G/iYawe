<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardSwapController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');
Route::post('/language', [AuthController::class, 'locale'])->middleware('throttle:30,1')->name('locale');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:20,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'emailReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth', 'company.context'])->group(function () {

    Route::get('/swaps/create', [CardSwapController::class, 'create'])->name('swaps.create');
    Route::post('/swaps', [CardSwapController::class, 'store'])->middleware('throttle:30,1')->name('swaps.store');
    Route::post('/records/search', [CardSwapController::class, 'search'])->middleware('throttle:30,1')->name('swaps.search');
    Route::get('/records', [CardSwapController::class, 'index'])->name('swaps.index');
    Route::get('/swaps/{swap}', [CardSwapController::class, 'show'])->whereNumber('swap')->name('swaps.show');
    Route::get('/swaps/{swap}/continue', [CardSwapController::class, 'edit'])->whereNumber('swap')->name('swaps.edit');
    Route::post('/swaps/{swap}/steps/{step}', [CardSwapController::class, 'advance'])->whereNumber('swap')->where('step', '[1-5]')->middleware('throttle:30,1')->name('swaps.advance');
    Route::post('/swaps/{swap}/back', [CardSwapController::class, 'back'])->whereNumber('swap')->name('swaps.back');
    Route::get('/swaps/{swap}/signatures/{signature}', [CardSwapController::class, 'signature'])->whereNumber(['swap', 'signature'])->name('swaps.signature');
    Route::get('/swaps/{swap}/pdf', [CardSwapController::class, 'pdf'])->whereNumber('swap')->name('swaps.pdf');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('throttle:5,1')->name('reports.export');
    Route::get('/consents', [CardSwapController::class, 'consents'])->name('consents.index');
    Route::post('/consents', [CardSwapController::class, 'publishConsent'])->name('consents.publish');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::post('/company/switch', [WorkspaceController::class, 'switchCompany'])->name('company.switch');
    Route::post('/invitations/{company}/accept', [WorkspaceController::class, 'acceptInvitation'])->whereNumber('company')->name('invitations.accept');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'form'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'save'])->name('companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'form'])->whereNumber('company')->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'save'])->whereNumber('company')->name('companies.update');
    Route::post('/companies/{company}/administrator', [CompanyController::class, 'administrator'])->whereNumber('company')->name('companies.administrator');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffController::class, 'form'])->name('staff.create');
    Route::post('/staff', [StaffController::class, 'save'])->name('staff.store');
    Route::get('/staff/{user}/edit', [StaffController::class, 'form'])->whereNumber('user')->name('staff.edit');
    Route::put('/staff/{user}', [StaffController::class, 'save'])->whereNumber('user')->name('staff.update');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'form'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'save'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'form'])->whereNumber('role')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'save'])->whereNumber('role')->name('roles.update');
    Route::get('/locations', [WorkspaceController::class, 'locations'])->name('locations.index');
    Route::post('/locations', [WorkspaceController::class, 'saveLocation'])->name('locations.store');
    Route::put('/locations/{location}', [WorkspaceController::class, 'saveLocation'])->whereNumber('location')->name('locations.update');
    Route::get('/settings', [WorkspaceController::class, 'settings'])->name('settings');
    Route::put('/settings', [WorkspaceController::class, 'saveSettings'])->name('settings.update');
    Route::put('/settings/platform', [WorkspaceController::class, 'savePlatformSettings'])->name('settings.platform');
    Route::put('/settings/company', [WorkspaceController::class, 'saveCompanySettings'])->name('settings.company');
});
