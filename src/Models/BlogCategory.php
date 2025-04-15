<?php

namespace NexaMerchant\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    // protected $table = 'ba_blog_categories';

    protected $fillable = [
        'name',
        'description',
        'seo_meta_title',
        'seo_meta_keywords',
        'seo_meta_description',
        'seo_url_key'
    ];

    public function articles()
    {
        return $this->hasMany(BlogArticle::class, 'category_id');
    }
}