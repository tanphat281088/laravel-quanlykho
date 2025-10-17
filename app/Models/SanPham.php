<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SanPham extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    // Cho phép mass-assign tất cả field
    protected $guarded = [];

    // Appends giúp FE/BE đọc nhanh các thuộc tính tính toán
    protected $appends = ['discount_percent', 'image_primary_url'];

    // (không bắt buộc, nhưng tiện ép kiểu 2 cột giá mới)
    protected $casts = [
        'gia_chinh'    => 'integer', // Giá đặt trước 3 ngày
        'gia_dat_ngay' => 'integer', // Giá đặt ngay
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Không cho ghi cột 'image' ảo (đã có bảng images riêng)
            unset($model->attributes['image']);
        });
    }

    /** ================= Quan hệ ================= */

    public function donViTinhs(): BelongsToMany
    {
        return $this->belongsToMany(DonViTinh::class, 'don_vi_tinh_san_phams', 'san_pham_id', 'don_vi_tinh_id')->withTimestamps();
    }

    public function donViTinhSanPhams(): HasMany
    {
        return $this->hasMany(DonViTinhSanPham::class);
    }

    public function nhaCungCaps(): BelongsToMany
    {
        return $this->belongsToMany(NhaCungCap::class, 'nha_cung_cap_san_phams', 'san_pham_id', 'nha_cung_cap_id')->withTimestamps();
    }

    public function nhaCungCapSanPhams(): HasMany
    {
        return $this->hasMany(NhaCungCapSanPham::class);
    }

    public function danhMuc(): BelongsTo
    {
        return $this->belongsTo(DanhMucSanPham::class);
    }

    public function chiTietPhieuNhapKhos(): HasMany
    {
        return $this->hasMany(ChiTietPhieuNhapKho::class);
    }

    // Kết nối sẵn với bảng images để lưu ảnh (polymorphic)
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /** ============== Accessors (tính toán) ============== */

    // % chênh lệch giữa "Giá đặt ngay" và "Giá đặt trước 3 ngày"
    public function getDiscountPercentAttribute(): ?int
    {
        $list = (int) ($this->gia_dat_ngay ?? 0);
        $pre  = (int) ($this->gia_chinh ?? 0);
        if ($list > 0 && $pre > 0 && $list > $pre) {
            return (int) round(($list - $pre) / $list * 100);
        }
        return null;
    }

    // URL ảnh chính: ưu tiên is_primary + url → url bất kỳ → path local
    public function getImagePrimaryUrlAttribute(): ?string
    {
        // Đảm bảo đã load quan hệ images
        if (!$this->relationLoaded('images')) {
            $this->load('images');
        }

        // 1) Ảnh được đánh dấu is_primary và có url
        $img = $this->images->firstWhere('is_primary', 1);
        if ($img && !empty($img->url)) {
            return $img->url;
        }

        // 2) Bất kỳ ảnh nào có url
        $img = $this->images->first(fn($i) => !empty($i->url));
        if ($img) {
            return $img->url;
        }

        // 3) Fallback: path local (nếu có dùng storage public)
        $img = $this->images->first(fn($i) => !empty($i->path));
        if ($img) {
            return asset('storage/' . ltrim($img->path, '/'));
        }

        return null;
    }
}
