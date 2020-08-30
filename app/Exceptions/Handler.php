<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        FileIOException::class,
        HttpException::class,
        InvalidTokenCategoryException::class,
        InvalidTokenDataException::class,
        ModelNotFoundException::class,
        NotSupportedException::class,
        TokenExpiredException::class,
        TokenNotFoundException::class,
        TokenNotVerifiedException::class,
        UserNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof ValidationException) {
            return [
                'success' => false,
                'errors' => $exception->errors(),
            ];
        } else if ($exception instanceof NotSupportedException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof FileIOException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof InvalidTokenCategoryException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof InvalidTokenDataException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof TokenExpiredException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof TokenNotFoundException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof TokenNotVerifiedException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        } else if ($exception instanceof UserNotFoundException) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
        return parent::render($request, $exception);
    }
}
