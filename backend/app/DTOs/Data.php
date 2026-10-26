<?php

namespace App\DTOs;

/**
 * Base dos DTOs: objetos imutáveis que levam dados validados do Controller para o Service.
 */
abstract readonly class Data
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : $value,
            get_object_vars($this),
        );
    }
}
