<?php

namespace NexaMerchant\Blog\Import;

use Maatwebsite\Excel\Concerns\ToModel;
use NexaMerchant\Blog\Models\BlogArticle;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use NexaMerchant\Blog\Http\Controllers\Api\BlogController;

class ArticleImport implements ToModel, WithHeadingRow, WithValidation
{
    public function rules(): array
    {
        return [
            '*.title' => 'nullable|string|max:512',
            '*.content' => 'required|string',
            // '*.description' => 'nullable|string',
            '*.category_id' => 'nullable|integer|exists:blog_categories,id',
            '*.seo_meta_title' => 'nullable|string|max:512',
            '*.seo_meta_keywords' => 'nullable|string|max:512',
            '*.seo_meta_description' => 'nullable|string',
            '*.seo_url_key' => 'required|string|max:255|unique:blog_articles,seo_url_key'
        ];
    }

    public function model(array $row)
    {
        $row['cover_image'] = BlogArticle::extractFirstImageFromContent($row['content']);
        // desc from content 截取200个字符
        $row['description'] = mb_substr(self::getBodyTextByHtml($row['content']), 0, 200);
        $row['category_id'] = $row['category_id'] ?: 0;
        $row['seo_meta_title'] = $row['seo_meta_title'] ?: '';
        $row['seo_meta_keywords'] = $row['seo_meta_keywords'] ?: '';
        $row['seo_meta_description'] = $row['seo_meta_description'] ?: '';
        $row['seo_url_key'] = self::formatString($row['seo_url_key']);
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['updated_at'] = date('Y-m-d H:i:s');

        if (empty($row['title'])) {
            $row['title'] = self::getTitleTextByHtml($row['content']);
        }

        $blogArticle = new BlogArticle($row);
        $blogArticle->save();
    }

    public static function formatString($str)
    {
        $str = strtolower($str);
        $str = trim($str);
        // 移除emoji
        $str = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}]/u', '', $str);
        // 替换特殊字符为-
        $str = str_replace('/[^\w\s-]/g/', '-', $str);
        // 替换空格为-
        $str = str_replace(' ', '-', $str);
        // 移除开头和结尾的连字符
        $str = trim($str, '-');

        return $str;
    }

    public static function getBodyTextByHtml($html)
    {
        // 创建一个新的 DOMDocument 实例
        $dom = new \DOMDocument();

        // 加载 HTML，禁用警告（有些 HTML 不规范）
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        // 提取纯文本
        $text = $dom->textContent;

        // 去掉多余的空白字符
        return trim($text);
    }

    public static function getTitleTextByHtml($html)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $title = $dom->getElementsByTagName('title')->item(0)->nodeValue;
        return trim($title);
    }

}