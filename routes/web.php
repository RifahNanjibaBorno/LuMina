<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepositoryController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/repository', [RepositoryController::class, 'index'])->middleware(EnsureAccountIsActive::class)->name('repository.index');
Route::get('/repository/{item}', [RepositoryController::class, 'show'])->middleware(EnsureAccountIsActive::class)->whereNumber('item')->name('items.show');
Route::get('/p/{slug}/avatar', [PortfolioController::class, 'avatar'])->name('portfolio.avatar');
Route::get('/p/{slug}', [PortfolioController::class, 'show'])->name('portfolio.public');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', EnsureAccountIsActive::class])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/google-analytics', [AdminController::class, 'analytics'])->middleware('can:admin')->name('admin.analytics');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/projects', [RepositoryController::class, 'index'])->name('projects.index');
    Route::get('/uploads/create', [RepositoryController::class, 'create'])->name('items.create');
    Route::post('/uploads', [RepositoryController::class, 'store'])->name('items.store');
    Route::get('/uploads/{item}/edit', [RepositoryController::class, 'edit'])->name('items.edit');
    Route::put('/uploads/{item}', [RepositoryController::class, 'update'])->name('items.update');
    Route::delete('/uploads/{item}', [RepositoryController::class, 'destroy'])->name('items.destroy');
    Route::get('/uploads/{item}/download', [RepositoryController::class, 'download'])->name('items.download');
    Route::middleware('can:student')->group(function (): void {
        Route::get('/portfolio', [PortfolioController::class, 'edit'])->name('portfolio.edit');
        Route::put('/portfolio', [PortfolioController::class, 'update'])->name('portfolio.update');
        Route::post('/portfolio/resume', [PortfolioController::class, 'import'])->middleware('throttle:10,1')->name('portfolio.import');
        Route::get('/portfolio/preview', [PortfolioController::class, 'preview'])->name('portfolio.preview');
    });
    Route::middleware('can:staff')->group(function (): void {
        Route::resource('courses', CourseController::class);
        Route::post('/course-assignments', [CourseController::class, 'assign'])->name('courses.assign');
        Route::put('/courses/{course}/students', [CourseController::class, 'enroll'])->name('courses.enroll');
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/users/create', [AdminController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/profile', [AdminController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroy'])->name('users.destroy');
        Route::get('/monitoring', [AdminController::class, 'monitoring'])->name('monitoring');
    });
});
