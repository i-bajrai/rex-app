<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Contacts\UpsertContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use Domain\Contact\Actions\DeleteContact;
use Domain\Contact\Actions\GetContact;
use Domain\Contact\Actions\ListContacts;
use Domain\Contact\Actions\UpsertContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ContactController
{
    public function index(ListContacts $action): AnonymousResourceCollection
    {
        return ContactResource::collection($action->execute());
    }

    public function show(int $contact, GetContact $action): ContactResource
    {
        return new ContactResource($action->execute($contact));
    }

    public function store(UpsertContactRequest $request, UpsertContact $action): JsonResponse
    {
        $contact = $action->execute($request->toContactData());

        return new ContactResource($contact)
            ->response()
            ->setStatusCode(201)
            ->header('Location', sprintf('/api/v1/contacts/%d', $contact->id));
    }

    public function update(int $contact, UpsertContactRequest $request, UpsertContact $action): ContactResource
    {
        return new ContactResource($action->execute($request->toContactData(), $contact));
    }

    public function destroy(int $contact, DeleteContact $action, GetContact $getContact): Response
    {
        $getContact->execute($contact);
        $action->execute($contact);

        return response()->noContent();
    }
}
