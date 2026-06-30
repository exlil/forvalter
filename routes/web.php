<?php

use App\Http\Controllers\AnnualExportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\IngestDocumentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', 'pages::login')->name('login');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/oversikt');

    Route::livewire('/oversikt', 'pages::dashboard')->name('dashboard');
    Route::livewire('/boliger', 'pages::properties')->name('properties.index');
    Route::livewire('/boliger/{property}', 'pages::property-show')->name('properties.show');
    Route::livewire('/enheter/{unit}', 'pages::unit-show')->name('units.show');
    Route::livewire('/registrer-utgift', 'pages::expenses-create')->name('expenses.create');
    Route::livewire('/kjorebok', 'pages::trips')->name('trips.index');
    Route::livewire('/arsoppgjor', 'pages::annual-report')->name('arsoppgjor');
    Route::get('/arsoppgjor/{year}/pdf', [AnnualExportController::class, 'pdf'])->name('arsoppgjor.pdf');
    Route::get('/arsoppgjor/{year}/regneark', [AnnualExportController::class, 'spreadsheet'])->name('arsoppgjor.csv');
    Route::get('/arsoppgjor/{year}/bilag', [AnnualExportController::class, 'bilag'])->name('arsoppgjor.bilag');
    Route::livewire('/innboks', 'pages::intake')->name('intake');
    Route::livewire('/innboks/bom/{analysis}', 'pages::toll-review')->name('intake.toll');

    // Global drop target — bilag dropped anywhere in the app POST here.
    Route::post('/bilag/slipp', IngestDocumentController::class)->name('intake.ingest');
    Route::get('/bilag/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
