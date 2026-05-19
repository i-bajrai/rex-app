<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CallOutcomeResource;
use Domain\Contact\Actions\PlaceCallToContact;

final class PlaceCallController
{
    public function __invoke(int $contact, PlaceCallToContact $action): CallOutcomeResource
    {
        return new CallOutcomeResource($action->execute($contact));
    }
}
