<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ChiTietDonHang extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    protected $guarded = [];

    // NEW: ép kiểu các trường snapshot/custom để đọc/ghi an toàn
    protected $casts = [
        'is_custom'      => 'boolean', // dòng mẫu khách
        'price_snapshot' => 'integer', // giá chốt tại thời điểm thêm dòng
        'so_luong'       => 'integer',
        'chiet_khau'     => 'float',   // nếu có dùng % chiết khấu dòng
    ];

    // (Tuỳ chọn) Nếu muốn FE nhận sẵn tổng tiền dòng theo snapshot
    // protected $appends = ['tong_tien_snapshot'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            unset($model->attributes['image']);
        });
    }

    /**
     * Tính số lượng còn lại có thể xuất kho cho chi tiết đơn hàng này.
     */
    protected function soLuongConLaiXuatKho(): Attribute
    {
        return Attribute::make(
            get: fn() => (int) $this->so_luong - (int) ($this->so_luong_da_xuat_kho ?? 0),
        );
    }

    // NEW (tuỳ chọn): Thành tiền của dòng theo snapshot (sau chiết khấu dòng nếu có)
    // public function getTongTienSnapshotAttribute(): int
    // {
    //     $qty   = (int) ($this->so_luong ?? 0);
    //     $price = (int) ($this->price_snapshot ?? 0);
    //     $ck    = (float) ($this->chiet_khau ?? 0); // % nếu có
    //     $subtotal = $qty * $price;
    //     if ($ck > 0) {
    //         $subtotal = (int) round($subtotal * (1 - $ck / 100));
    //     }
    //     return max(0, $subtotal);
    // }

    // Kết nối sẵn với bảng images để lưu ảnh
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class);
    }

    public function donViTinh()
    {
        return $this->belongsTo(DonViTinh::class);
    }
}
