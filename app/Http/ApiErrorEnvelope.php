<?php

declare(strict_types=1);

namespace App\Http;

use Domain\Contact\Exceptions\ContactHasNoPhoneException;
use Domain\Contact\Exceptions\ContactNoIdentifiersException;
use Domain\Contact\Exceptions\ContactNotFoundException;
use Domain\Contact\Exceptions\DuplicateContactEmailException;
use Domain\Contact\Exceptions\DuplicateContactPhoneException;
use Domain\Contact\Exceptions\InvalidEmailAddressException;
use Domain\Contact\Exceptions\InvalidPhoneNumberException;
use Domain\Contact\Exceptions\NoSearchCriteriaException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiErrorEnvelope
{
    public static function shouldHandle(Request $request): bool
    {
        if ($request->is('api/*')) {
            return true;
        }

        return $request->expectsJson();
    }

    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! self::shouldHandle($request)) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => self::validation($exception),
            $exception instanceof InvalidPhoneNumberException => self::invalidPhone($exception),
            $exception instanceof InvalidEmailAddressException => self::invalidEmail($exception),
            $exception instanceof DuplicateContactPhoneException => self::duplicatePhone($exception),
            $exception instanceof DuplicateContactEmailException => self::duplicateEmail($exception),
            $exception instanceof ContactNoIdentifiersException => self::noIdentifiers($exception),
            $exception instanceof ContactHasNoPhoneException => self::noPhone($exception),
            $exception instanceof NoSearchCriteriaException => self::noSearchCriteria($exception),
            $exception instanceof ContactNotFoundException => self::notFound($exception->id, $exception->getMessage()),
            $exception instanceof NotFoundHttpException => self::notFoundFromRoute($request),
            $exception instanceof ThrottleRequestsException => self::rateLimited($exception),
            $exception instanceof HttpException => self::http($exception),
            default => null,
        };
    }

    private static function validation(ValidationException $exception): JsonResponse
    {
        /** @var array<string, array<int, string>> $errors */
        $errors = $exception->errors();
        /** @var array<string, array<string, array<int, mixed>>> $failedRules */
        $failedRules = $exception->validator->failed();

        return self::respond(422, [
            'code' => 'validation_failed',
            'message' => 'The given data was invalid.',
            'details' => self::buildValidationDetails($errors, $failedRules),
        ]);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, array<string, array<int, mixed>>>  $failedRules
     * @return list<array{field: string, code: string, message: string}>
     */
    private static function buildValidationDetails(array $errors, array $failedRules): array
    {
        return array_values(Collection::make($errors)
            ->flatMap(static fn (array $messages, string $field): array => Collection::make($messages)
                ->values()
                ->map(static fn (string $message, int $index): array => [
                    'field' => $field,
                    'code' => self::normaliseRuleCode(
                        Collection::make($failedRules[$field] ?? [])->keys()->get($index, 'invalid'),
                    ),
                    'message' => $message,
                ])
                ->all())
            ->all());
    }

    private static function normaliseRuleCode(mixed $ruleName): string
    {
        $short = Str::of(is_string($ruleName) ? $ruleName : 'invalid')
            ->afterLast('\\')
            ->before(':')
            ->snake()
            ->toString();

        return $short === 'distinct' ? 'duplicate' : $short;
    }

    private static function invalidPhone(InvalidPhoneNumberException $exception): JsonResponse
    {
        $details = ['phone' => $exception->value];

        if ($exception->errorCode === 'unsupported_region') {
            $details['supported_regions'] = $exception->supportedRegions;
        }

        return self::respond(422, [
            'code' => sprintf('contact.phone.%s', $exception->errorCode),
            'message' => $exception->getMessage(),
            'details' => $details,
        ]);
    }

    private static function invalidEmail(InvalidEmailAddressException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => sprintf('contact.email.%s', $exception->errorCode),
            'message' => $exception->getMessage(),
            'details' => ['email' => $exception->value],
        ]);
    }

    private static function duplicatePhone(DuplicateContactPhoneException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'details' => ['phone' => $exception->value],
        ]);
    }

    private static function duplicateEmail(DuplicateContactEmailException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'details' => ['email' => $exception->value],
        ]);
    }

    private static function noIdentifiers(ContactNoIdentifiersException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'details' => (object) [],
        ]);
    }

    private static function noPhone(ContactHasNoPhoneException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'details' => ['id' => $exception->id],
        ]);
    }

    private static function noSearchCriteria(NoSearchCriteriaException $exception): JsonResponse
    {
        return self::respond(422, [
            'code' => $exception->errorCode,
            'message' => $exception->getMessage(),
            'details' => (object) [],
        ]);
    }

    private static function notFound(int $id, string $message): JsonResponse
    {
        return self::respond(404, [
            'code' => 'contact.not_found',
            'message' => $message,
            'details' => ['id' => $id],
        ]);
    }

    private static function notFoundFromRoute(Request $request): JsonResponse
    {
        $segments = $request->segments();
        $candidate = end($segments);
        $id = is_string($candidate) && ctype_digit($candidate) ? (int) $candidate : 0;

        return self::notFound($id, sprintf('Contact [%d] was not found.', $id));
    }

    private static function rateLimited(ThrottleRequestsException $exception): JsonResponse
    {
        $header = $exception->getHeaders()['Retry-After'] ?? 60;
        $retryAfter = is_numeric($header) ? (int) $header : 60;

        return self::respond(429, [
            'code' => 'contact.call.rate_limited',
            'message' => 'Too many call attempts. Please retry shortly.',
            'details' => ['retry_after_seconds' => $retryAfter],
        ], [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    private static function http(HttpException $exception): JsonResponse
    {
        return self::respond($exception->getStatusCode(), [
            'code' => 'http_error',
            'message' => $exception->getMessage() === '' ? Response::$statusTexts[$exception->getStatusCode()] : $exception->getMessage(),
            'details' => (object) [],
        ]);
    }

    /**
     * @param  array{code: string, message: string, details: array<int|string, mixed>|object}  $error
     * @param  array<string, string>  $headers
     */
    private static function respond(int $status, array $error, array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
