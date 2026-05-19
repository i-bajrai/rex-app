<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Domain\Contact\DataTransferObjects\ContactSearchCriteria;
use Domain\Contact\Exceptions\NoSearchCriteriaException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class SearchContacts
{
    public const int DEFAULT_LIMIT = 100;

    /**
     * @return Collection<int, Contact>
     */
    public function execute(ContactSearchCriteria $criteria, int $limit = self::DEFAULT_LIMIT): Collection
    {
        throw_if($criteria->isEmpty(), NoSearchCriteriaException::class);

        return Contact::query()
            ->withCount(['phones', 'emails'])
            ->when($criteria->name, fn (Builder $query, string $name): Builder => $query->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($name).'%']))
            ->when($criteria->phone, fn (Builder $query): Builder => $query->whereIn(
                'id',
                ContactPhone::query()->where('e164', (string) $criteria->phone)->select('contact_id'),
            ))
            ->when($criteria->emailDomain, fn (Builder $query, string $domain): Builder => $query->whereIn(
                'id',
                ContactEmail::query()->where('address_domain', mb_strtolower($domain))->select('contact_id'),
            ))->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
