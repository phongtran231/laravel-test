<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('company-and-cost', [\App\Http\Controllers\GetListCompanyAndCostController::class, 'execute']);
