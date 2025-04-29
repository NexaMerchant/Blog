<?php

namespace NexaMerchant\Blog\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BlogArticle extends Model
{
    // protected $table = 'ba_blog_articles';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    protected $fillable = [
        'title',
        'description',
        'content',
        'cover_image',
        'category_id',
        'status',
        'seo_meta_title',
        'seo_meta_keywords',
        'seo_meta_description',
        'seo_url_key'
    ];

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id', 'id');
    }

    public function relatedArticles()
    {
        return $this->hasMany(BlogArticle::class, 'category_id', 'category_id')
            ->where('id', '!=', $this->id)
            ->orderBy('created_at', 'desc');
    }

    /**
     * 从HTML内容中提取第一张图片URL
     */
    public static function extractFirstImageFromContent(string $content): ?string
    {
        // 方案1：正则匹配（简单HTML内容）
        preg_match('/<img[^>]+src="([^">]+)"/', $content, $matches);
        $imageUrl = $matches[1] ?? '';

        // 方案2：DOM解析（更复杂的HTML）
        /*
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $images = $dom->getElementsByTagName('img');
        $imageUrl = $images->length > 0 ? $images->item(0)->getAttribute('src') : null;
        */

        // 如果是本地相对路径，转换为存储路径
        if ($imageUrl && !Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return Storage::url(ltrim(parse_url($imageUrl, PHP_URL_PATH), '/'));
        }

        return $imageUrl;
    }
}