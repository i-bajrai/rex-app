<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Contacts\SearchContactsRequest;
use App\Http\Resources\Api\V1\ContactResource;
use Domain\Contact\Actions\SearchContacts;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SearchContactsController
{
    public function __invoke(SearchContactsRequest $request, SearchContacts $action): AnonymousResourceCollection
    {
        return ContactResource::collection($action->execute($request->toCriteria()));
    }
}
