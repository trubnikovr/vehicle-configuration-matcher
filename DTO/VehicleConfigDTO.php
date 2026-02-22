<?php
namespace App\Services\Vehicles\DTO;

final class VehicleConfigDTO
{
    public function __construct(
        public string $name,
        public ?string $externalId = null,   // api_id из источника
        public ?int $companyId = null,       // для линка (например 6)
        public ?int $price = null,           // на будущее
        public array $meta = [],             // любые доп. поля
    ) {}
}
