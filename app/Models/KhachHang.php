<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** BỔ SUNG: dùng helper sinh mã KH */
use App\Support\CodeGen;

class KhachHang extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    /** Nếu bạn muốn chỉ định rõ bảng: */
    protected $table = 'khach_hangs';

    /** Bạn đang dùng guarded = [] để fill all */
    protected $guarded = [];

    /**
     * Đảm bảo khi trả JSON vẫn có trường 'pipeline'
     * để FE cũ không bị vỡ (alias cho tinh_trang_khach).
     */
    protected $appends = ['pipeline'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // TỰ SINH MÃ KH nếu đang rỗng
            if (empty($model->ma_khach_hang)) {
                $model->ma_khach_hang = CodeGen::next('KH'); // => KH00001, KH00002, ...
            }

            // Tránh lỗi nếu FE gửi field ảo
            unset($model->attributes['image']);
        });
    }

    /** ----------------- Accessors / Mutators (Alias) ----------------- */

    /**
     * Alias getter cho FE: $kh->pipeline -> $kh->tinh_trang_khach
     */
    public function getPipelineAttribute()
    {
        return $this->attributes['tinh_trang_khach'] ?? null;
    }

    /**
     * Alias setter cho FE: $kh->pipeline = '...' -> set tinh_trang_khach
     */
    public function setPipelineAttribute($value): void
    {
        $this->attributes['tinh_trang_khach'] = $value;
    }

    /** ----------------- Relationships ----------------- */

    public function loaiKhachHang(): BelongsTo
    {
        return $this->belongsTo(LoaiKhachHang::class, 'loai_khach_hang_id');
    }

    // Kết nối sẵn với bảng images để lưu ảnh
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // Đơn hàng của khách (để tính doanh thu/điểm, nếu cần)
    public function donHangs(): HasMany
    {
        return $this->hasMany(DonHang::class, 'khach_hang_id');
    }
}
