<?php

namespace NexaMerchant\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class BlogArticle extends Model
{
    // protected $table = 'ba_blog_articles';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    protected $fillable = [
        'title',
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
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }
}