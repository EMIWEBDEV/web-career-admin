<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Career\MppLowongan\MppLowonganController;

Route::resource('mpp-lowongan', MppLowonganController::class);
