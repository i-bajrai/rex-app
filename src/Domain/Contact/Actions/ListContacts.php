<?php

declare(strict_types=1);

namespace Domain\Contact\Actions;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

final class ListContacts
{
    public const int DEFAULT_LIMIT = 50;

    /**
     * @return Collection<int, Contact>
     */
    public function execute(int $limit = self::DEFAULT_LIMIT): Collection
    {
        return Contact::query()
            ->withCount(['phones', 'emails'])->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
