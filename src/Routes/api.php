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

Route::group(['middleware' => ['api'], 'prefix' => 'api'], function () {
    Route::prefix('blog')->group(function () {

        Route::controller(ExampleController::class)->prefix('example')->group(function () {

            Route::get('demo', 'demo')->name('blog.api.example.demo');

        });

    });

    Route::controller(BlogController::class)->prefix('blog')->group(function () {
        // 分类接口
        Route::post('categories', 'storeCategory');
        Route::get('categories', 'listCategories');
        Route::get('categories/{id}', 'showCategory');
        Route::put('categories/{id}', 'updateCategory');
        Route::delete('categories/{id}', 'deleteCategory');

        // 文章接口
        Route::post('articles', 'storeArticle');
        Route::get('articles', 'listArticles');
        Route::get('articles/{id}', 'showArticle');
        Route::put('articles/{id}', 'updateArticle');
        // Route::delete('articles/{id}', 'deleteArticle');
    });

});