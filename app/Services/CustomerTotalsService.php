<?php

namespace App\Services;

use App\Models\KhachHang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tính lại doanh thu/điểm/hạng cho khách hàng từ bảng đơn hàng.
 * NOTE: nếu tên bảng/cột đơn hàng của bạn khác, sửa 2 chỗ TODO bên dưới.
 */
class CustomerTotalsService
{
    /**
     * Re-calc totals for a customer.
     */
    public function refresh(int $khachHangId): void
    {
        // TODO(1): Sửa tên bảng/cột nếu khác schema của bạn
        // Tổng tiền các đơn đã thanh toán hoặc đã cọc
        $tong = (int) DB::table('don_hangs')
            ->where('khach_hang_id', $khachHangId)
            ->whereIn('trang_thai_thanh_toan', ['da_thanh_toan', 'da_coc'])
            ->sum('tong_tien');

        $kh = KhachHang::findOrFail($khachHangId);
        $kh->doanh_thu_tich_luy = $tong;
        $kh->diem_tich_luy     = (int) floor($tong / 10000);

        // Map hạng thành viên theo ngưỡng doanh thu ở bảng loai_khach_hangs
        // (lấy ngưỡng cao nhất mà tổng đạt)
        // TODO(2): Nếu tên cột khác, đổi cho khớp (ten_loai_khach_hang, nguong_doanh_thu)
        $tier = DB::table('loai_khach_hangs')
            ->select('ten_loai_khach_hang', 'nguong_doanh_thu')
            ->orderByDesc('nguong_doanh_thu')
            ->get()
            ->first(function ($row) use ($tong) {
                return $tong >= (int) $row->nguong_doanh_thu;
            });

        $kh->hang_thanh_vien    = $tier->ten_loai_khach_hang ?? 'Thành viên';
        $kh->ngay_cap_nhat_hang = Carbon::today();

        $kh->save();
    }
}
