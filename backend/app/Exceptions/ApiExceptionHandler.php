<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Render an API exception into the documented error envelope.
     */
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => self::error(
                'VALIDATION_ERROR',
                'Data yang dikirim tidak valid.',
                422,
                $exception->errors(),
            ),
            $exception instanceof InsufficientStockException => self::error(
                'INSUFFICIENT_STOCK',
                $exception->getMessage(),
                409,
                $exception->details,
            ),
            $exception instanceof InsufficientPaymentException => self::error(
                'INSUFFICIENT_PAYMENT',
                $exception->getMessage(),
                422,
                [
                    'total_amount' => $exception->totalAmount,
                    'amount_paid' => $exception->amountPaid,
                ],
            ),
            $exception instanceof ItemNotFoundException => self::error(
                'NOT_FOUND',
                $exception->getMessage(),
                404,
                ['item_ids' => $exception->itemIds],
            ),
            $exception instanceof AuthenticationException => self::error(
                'UNAUTHENTICATED',
                self::authenticationMessage($exception),
                401,
            ),
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException,
            $exception instanceof HttpException && $exception->getStatusCode() === 403 => self::error(
                'FORBIDDEN',
                'Anda tidak memiliki akses ke sumber daya ini.',
                403,
            ),
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException,
            $exception instanceof HttpException && $exception->getStatusCode() === 404 => self::error(
                'NOT_FOUND',
                'Sumber daya tidak ditemukan.',
                404,
            ),
            $exception instanceof ThrottleRequestsException,
            $exception instanceof HttpException && $exception->getStatusCode() === 429 => self::error(
                'RATE_LIMITED',
                'Terlalu banyak percobaan. Silakan coba lagi nanti.',
                429,
            ),
            default => self::error(
                'INTERNAL_ERROR',
                'Terjadi kesalahan pada server.',
                500,
            ),
        };
    }

    /**
     * Build the documented error response body.
     *
     * @param  array<string, mixed>|null  $details
     */
    private static function error(string $code, string $message, int $status, ?array $details = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    /**
     * Keep authentication failures generic in Indonesian.
     */
    private static function authenticationMessage(AuthenticationException $exception): string
    {
        if ($exception->getMessage() === 'Unauthenticated.') {
            return 'Autentikasi diperlukan. Silakan login.';
        }

        return $exception->getMessage();
    }
}
