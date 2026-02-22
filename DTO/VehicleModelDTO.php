<?php
namespace App\Services\Vehicles\DTO;

final class VehicleModelDTO
{
    /** @param VehicleConfigDTO[] $configs */
    public function __construct(
        public string $name,
        public array $configs,
    ) {}
}
