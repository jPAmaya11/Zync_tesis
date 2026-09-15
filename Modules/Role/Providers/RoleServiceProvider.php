<?php

namespace Modules\Role\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Role\Services\Contracts\RoleServiceInterface;
use Modules\Role\Services\RoleService;
use Modules\Role\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Role\Repositories\RoleRepository;

class RoleServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
$this->app->bind(
            RoleServiceInterface::class,
            RoleService::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );
    }

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
