<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    Modules\GestionProyectos\Providers\GestionProyectosServiceProvider::class,
    Modules\Role\Providers\RoleServiceProvider::class,
    Modules\User\Providers\UserServiceProvider::class,
];
