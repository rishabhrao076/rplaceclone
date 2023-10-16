<?php

use App\Events\ColorChanged;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('index');
});

Broadcast::routes();

Route::post('/save', function (Request $request) {
    Redis::set($request->key, $request->color . ":" . $request->user()->name);
    ColorChanged::dispatch([
        'key' => $request->key,
        'color' => $request->color . ':' . $request->user()->name,
    ]);

    return response()->json(Redis::get($request->key));
})->middleware(["auth", "throttle:pixelChange"]);

Route::get('/map', function () {
    $pixels = [];
    for ($x = 0; $x <= 99; $x++) {
        $rows = [];
        for ($y = 0; $y <= 99; $y++) {
            $rows[] = "{$x}:{$y}";
        }

        $pixels[] = Redis::mget($rows);
    };

    return $pixels;
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';