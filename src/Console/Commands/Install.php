<?php
/**
 * 
 * This file is auto generate by Nicelizhi\Apps\Commands\Create
 * @author Steve
 * @date 2025-04-11 09:02:26
 * @link https://github.com/xxxl4
 * 
 */
namespace NexaMerchant\Blog\Console\Commands;

use NexaMerchant\Apps\Console\Commands\CommandInterface;

class Install extends CommandInterface 

{
    protected $signature = 'Blog:install';

    protected $description = 'Install Blog an app';

    public function getAppVer() {
        return config("Blog.version");
    }

    public function getAppName() {
        return config("Blog.name");
    }

    public function handle()
    {
        $this->info("Install app: Blog");
        if (!$this->confirm('Do you wish to continue?')) {
            // ...
            $this->error("App Blog Install cannelled");
            return false;
        }
    }
}