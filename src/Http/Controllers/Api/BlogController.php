<?php

namespace NexaMerchant\Blog\Http\Controllers\Api;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NexaMerchant\Blog\Models\BlogArticle;
use NexaMerchant\Blog\Models\BlogCategory;
use NexaMerchant\Blog\Http\Requests\StoreBlogArticleRequest;
use NexaMerchant\Blog\Http\Requests\StoreBlogCategoryRequest;
use NexaMerchant\Blog\Http\Requests\UpdateBlogArticleRequest;
use NexaMerchant\Blog\Http\Requests\UpdateBlogCategoryRequest;

class BlogController extends Controller
{
    // 新增分类
    public function storeCategory(StoreBlogCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = BlogCategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => '分类创建成功'
        ], 201);
    }

    public function updateCategory(UpdateBlogCategoryRequest $request, $categoryId)
    {
        try {
            // 查找分类
            $category = BlogCategory::findOrFail($categoryId);

            // 记录原始数据
            $originalData = $category->toArray();

            // 更新数据
            $category->update($request->validated());

            // 日志记录
            Log::info('分类已更新', [
                'user_id' => auth()->id(),
                'category_id' => $categoryId,
                'changes' => array_diff_assoc($category->toArray(), $originalData)
            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => '分类更新成功'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('分类不存在', ['category_id' => $categoryId]);
            return response()->json([
                'success' => false,
                'message' => '指定的分类不存在'
            ], 404);

        } catch (\Exception $e) {
            Log::error('分类更新失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '服务器错误，请稍后重试'
            ], 500);
        }
    }

    // 新增文章
    public function storeArticle(StoreBlogArticleRequest $request)
    {
        $validated = $request->validated();

        // 自动从content提取封面图
        $coverImage = $this->extractFirstImageFromContent($validated['content']);

        // 合并数据
        $validated = array_merge($validated, [
            'cover_image' => $coverImage ?? '' #'defaults/article-cover.jpg' // 默认封面图
        ]);

        $article = BlogArticle::create($validated);

        return response()->json([
            'success' => true,
            'data' => $article,
            'message' => '文章创建成功'
        ], 201);
    }

    public function updateArticle(UpdateBlogArticleRequest $request, $articleId)
    {
        return DB::transaction(function () use ($request, $articleId) {
            try {
                $article = BlogArticle::with('category')->findOrFail($articleId);

                // 获取验证数据（自动处理了封面图）
                $validated = $request->validated();

                // 保留原始封面图（如果没有新图且内容中无图）
                if (!isset($validated['cover_image'])) {
                    $validated['cover_image'] = $article->cover_image;
                }

                $article->update($validated);

                return response()->json([
                    'success' => true,
                    'data' => $article->fresh(),
                    'message' => '文章更新成功',
                    'changes' => $request->all()
                ]);

            } catch (\Exception $e) {
                Log::error('文章更新失败: '.$e->getMessage(), [
                    'article_id' => $articleId,
                    'user_id' => auth()->id(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '更新失败: '.$e->getMessage()
                ], 500);
            }
        });
    }

    public function listCategories(Request $request)
    {
        $data = [];
        $data['code'] = 200;
        $data['message'] = "success";
        return response()->json($data);

        // 验证请求参数
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|in:name,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc'
        ]);

        // 构建查询
        $query = BlogCategory::query();

        // 搜索条件
        if (!empty($validated['search'])) {
            $query->where('name', 'like', '%'.$validated['search'].'%')
                ->orWhere('description', 'like', '%'.$validated['search'].'%');
        }

        // 排序
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // 分页
        $perPage = $validated['per_page'] ?? 15;
        $categories = $query->paginate($perPage);

        // 格式化响应
        return response()->json([
            'success' => true,
            'data' => $categories,
            'message' => '分类列表获取成功'
        ]);
    }

    public function listArticles(Request $request)
    {
        // 验证请求参数
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:512',
            'category_id' => 'nullable|integer|exists:blog_categories,id',
            'sort_by' => 'nullable|in:title,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc',
            'with_category' => 'nullable|boolean'
        ]);

        // 构建基础查询
        $query = BlogArticle::query();

        // 关键词搜索（标题/内容）
        if (!empty($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('title', 'like', '%'.$validated['search'].'%')
                ->orWhere('content', 'like', '%'.$validated['search'].'%');
            });
        }

        // 分类过滤
        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        // 排序设置
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // 关联分类数据
        if ($request->boolean('with_category')) {
            $query->with('category');
        }

        // 执行分页查询
        $articles = $query->paginate($validated['per_page'] ?? 10);

        // 格式化响应
        return response()->json([
            'success' => true,
            'data' => $articles,
            'message' => '文章列表获取成功'
        ]);
    }

    /**
     * 从HTML内容中提取第一张图片URL
     */
    private function extractFirstImageFromContent(string $content): ?string
    {
        // 方案1：正则匹配（简单HTML内容）
        preg_match('/<img[^>]+src="([^">]+)"/', $content, $matches);
        $imageUrl = $matches[1] ?? null;

        // 方案2：DOM解析（更复杂的HTML）
        /*
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $images = $dom->getElementsByTagName('img');
        $imageUrl = $images->length > 0 ? $images->item(0)->getAttribute('src') : null;
        */

        // 如果是本地相对路径，转换为存储路径
        if ($imageUrl && !Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return Storage::url(ltrim(parse_url($imageUrl, PHP_URL_PATH), '/'));
        }

        return $imageUrl;
    }

    public function deleteCategory($categoryId)
    {
        return DB::transaction(function () use ($categoryId) {
            try {
                $category = BlogCategory::withCount('articles')->findOrFail($categoryId);

                if ($category->articles_count > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => '该分类下存在文章，无法删除'
                    ], 422);
                }

                // 执行删除
                $category->delete();

                return response()->json([
                    'success' => true,
                    'message' => '分类删除成功',
                    'deleted_at' => Carbon::now()->toDateTimeString()
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => '分类不存在或已被删除'
                ], 404);

            } catch (\Exception $e) {
                Log::error('分类删除失败: '.$e->getMessage(), [
                    'category_id' => $categoryId,
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '删除失败: '.$e->getMessage()
                ], 500);
            }
        });
    }
}