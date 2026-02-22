<?php
namespace App\Services\Vehicles\DTO;

final class VehicleBrandDTO
{
    /** @param VehicleModelDTO[] $models */
    public function __construct(
        public string $name,
        public array $models,
    ) {}
}
