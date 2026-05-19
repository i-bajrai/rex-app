<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

it('only places Contact domain classes in approved sub-namespaces', function (): void {
    $allowedSubNamespaces = [
        'Actions',
        'DataTransferObjects',
        'ValueObjects',
        'Exceptions',
        'Enums',
        'Contracts',
        'Gateways',
    ];

    $finder = (new Finder)
        ->files()
        ->in(base_path('src/Domain/Contact'))
        ->name('*.php');

    $offenders = Collection::make(iterator_to_array($finder, false))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->reject(fn (string $relative): bool => str_starts_with($relative, '.'))
        ->reject(function (string $relative) use ($allowedSubNamespaces): bool {
            $segments = explode(DIRECTORY_SEPARATOR, $relative);
            $head = $segments[0];

            return in_array($head, $allowedSubNamespaces, true);
        })
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('forbids the Contact controllers from referencing Contact Eloquent models', function (): void {
    $controllerPaths = [
        app_path('Http/Controllers/Api/V1/ContactController.php'),
        app_path('Http/Controllers/Api/V1/SearchContactsController.php'),
        app_path('Http/Controllers/Api/V1/PlaceCallController.php'),
    ];

    $offenders = Collection::make($controllerPaths)
        ->filter(fn (string $path): bool => file_exists($path))
        ->filter(function (string $path): bool {
            $contents = (string) file_get_contents($path);

            return str_contains($contents, Contact::class)
                || str_contains($contents, 'use App\\Models\\Contact');
        })
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('forbids App\\Jobs classes from referencing Contact Eloquent models', function (): void {
    $jobsDirectory = app_path('Jobs');

    if (! is_dir($jobsDirectory)) {
        expect(true)->toBeTrue();

        return;
    }

    $finder = (new Finder)
        ->files()
        ->in($jobsDirectory)
        ->name('*.php');

    $offenders = Collection::make(iterator_to_array($finder, false))
        ->filter(function ($file): bool {
            $contents = (string) file_get_contents($file->getPathname());

            return str_contains($contents, Contact::class)
                || str_contains($contents, ContactPhone::class)
                || str_contains($contents, ContactEmail::class);
        })
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
