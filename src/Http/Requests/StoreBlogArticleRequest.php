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
            'content' => 'required|string',
            'description' => 'nullable|string',
            'category_id' => 'required|integer|exists:blog_categories,id',
            'seo_meta_title' => 'nullable|string|max:512',
            'seo_meta_keywords' => 'nullable|string|max:512',
            'seo_meta_description' => 'nullable|string',
            'seo_url_key' => 'required|string|max:255|unique:blog_articles,seo_url_key'
        ];
    }
}