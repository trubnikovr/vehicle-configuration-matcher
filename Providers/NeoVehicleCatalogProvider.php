<?php
declare(strict_types=1);

namespace App\Services\Vehicles\Providers;

use App\Integrations\NeoInsurance\NeoInsuranceService;
use App\Services\Vehicles\Contracts\VehicleCatalogProvider;
use App\Services\Vehicles\DTO\VehicleCatalogDTO;
use App\Services\Vehicles\DTO\VehicleBrandDTO;
use App\Services\Vehicles\DTO\VehicleModelDTO;
use App\Services\Vehicles\DTO\VehicleConfigDTO;

final class NeoVehicleCatalogProvider implements VehicleCatalogProvider
{

    public function __construct(private readonly NeoInsuranceService $neo) {}

    public function key(): int {
        return  6;
    }


    public function fetchCatalog(): VehicleCatalogDTO
    {
        $brandsRaw = data_get($this->neo->kasko()->vehicles(), 'vehicles', []);
        $brands = [];

        foreach ($brandsRaw as $brandRow) {
            $brandName = $this->norm((string) data_get($brandRow, 'name', ''));
            if ($brandName === '') continue;

            $brandName = preg_replace('/\s+/u', ' ', trim($brandName));
            $brandName = mb_convert_case($brandName, MB_CASE_TITLE, 'UTF-8');

            $modelsRaw = data_get($brandRow, 'car_models', []);
            $models = [];

            foreach ($modelsRaw as $modelRow) {
                $modelName = $this->norm((string) data_get($modelRow, 'name', ''));
                if ($modelName === '') continue;


                $modelName = preg_replace('/\s+/u', ' ', trim($modelName));
                $modelName = mb_convert_case($modelName, MB_CASE_TITLE, 'UTF-8');

                $posRaw = data_get($modelRow, 'car_positions', []);
                $configs = [];


                foreach ($posRaw as $posRow) {
                    $cfgName = $this->norm((string) data_get($posRow, 'name', ''));
                    if ($cfgName === '') continue;

                    $cfgName = preg_replace('/\s+/u', ' ', trim($cfgName));
                    $cfgName = mb_convert_case($cfgName, MB_CASE_TITLE, 'UTF-8');


                    $configs[] = new VehicleConfigDTO(
                        name: join(' ',array_unique([$modelName, $cfgName])),
                        externalId: (string) data_get($posRow, 'id'),
                        companyId: $this->key(),
                        price: data_get($posRow, 'price') ? (int) data_get($posRow, 'price') : null,
                        meta: $posRow,
                    );
                }

                if ($configs) {
                    $models[] = new VehicleModelDTO($modelName, $configs);
                }
            }

            if ($models) {
                $brands[] = new VehicleBrandDTO($brandName, $models);
            }
        }

        return new VehicleCatalogDTO($brands);
    }

    private function norm(string $v): string
    {
        $v = trim($v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        return $v;
    }
}
