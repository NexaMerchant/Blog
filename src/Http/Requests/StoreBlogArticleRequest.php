<?php

namespace NexaMerchant\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogArticleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:512',
            'content' => 'nullable|string',
            'description' => 'sometimes|string',
            // 'cover_image' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:blog_categories,id',
            'seo_meta_title' => 'required|string|max:255',
            'seo_meta_keywords' => 'required|string|max:255',
            'seo_meta_description' => 'required|string|max:255',
            'seo_url_key' => 'required|string|max:255|unique:blog_articles,seo_url_key'
        ];
    }
}