<?php

namespace NexaMerchant\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBlogCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true; // 或添加权限检查：return $this->user()->can('update', $this->route('category'));
    }

    public function rules()
    {
        $categoryId = $this->route('category');

        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'seo_meta_title' => 'sometimes|string|max:512',
            'seo_meta_keywords' => 'sometimes|string|max:512',
            'seo_meta_description' => 'sometimes|string',
            'seo_url_key' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('blog_categories', 'seo_url_key')->ignore($categoryId)
            ]
        ];
    }

    public function messages()
    {
        return [
            'seo_url_key.unique' => 'SEO URL 关键词已被使用'
        ];
    }
}