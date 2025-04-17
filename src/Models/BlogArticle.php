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
}