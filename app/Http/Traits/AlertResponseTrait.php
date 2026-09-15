<?php

namespace App\Http\Traits;

trait AlertResponseTrait
{
    /**
     * Redireccionar con mensaje de éxito
     */
    protected function redirectWithSuccess(string $message, string $route = null)
    {
        $redirectRoute = $route ?? request()->route()->getName();

        // No usar queryParams para mantener URLs limpias
        $redirect = redirect()->route($redirectRoute);

        return $redirect->with('alert', [
            'type' => 'success',
            'message' => $message,
            'mode' => 'toast',
            'timer' => 3000
        ]);
    }

    /**
     * Redireccionar con mensaje de éxito y filtros preservados
     */
    protected function redirectWithSuccessAndFilters(string $message, array $filters = [], string $route = null)
    {
        $redirectRoute = $route ?? request()->route()->getName();

        // Usar POST redirect para preservar filtros
        return redirect()->route($redirectRoute)
            ->with('alert', [
                'type' => 'success',
                'message' => $message,
                'mode' => 'toast',
                'timer' => 3000
            ])
            ->with('filters', $filters);
    }

    /**
     * Redireccionar con errores de validación
     */
    protected function redirectWithValidationError(\Illuminate\Validation\ValidationException $exception)
    {
        return redirect()->back()
            ->withErrors($exception->validator)
            ->withInput()
            ->with('alert', [
                'type' => 'error',
                'message' => 'Verifica los datos ingresados',
                'timer' => 4000
            ]);
    }

    /**
     * Redireccionar con mensaje de error general
     */
    protected function redirectWithError(string $title, \Exception $exception)
    {
        return redirect()->back()
            ->withInput()
            ->with('alert', [
                'type' => 'error',
                'message' => $exception->getMessage(),
                'timer' => 4000
            ]);
    }

    /**
     * Respuesta JSON de éxito
     */
    protected function jsonSuccess(string $message, array $data = [], int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Respuesta JSON de error
     */
    protected function jsonError(string $message, array $errors = [], int $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }

    /**
     * Redireccionar hacia atrás con mensaje de éxito (método simplificado)
     */
    protected function backWithSuccess(string $message, int $timer = 3000)
    {
        return redirect()->back()->with('alert', [
            'type' => 'success',
            'message' => $message,
            'mode' => 'toast',
            'timer' => $timer
        ]);
    }

    /**
     * Redireccionar hacia atrás con mensaje de error (método simplificado)
     */
    protected function backWithError(string $message, int $timer = 4000)
    {
        return redirect()->back()->with('alert', [
            'type' => 'error',
            'message' => $message,
            'timer' => $timer
        ]);
    }

    /**
     * Redireccionar hacia atrás con mensaje de info (método simplificado)
     */
    protected function backWithInfo(string $message, int $timer = 3000)
    {
        return redirect()->back()->with('alert', [
            'type' => 'info',
            'message' => $message,
            'timer' => $timer
        ]);
    }

    /**
     * Redireccionar hacia atrás con mensaje de warning (método simplificado)
     */
    protected function backWithWarning(string $message, int $timer = 4000)
    {
        return redirect()->back()->with('alert', [
            'type' => 'warning',
            'message' => $message,
            'timer' => $timer
        ]);
    }
}
