<?php

namespace NexaMerchant\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'seo_meta_title' => 'string|max:512',
            'seo_meta_keywords' => 'string|max:512',
            'seo_meta_description' => 'string',
            'seo_url_key' => 'required|string|max:255|unique:blog_categories,seo_url_key'
        ];
    }
}