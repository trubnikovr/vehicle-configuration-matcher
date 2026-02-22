<?php
declare(strict_types=1);

namespace App\Services\Vehicles\Providers;

use App\Integrations\KapitalInsurance\KapitalInsuranceService;
use App\Integrations\KapitalInsurance\Resources\Kasko\Requests\KaskoVehicleRequest;
use App\Services\Vehicles\Contracts\VehicleCatalogProvider;
use App\Services\Vehicles\DTO\VehicleCatalogDTO;
use App\Services\Vehicles\DTO\VehicleBrandDTO;
use App\Services\Vehicles\DTO\VehicleModelDTO;
use App\Services\Vehicles\DTO\VehicleConfigDTO;

final class KapitalVehicleCatalogProvider implements VehicleCatalogProvider
{

    public function __construct(
        private readonly KapitalInsuranceService $kapitalInsuranceService,
    ) {}
    function key():int
    {
        return 2;
    }

    public function fetchCatalog(): VehicleCatalogDTO
    {
         // 1) Марки
        $brandsRaw = $this->kapitalInsuranceService->kasko()->kaskoVehicleMarka();

        $brands = [];

        foreach ($brandsRaw as $brandResp) {
            $brandId = (int) $brandResp->id;
            $brandName = $this->norm((string) $brandResp->name);
            if ($brandId <= 0 || $brandName === '') continue;

            // 2) Модели по марке
            $modelsRaw = $this->kapitalInsuranceService
                ->kasko()
                ->kaskoVehicleModel(new KaskoVehicleRequest(id: $brandId));

            $models = [];

            foreach ($modelsRaw as $modelResp) {
                $modelId = (int) $modelResp->id;
                $modelName = $this->norm((string) $modelResp->name);
                if ($modelId <= 0 || $modelName === '') continue;

                // 3) Так как "конфигов нет" — делаем 1 конфиг на модель (забиваем модель в конфиг)
                $configs = [
                    new VehicleConfigDTO(
                        name: $modelName,
                        externalId: (string) $modelId, // externalId = id модели у Kapital
                        companyId: $this->key(),
                        price: null,
                        meta: [
                            'kapital_brand_id' => $brandId,
                            'kapital_model_id' => $modelId,
                            'until_five_year'  => $modelResp->until_five_year ?? null,
                            'after_five_year'  => $modelResp->after_five_year ?? null,
                            'electricCar'      => $modelResp->electricCar ?? null,
                        ],
                    ),
                ];

                $models[] = new VehicleModelDTO($modelName, $configs);
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
