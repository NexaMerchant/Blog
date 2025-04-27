<?php

namespace NexaMerchant\Blog\Http\Controllers\Api;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use NexaMerchant\Blog\Models\BlogArticle;
use NexaMerchant\Blog\Models\BlogCategory;
use NexaMerchant\Blog\Http\Requests\StoreBlogArticleRequest;
use NexaMerchant\Blog\Http\Requests\StoreBlogCategoryRequest;
use NexaMerchant\Blog\Http\Requests\UpdateBlogArticleRequest;
use NexaMerchant\Blog\Http\Requests\UpdateBlogCategoryRequest;
use NexaMerchant\Blog\Import\ArticleImport;

class BlogController extends Controller
{
    const CACHE_KEY_ARTICLE_RECOMMEND = 'article_recommendations_cache';

    // 新增分类
    public function storeCategory(StoreBlogCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = BlogCategory::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => '分类创建成功'
        ], 200);
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
            ], 200);
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

        Cache::tags([self::CACHE_KEY_ARTICLE_RECOMMEND])->flush();

        return response()->json([
            'success' => true,
            'data' => $article,
            'message' => '文章创建成功'
        ], 200);
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
                ], 200);
            } catch (\Exception $e) {
                Log::error('文章更新失败: ' . $e->getMessage(), [
                    'article_id' => $articleId,
                    'user_id' => auth()->id(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '更新失败: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    public function listCategories(Request $request)
    {
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
            $query->where('name', 'like', '%' . $validated['search'] . '%')
                ->orWhere('description', 'like', '%' . $validated['search'] . '%');
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
        ], 200);
    }

    public function listArticles(Request $request)
    {
        // 验证请求参数
        $validated = $request->validate([
            'category_url_key' => 'nullable|string|max:512',
            'status' => 'nullable|integer|in:1,2', // 1-已发布 2-已下架
            'per_page' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:512',
            'category_id' => 'nullable|integer|exists:blog_categories,id',
            'sort_by' => 'nullable|in:title,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc',
            'with_category' => 'nullable|boolean'
        ]);

        // 构建基础查询
        $query = BlogArticle::query()->with('category');

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['category_url_key'])) {
            $categoryId = BlogCategory::where('seo_url_key', $validated['category_url_key'])->value('id');
            if (!$categoryId) {
                return response()->json([
                    'success' => false,
                    'message' => '指定的分类不存在'
                ], 404);
            }
            $query->where('category_id', $categoryId);
        }

        // 关键词搜索（标题/内容）
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('title', 'like', '%' . $validated['search'] . '%');
                // ->orWhere('content', 'like', '%'.$validated['search'].'%');
            });
        }

        // 排序设置
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // 关联分类数据
        if ($request->boolean('with_category')) {
            $query->with('category');
        }

        $fields = [
            'id',
            'title',
            'cover_image',
            'description',
            'created_at',
            'updated_at',
            'view_count',
            'category_id',
            'status',
            'seo_meta_title',
            'seo_meta_keywords',
            'seo_meta_description',
            'seo_url_key'
        ];

        // 执行分页查询
        $articles = $query->select($fields)->paginate($validated['per_page'] ?? 10);

        // 返回结果补充按浏览量推荐三篇文章
        // $articles['recommended_articles'] = BlogArticle::query()->latest('view_count')->take(3)->get();

        // 格式化响应
        return response()->json([
            'success' => true,
            'data' => $articles,
            'message' => '文章列表获取成功'
        ], 200);
    }

    public function recommendArticles(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
            // 'exclude_id' => 'nullable|integer|exists:blog_articles,id',
            // 'exclude_id' => 'integer|exists:blog_articles,id',
            'type' => 'nullable|in:latest,popular'
        ]);

        $expiresAt = now()->addHours(1); // 缓存2小时
        $fields = [
            'id',
            'title',
            'cover_image',
            'description',
            'created_at',
            'updated_at',
            'view_count',
            'category_id',
            'status',
            'seo_meta_title',
            'seo_meta_keywords',
            'seo_meta_description',
            'seo_url_key'
        ];
        $articles = Cache::remember(self::CACHE_KEY_ARTICLE_RECOMMEND, $expiresAt, function () use ($validated, $fields) {
            return BlogArticle::query()
                ->where('status', 1) // 只推荐已发布文章
                ->when($validated['seo_url_key'] ?? false, function ($q) use ($validated) {
                    $q->where('seo_url_key', '!=', $validated['seo_url_key']); // 排除当前文章
                })
                ->when($validated['type'] === 'popular', function ($q) {
                    $q->orderBy('view_count', 'desc');
                }, function ($q) {
                    $q->latest();
                })
                ->take($validated['limit'] ?? 3)
                ->select($fields)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $articles,
            'cache' => [
                'expires_at' => $expiresAt->toDateTimeString()
            ]
        ], 200);
    }

    /**
     * 从HTML内容中提取第一张图片URL
     */
    public function extractFirstImageFromContent(string $content): ?string
    {
        // 方案1：正则匹配（简单HTML内容）
        preg_match('/<img[^>]+src="([^">]+)"/', $content, $matches);
        $imageUrl = $matches[1] ?? '';

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

    public function showArticle($id)
    {
        try {
            // 验证ID有效性
            // $validated = $request->validate([
            //     'with_category' => 'nullable|boolean',
            //     'with_related' => 'nullable|integer|min:1|max:5' // 相关文章数量
            // ]);

            // 构建查询
            $article = BlogArticle::query()
                // ->when($request->boolean('with_category'), function ($query) {
                //     $query->with(['category' => function ($q) {
                //         $q->select('id', 'name', 'seo_url_key');
                //     }]);
                // })
                // ->when($request->filled('with_related'), function ($query) use ($id, $request) {
                //     $query->with(['relatedArticles' => function ($q) use ($request) {
                //         $q->limit($request->input('with_related', 3))
                //             ->select('id', 'title', 'seo_url_key', 'created_at');
                //     }]);
                // })
                ->findOrFail($id);

            // 记录访问量（队列处理）
            dispatch(function () use ($id) {
                BlogArticle::where('id', $id)->increment('view_count');
            })->afterResponse();

            return response()->json([
                'success' => true,
                'data' => $this->formatArticleResponse($article),
                'message' => '文章获取成功'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '文章不存在'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('获取文章失败: ' . $e->getMessage(), [
                'article_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '服务器错误'
            ], 500);
        }
    }

    public function showCategory($id)
    {
        try {
            // $validated = $request->validate([
            //     'with_latest' => 'nullable|integer|min:1|max:5' // 最新文章数量
            // ]);

            $category = BlogCategory::query()->withCount(['articles' => function ($q) {
                $q->where('status', 1); // 只统计已发布文章
            }])->with(['latestArticles' => function ($q) {
                $q->select('id', 'title', 'seo_url_key', 'created_at')
                    ->where('status', 1)
                    ->orderBy('created_at', 'desc')
                    ->limit(3);
            }])->with(['articles' => function ($q) {
                $q->select('id', 'title', 'seo_url_key', 'created_at')
                    ->where('status', 1)
                    ->orderBy('created_at', 'desc')
                    ->limit(3);
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->formatCategoryResponse($category),
                'message' => '分类详情获取成功'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '分类不存在'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('获取分类失败: ' . $e->getMessage(), [
                'category_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '服务器错误' . $e->getMessage()
            ], 500);
        }
    }

    public function showCategoryBySlug($seo_url_key)
    {
        $categoryId = BlogCategory::where('seo_url_key', $seo_url_key)->value('id');
        if (!$categoryId) {
            return response()->json([
                'success' => false,
                'message' => '分类不存在'
            ], 404);
        }
        return $this->showCategory($categoryId);
    }

    public function showArticleBySlug($seo_url_key)
    {
        $articleId = BlogArticle::where('seo_url_key', $seo_url_key)->value('id');
        if (!$articleId) {
            return response()->json([
                'success' => false,
                'message' => '文章不存在'
            ], 404);
        }
        return $this->showArticle($articleId);
    }

    // 格式化响应数据
    protected function formatCategoryResponse($category)
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'seo_meta' => [
                'title' => $category->seo_meta_title,
                'keywords' => $category->seo_meta_keywords,
                'description' => $category->seo_meta_description,
                'url_key' => $category->seo_url_key
            ],
            'statistics' => [
                'articles_count' => $category->articles_count,
                'last_updated' => $category->updated_at->diffForHumans()
            ],
            'articles' => $category->articles->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'url' => route('articles.show', $article->seo_url_key),
                    'created_at' => $article->created_at->format('Y-m-d')
                ];
            }),
            'latest_articles' => $category->latestArticles->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'url' => route('articles.show', $article->seo_url_key),
                    'created_at' => $article->created_at->format('Y-m-d')
                ];
            }),
            'created_at' => $category->created_at->toDateTimeString(),
            'updated_at' => $category->updated_at->toDateTimeString()
        ];
    }

    // 格式化响应数据
    protected function formatArticleResponse($article)
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'description' => $article->description,
            'content' => $article->content,
            'cover_image' => asset($article->cover_image),
            'category' => $article->category ?: null,
            'view_count' => $article->view_count,
            'related_articles' => $article->relatedArticles->isEmpty() ? null : $article->relatedArticles,
            // 'recommended_articles' => BlogArticle::query()->latest('updated_at')->take(3)->get(),
            'seo_meta' => [
                'title' => $article->seo_meta_title,
                'keywords' => $article->seo_meta_keywords,
                'description' => $article->seo_meta_description,
                'url_key' => $article->seo_url_key
            ],
            'created_at' => $article->created_at->toDateTimeString(),
            'updated_at' => $article->updated_at->toDateTimeString()
        ];
    }

    public function deleteArticle($id)
    {
        $ids = $id ? explode(',', $id) : request()->input('ids', []);
        // 验证ID数组
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => '请指定要删除的文章ID'
            ], 400);
        }

        // 检查所有ID是否有效
        $validIds = BlogArticle::whereIn('id', $ids)
            ->pluck('id')
            ->toArray();

        if (count($ids) !== count($validIds)) {
            return response()->json([
                'success' => false,
                'message' => '包含无效的文章ID',
                'invalid_ids' => array_diff($ids, $validIds)
            ], 422);
        }

        // 执行删除
        BlogArticle::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
            'deleted_ids' => $ids,
            'count' => count($ids)
        ]);
    }

    public function deleteCategory($id)
    {
        return DB::transaction(function () use ($id) {
            try {

                $ids = $id ? explode(',', $id) : request()->input('ids', []);
                // 验证ID数组
                if (empty($ids)) {
                    return response()->json([
                        'success' => false,
                        'message' => '请指定要删除的分类ID'
                    ], 400);
                }

                // 检查所有ID是否有效
                $validIds = BlogCategory::whereIn('id', $ids)
                    ->pluck('id')
                    ->toArray();

                if (count($ids) !== count($validIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => '包含无效的分类ID',
                        'invalid_ids' => array_diff($ids, $validIds)
                    ], 422);
                }

                $categories = BlogCategory::withCount('articles')->whereIn('id', $ids)->get();
                foreach ($categories as $category) {
                    if ($category->articles_count > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => "分类id={$category->id}下存在文章，无法删除"
                        ], 422);
                    }
                }

                // 执行删除
                BlogCategory::whereIn('id', $ids)->delete();

                return response()->json([
                    'success' => true,
                    'message' => '分类删除成功',
                    'deleted_at' => Carbon::now()->toDateTimeString()
                ], 200);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => '分类不存在或已被删除'
                ], 404);
            } catch (\Exception $e) {
                Log::error('分类删除失败: ' . $e->getMessage(), [
                    'category_id' => $categoryId,
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '删除失败: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    public function batchUpdateArticleStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100', // 限制每次最多操作100条
            'ids.*' => 'integer|exists:blog_articles,id',
            'status' => [
                'required',
                Rule::in([1, 2]), // 1-已发布 2-已下架
            ],
        ]);

        return DB::transaction(function () use ($validated) {
            // 获取可操作的文章（防止越权更新）
            $operableArticles = BlogArticle::whereIn('id', $validated['ids'])->pluck('id');

            if (count($operableArticles) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '没有找到可操作的文章'
                ], 403);
            }

            // 执行更新
            $affected = BlogArticle::whereIn('id', $operableArticles)->update([
                'status' => $validated['status'],
                'updated_at' => now()
            ]);

            Cache::tags([self::CACHE_KEY_ARTICLE_RECOMMEND])->flush();

            return response()->json([
                'success' => true,
                'message' => "成功更新 {$affected} 篇文章状态",
                'data' => [
                    'updated_ids' => $operableArticles,
                    'new_status' => $validated['status']
                ]
            ]);
        });
    }

    public function batchDeleteArticle(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100', // 限制每次最多操作100条
            'ids.*' => 'integer|exists:blog_articles,id',
        ]);

        $ids = $validated['ids'];
        // 验证ID数组
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => '请指定要删除的文章ID'
            ], 400);
        }

        // 检查所有ID是否有效
        $validIds = BlogArticle::whereIn('id', $ids)
            ->pluck('id')
            ->toArray();

        if (count($ids) !== count($validIds)) {
            return response()->json([
                'success' => false,
                'message' => '包含无效的文章ID',
                'invalid_ids' => array_diff($ids, $validIds)
            ], 422);
        }

        // 执行删除
        BlogArticle::whereIn('id', $ids)->delete();

        Cache::tags([self::CACHE_KEY_ARTICLE_RECOMMEND])->flush();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
            'deleted_ids' => $ids,
            'count' => count($ids)
        ]);
    }

    public function batchDeleteCategory(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100', // 限制每次最多操作100条
            'ids.*' => 'integer|exists:blog_categories,id',
        ]);

        $ids = $validated['ids'];

        return DB::transaction(function () use ($ids) {
            try {
                // 验证ID数组
                if (empty($ids)) {
                    return response()->json([
                        'success' => false,
                        'message' => '请指定要删除的分类ID'
                    ], 400);
                }

                // 检查所有ID是否有效
                $validIds = BlogCategory::whereIn('id', $ids)
                    ->pluck('id')
                    ->toArray();

                if (count($ids) !== count($validIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => '包含无效的分类ID',
                        'invalid_ids' => array_diff($ids, $validIds)
                    ], 422);
                }

                $categories = BlogCategory::withCount('articles')->whereIn('id', $ids)->get();
                foreach ($categories as $category) {
                    if ($category->articles_count > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => "分类id={$category->id}下存在文章，无法删除"
                        ], 422);
                    }
                }

                // 执行删除
                BlogCategory::whereIn('id', $ids)->delete();

                return response()->json([
                    'success' => true,
                    'message' => '分类删除成功',
                    'deleted_at' => Carbon::now()->toDateTimeString()
                ], 200);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => '分类不存在或已被删除'
                ], 404);
            } catch (\Exception $e) {
                Log::error('分类删除失败: ' . $e->getMessage(), [
                    'category_id' => $categoryId,
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '删除失败: ' . $e->getMessage()
                ], 500);
            }
        });
    }

    public function articlesUpload(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx',
        ]);

        try {
            $path = $request->file('file')->store('uploads', 'local');
            Excel::import(new ArticleImport, $request->file('file'));

            Cache::tags([self::CACHE_KEY_ARTICLE_RECOMMEND])->flush();

            return response()->json([
                'success' => true,
                'message' => '导入成功',
                'saved_path' => $path,
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            return response()->json([
                'message'  => 'Import failed due to validation errors.',
                'errors'   => $failures,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during import.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
