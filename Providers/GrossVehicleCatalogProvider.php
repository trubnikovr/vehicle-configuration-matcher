<?php
declare(strict_types=1);

namespace App\Services\Vehicles\Providers;

use App\Integrations\GrossInsurance\DataObjects\Kasko\AutoBrand;
use App\Integrations\GrossInsurance\DataObjects\Kasko\AutoComp;
use App\Integrations\GrossInsurance\DataObjects\Kasko\AutoModel;
use App\Integrations\GrossInsurance\GrossInsuranceService;
use App\Integrations\GrossInsurance\Resources\Kasko\Requests\GetAutoCompRequest;
use App\Integrations\GrossInsurance\Resources\Kasko\Requests\GetAutoModelRequest;
use App\Services\Vehicles\Contracts\VehicleCatalogProvider;
use App\Services\Vehicles\DTO\VehicleBrandDTO;
use App\Services\Vehicles\DTO\VehicleCatalogDTO;
use App\Services\Vehicles\DTO\VehicleConfigDTO;
use App\Services\Vehicles\DTO\VehicleModelDTO;


class GrossVehicleCatalogProvider implements VehicleCatalogProvider
{

    public function __construct(
        private readonly GrossInsuranceService $grossInsuranceService,
    ) {}

    public function key(): int
    {
        return 8;
    }

    public function fetchCatalog(): VehicleCatalogDTO
    {
        $brandsResp = $this->grossInsuranceService->kasko()->getAutoBrand();
        /** @var AutoBrand[] $brands */
        $brands = $brandsResp->response;

        $brandDtos = [];

        foreach ($brands as $brand) {
            $modelsResp = $this->grossInsuranceService->kasko()->getAutoModel(
                GetAutoModelRequest::fromArray(['autobrand_id' => $brand->id])
            );
            /** @var AutoModel[] $models */
            $models = $modelsResp->response ?? $modelsResp; // зависит от твоего SDK

            $modelDtos = [];

            foreach ($models as $model) {
                $compsResp = $this->grossInsuranceService->kasko()->getAutoComp(
                    GetAutoCompRequest::fromArray(['automodel_id' => $model->id])
                );
                /** @var AutoComp[] $comps */
                $comps = $compsResp->response ?? $compsResp;

                $cfgDtos = [];
                foreach ($comps as $comp) {
                    $cfgDtos[] = new VehicleConfigDTO(
                        name: join(' ',array_unique([$model->name, $comp->name])),
                        externalId: (string)$comp->id,
                        companyId: $this->key(), // твоя константа
                        price: null,
                        meta: ['raw' => $comp->toArray()] // опционально
                    );
                }

                $modelDtos[] = new VehicleModelDTO(
                    name: $model->name,
                    configs: $cfgDtos,
                );
            }

            $brandDtos[] = new VehicleBrandDTO(
                name: $brand->name,
                models: $modelDtos,
            );
        }

        return new VehicleCatalogDTO(brands: $brandDtos);
    }
}
