<?php
declare(strict_types=1);

namespace App\Services\Vehicles\Contracts;

use App\Services\Vehicles\DTO\VehicleCatalogDTO;

interface VehicleCatalogProvider
{
    public function fetchCatalog(): VehicleCatalogDTO;
}
