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

class UnInstall extends CommandInterface 

{
    protected $signature = 'Blog:uninstall';

    protected $description = 'Uninstall Blog an app';

    public function getAppVer() {
        return config("Blog.version");
    }

    public function getAppName() {
        return config("Blog.name");
    }

    public function handle()
    {
        if (!$this->confirm('Do you wish to continue?')) {
            // ...
            $this->error("App Blog UnInstall cannelled");
            return false;
        }
    }
}