<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, \Closure $next)
    {
        $response = parent::handle($request, $next);

        // No procesar respuestas de descarga de archivos
        if (
            $response instanceof StreamedResponse ||
            $response instanceof BinaryFileResponse ||
            $response->headers->has('Content-Disposition')
        ) {
            return $response;
        }

        return $response;
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->toArray() : null,
                'role' => $user ? $user->getRoleNames()->first() : null,
            ],

            'can' => $user
                ? $user->getAllPermissions()->pluck('name')->mapWithKeys(fn($name) => [$name => true])
                : [],

            // Pull (no get): consume el flash en cuanto se lee la primera vez en este request.
            // Evita que partial reloads concurrentes lo re-emitan y disparen el toast en loop.
            'alert' => fn() => $request->session()->pull('alert'),

            'errors' => function () use ($request) {
                $errors = $request->session()->get('errors');
                if (!$errors) {
                    return new \stdClass();
                }

                $messages = $errors->getBag('default')->getMessages();
                $flattenedErrors = [];

                foreach ($messages as $field => $fieldErrors) {
                    $flattenedErrors[$field] = is_array($fieldErrors) ? $fieldErrors[0] : $fieldErrors;
                }

                return (object) $flattenedErrors;
            },

        ]);
    }
}
