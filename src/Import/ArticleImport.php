<?php

namespace NexaMerchant\Blog\Import;

use Maatwebsite\Excel\Concerns\ToModel;
use NexaMerchant\Blog\Models\BlogArticle;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use NexaMerchant\Blog\Http\Controllers\Api\BlogController;

class ArticleImport implements ToModel, WithHeadingRow
{
    public function rules(): array
    {
        return [
            '*.title' => 'required|string|max:512',
            '*.content' => 'required|string',
            // '*.description' => 'nullable|string',
            '*.category_id' => 'required|integer|exists:blog_categories,id',
            '*.seo_meta_title' => 'nullable|string|max:512',
            '*.seo_meta_keywords' => 'nullable|string|max:512',
            '*.seo_meta_description' => 'nullable|string',
            '*.seo_url_key' => 'required|string|max:255|unique:blog_articles,seo_url_key'
        ];
    }

    public function model(array $row)
    {
        $blogController = new BlogController();
        $row['cover_image'] = $blogController->extractFirstImageFromContent($row['content']);
        // desc from content 截取200个字符
        $row['description'] = mb_substr($row['content'], 0, 200);
        $row['seo_meta_title'] = $row['seo_meta_title'] ?: '';
        $row['seo_meta_keywords'] = $row['seo_meta_keywords'] ?: '';
        $row['seo_meta_description'] = $row['seo_meta_description'] ?: '';
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['updated_at'] = date('Y-m-d H:i:s');

        $blogArticle = new BlogArticle($row);
        $blogArticle->save();
    }
}