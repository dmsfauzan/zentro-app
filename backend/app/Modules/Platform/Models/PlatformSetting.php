<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember(
            "platform_setting:{$key}",
            now()->addHour(),
            fn () => static::query()->where('key', $key)->value('value')
        );

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value, ?string $description = null): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description],
        );

        Cache::forget("platform_setting:{$key}");

        return $setting;
    }
}
