<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\ImageUpload;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter, ImageUpload;

    protected $guarded = [];

    // Cho FE thấy cả url & is_primary (ngoài id, path)
    protected $visible = ['id', 'path', 'url', 'is_primary'];

    // is_primary nên ép kiểu boolean
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // Boot model để đăng ký các events
    protected static function boot()
    {
        parent::boot();

        // Event trước khi update - xóa ảnh cũ (chỉ khi DÙNG FILE LOCAL)
        static::updating(function ($image) {
            // Nếu record ĐANG dùng url ảnh ngoài => KHÔNG đụng file local
            if (!empty($image->url)) {
                return true;
            }

            // Vẫn giữ hành vi cũ: path đổi và có path cũ => xóa file local cũ
            if ($image->isDirty('path') && $image->getOriginal('path')) {
                $oldPath = str_replace(env('APP_URL') . '/', '', $image->getOriginal('path'));
                $image->deleteImage($oldPath);
            }

            return true;
        });

        // Event trước khi delete - xóa ảnh (chỉ khi DÙNG FILE LOCAL)
        static::deleting(function ($image) {
            // Nếu record dùng url ảnh ngoài => KHÔNG xóa file local
            if (!empty($image->url)) {
                return true;
            }

            if ($image->path) {
                $path = str_replace(env('APP_URL') . '/', '', $image->path);
                $image->deleteImage($path);
            }

            return true;
        });
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
