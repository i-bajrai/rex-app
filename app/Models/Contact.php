<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactPhone> $phones
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactEmail> $emails
 * @property-read ContactPhone|null $primaryPhone
 */
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<ContactPhone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class);
    }

    /**
     * @return HasMany<ContactEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class);
    }

    /**
     * @return HasOne<ContactPhone, $this>
     */
    public function primaryPhone(): HasOne
    {
        return $this->hasOne(ContactPhone::class)->oldestOfMany('created_at');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
