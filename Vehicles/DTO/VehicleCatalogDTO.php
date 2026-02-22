<?php
namespace App\Services\Vehicles\DTO;

final class VehicleCatalogDTO
{
    /** @param VehicleBrandDTO[] $brands */
    public function __construct(public array $brands) {}
}
