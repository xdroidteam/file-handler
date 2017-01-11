<?php namespace XdroidTeam\FileHandler;

use Illuminate\Support\ServiceProvider;

class FileHandlerServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => base_path('database/migrations'),
        ], 'xdroidteam-file-handler');
    }

    public function register()
    {
        //
    }

}
