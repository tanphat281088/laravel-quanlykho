<?php

use Illuminate\Support\Facades\Route;
use App\Modules\KhachHang\KhachHangController;

// ==== PHG CRM: KHÁCH HÀNG ====
Route::prefix('khach-hang')->group(function () {
    Route::get('/',        [KhachHangController::class, 'index']);
    Route::get('/{id}',    [KhachHangController::class, 'show']);
    Route::post('/',       [KhachHangController::class, 'store']);
    Route::put('/{id}',    [KhachHangController::class, 'update']);
    Route::delete('/{id}', [KhachHangController::class, 'destroy']);

    // lookup theo SĐT
    Route::get('/lookup/phone', [KhachHangController::class, 'lookupByPhone']);

    // ép tính lại tổng/điểm/hạng
    Route::post('/{id}/recalc', [KhachHangController::class, 'recalc']);

    // options cho dropdown
    Route::get('/options', [KhachHangController::class, 'options']);
});
