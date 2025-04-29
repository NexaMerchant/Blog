<?php

namespace NexaMerchant\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use NexaMerchant\Blog\Models\BlogArticle;

class UpdateBlogArticleRequest extends FormRequest
{
    public function authorize()
    {
        return true;//$this->user()->can('update', $this->route('article'));
    }

    public function rules()
    {
        $articleId = $this->route('id');

        return [
            'title' => 'sometimes|string|max:512',
            'description' => 'sometimes|string',
            'content' => 'sometimes|string',
            'category_id' => 'nullable|integer|exists:blog_categories,id',
            'status' => 'sometimes|integer|in:1,2',
            'seo_meta_title' => 'sometimes|string|max:512',
            'seo_meta_keywords' => 'sometimes|string|max:512',
            'seo_meta_description' => 'sometimes|string',
            'seo_url_key' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('blog_articles', 'seo_url_key')->ignore($articleId)
            ],
            'cover_image' => 'nullable|string|max:512'
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('content') && !$this->has('cover_image')) {
            $this->merge([
                'cover_image' => BlogArticle::extractFirstImageFromContent($this->input('content'))
            ]);
        }
    }
}