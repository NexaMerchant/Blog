<?php
/**
 *
 * This file is auto generate by Nicelizhi\Apps\Commands\Create
 * @author Steve
 * @date 2025-04-11 09:02:26
 * @link https://github.com/xxxl4
 *
 */
use Illuminate\Support\Facades\Route;
use NexaMerchant\Blog\Http\Controllers\Api\ExampleController;
use NexaMerchant\Blog\Http\Controllers\Api\BlogController;

Route::group(['middleware' => 'api', 'prefix' => 'api/v1'], function () {

    // 示例路由组（保持不变）
    Route::prefix('blog')->group(function () {
        Route::controller(ExampleController::class)
            ->prefix('example')
            ->group(function () {
                Route::get('demo', 'demo')->name('v1.blog.api.example.demo');
            });
    });

    // 后台接口
    Route::prefix('admin')->group(function () {
        Route::controller(BlogController::class)
            ->prefix('blog')
            ->group(function () {
                // 分类接口
                Route::post('categories', 'storeCategory')->name('v1.admin.blog.categories.store');
                Route::get('categories', 'listCategories')->name('v1.admin.blog.categories.index');
                Route::get('categories/{id}', 'showCategory')->name('v1.admin.blog.categories.show');
                Route::put('categories/{id?}', 'updateCategory')->name('v1.admin.blog.categories.update');
                Route::delete('categories/{id?}', 'deleteCategory')
                    ->where('id', '^[0-9,]+$') // 允许数字或逗号分隔的ID
                    ->name('v1.admin.blog.categories.destroy');

                // 批量删除分类
                Route::patch('categories/batch-delete', 'batchDeleteCategory')->name('v1.admin.blog.articles.batch_category');

                // 文章接口
                Route::post('articles', 'storeArticle')->name('v1.admin.blog.articles.store');
                Route::get('articles', 'listArticles')->name('v1.admin.blog.articles.index');
                Route::get('articles/{id}', 'showArticle')->name('v1.admin.blog.articles.show');
                Route::put('articles/{id}', 'updateArticle')->name('v1.admin.blog.articles.update');
                Route::delete('articles/{id?}', 'deleteArticle')
                    ->where('id', '^[0-9,]+$') // 允许数字或逗号分隔的ID
                    ->name('v1.admin.blog.articles.destroy');

                // 批量更新文章状态
                Route::patch('articles/batch-status', 'batchUpdateArticleStatus')->name('v1.admin.blog.articles.batch_status');

                // 批量删除文章
                Route::patch('articles/batch-delete', 'batchDeleteArticle')->name('v1.admin.blog.articles.batch_delete');
            });
    });

    // 前端接口
    Route::controller(BlogController::class)
        ->prefix('blog')
        ->group(function () {
            // 分类接口
            Route::get('categories', 'listCategories')->name('v1.blog.categories.index');
            Route::get('categories/{id}', 'showCategory')->name('v1.blog.categories.show');
            Route::get('categories/by-slug/{seo_url_key}', 'showCategoryBySlug')->name('v1.blog.categories.show.slug');

            // 文章接口
            Route::get('articles', 'listArticles')->name('v1.blog.articles.index');
            Route::get('articles/{id}', 'showArticle')->name('v1.blog.articles.show');
            Route::get('articles/by-slug/{seo_url_key}', 'showCategoryBySlug')->name('v1.blog.categories.show.slug');
        });

});