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
        Schema::create('blog_categories', function (Blueprint $table) {
            // 主键
            $table->unsignedInteger('id')->autoIncrement()->comment('分类ID');

            // 基础信息
            $table->string('name', 255)->comment('分类名称');
            $table->text('description')->nullable()->comment('分类描述');

            // SEO信息
            $table->string('seo_meta_title', 512);
            $table->string('seo_meta_keywords', 512);
            $table->text('seo_meta_description');
            $table->string('seo_url_key', 255);

            // 时间戳
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrentOnUpdate()->comment('更新时间');

            // 索引
            $table->unique('seo_url_key', 'uk_seo_url');
            $table->index('name', 'idx_name');

        });

        // 表注释
        DB::statement("ALTER TABLE `blog_categories` COMMENT '博客分类表'");

        // 自增值设置（与原始SQL一致）
        DB::statement("ALTER TABLE `blog_categories` AUTO_INCREMENT=4");
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        Schema::dropIfExists('blog_categories');
    }
};