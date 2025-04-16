<?php

namespace NexaMerchant\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBlogArticleRequest extends FormRequest
{
    public function authorize()
    {
        return true;//$this->user()->can('update', $this->route('article'));
    }

    public function rules()
    {
        $articleId = $this->route('article');

        return [
            'title' => 'sometimes|string|max:512',
            'content' => 'sometimes|string',
            'category_id' => 'sometimes|integer|exists:blog_categories,id',
            'status' => 'sometimes|integer|in:1,2',
            'seo_meta_title' => 'sometimes|string|max:255',
            'seo_meta_keywords' => 'sometimes|string|max:255',
            'seo_meta_description' => 'sometimes|string|max:255',
            'seo_url_key' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('ba_blog_articles', 'seo_url_key')->ignore($articleId)
            ],
            'cover_image' => 'nullable|string|max:255'
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('content') && !$this->has('cover_image')) {
            $this->merge([
                'cover_image' => $this->extractFirstImage($this->input('content'))
            ]);
        }
    }

    protected function extractFirstImage($content)
    {
        preg_match('/<img[^>]+src="([^">]+)"/', $content, $matches);
        return $matches[1] ?? null;
    }
}