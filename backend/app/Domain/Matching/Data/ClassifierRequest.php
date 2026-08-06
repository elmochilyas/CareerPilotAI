<?php

namespace App\Domain\Matching\Data;

final readonly class ClassifierRequest
{
    /**
     * @param  list<ClassifierRequestItem>  $items
     * @param  list<array{id: int, type: string, title: string, organization: string|null, description: string|null}>  $profileItems
     */
    public function __construct(
        public array $items,
        public array $profileItems,
        public ?string $requestId = null,
    ) {}
}
