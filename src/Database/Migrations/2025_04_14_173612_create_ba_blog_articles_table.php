<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up()
    {
        Schema::create('blog_articles', function (Blueprint $table) {
            // 主键
            $table->unsignedInteger('id')->autoIncrement()->comment('文章ID');

            // 基础信息
            $table->string('title', 512)->comment('文章标题');
            $table->text('content')->nullable()->comment('文章内容');
            $table->string('cover_image', 512)->comment('封面图路径');

            // 分类关联
            $table->unsignedInteger('category_id')->default(0)->comment('分类ID');

            // 状态控制
            $table->tinyInteger('status')->default(1)->comment('状态：0-草稿 1-已发布 2-已下架');

            // SEO信息
            $table->string('seo_meta_title', 512);
            $table->string('seo_meta_keywords', 512);
            $table->text('seo_meta_description');
            $table->string('seo_url_key', 255);

            // 时间戳
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrentOnUpdate()->comment('更新时间');

            // 索引
            $table->index('category_id', 'idx_category');
            $table->index('status', 'idx_status');
            $table->unique('seo_url_key', 'uk_seo_url');
            $table->index('created_at', 'idx_created_at');

        });

        // 表注释
        // DB::statement("ALTER TABLE `ba_blog_articles` COMMENT '博客文章表'");
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        Schema::dropIfExists('blog_articles');
    }
};