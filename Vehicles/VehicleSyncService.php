<?php
declare(strict_types=1);
namespace App\Services\Vehicles;


use App\Services\Vehicles\Providers\GrossVehicleCatalogProvider;
use App\Services\Vehicles\Providers\InsonVehicleCatalogProvider;
use App\Services\Vehicles\Providers\KapitalVehicleCatalogProvider;
use App\Services\Vehicles\Providers\NeoVehicleCatalogProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

final class VehicleSyncService
{
    public function __construct(
        private readonly NeoVehicleCatalogProvider $neoVehicleCatalogProvider,
        private readonly VehicleCatalogUpserter $upserter,
        private readonly GrossVehicleCatalogProvider $grossVehicleCatalogProvider,
        private readonly VehicleExternalConfigMapper $vehicleExternalConfigMapper,
        private readonly InsonVehicleCatalogProvider $insonVehicleCatalogProvider,
        private readonly KapitalVehicleCatalogProvider $kapitalVehicleCatalogProvider,
    ) {}

    public function main(bool $force = false): array
    {
        try {
            $catalog = $this->grossVehicleCatalogProvider->fetchCatalog();
            if (empty($catalog->brands)) {
                return ['brands' => 0, 'models' => 0, 'configurations' => 0, 'updated_prices' => 0, 'updated_tokens' => 0];
            }

            $result = $this->upserter->upsert($catalog, $force);

            Log::info('Vehicle sync done', $result);
            return $result;
        } catch (Throwable $e) {
            Log::error('Vehicle sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function restSync(bool $force = false): void
    {
            $this->kapital();
    }

    public function inson(bool $force = false): array
    {
        try {
            $catalog = $this->insonVehicleCatalogProvider->fetchCatalog();

            $stats = [
                'total' => 0,
                'linked' => 0,
                'created_brands_models_configs' => 0, // если хочешь — можно детализировать внутри mapper
                'no_api_id' => 0,
            ];

            foreach ($catalog->brands as $brandDto) {
                foreach ($brandDto->models as $modelDto) {
                    foreach ($modelDto->configs as $cfgDto) {
                        $stats['total']++;

                        if ($cfgDto->externalId === null) {
                            $stats['no_api_id']++;
                            continue;
                        }

                        // ВАЖНО: если в DTO brand/model/config уже нормализованы — ок.
                        // Если нет — mapper сам нормализует.
                        $this->vehicleExternalConfigMapper->mapOne(
                            brandName: $brandDto->name,
                            modelName: $modelDto->name,
                            configName: $cfgDto->name,
                            companyId: $cfgDto->companyId,
                            apiId: $cfgDto->externalId,
                            //meta: $cfgDto->meta ?? [],
                        );

                        $stats['linked']++;
                    }
                }
            }

            Log::info('Gross vehicle mapping done', $stats);
            return $stats;

        } catch (Throwable $e) {
            Log::error('Gross vehicle mapping failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }



    public function neo(bool $force = false): array
    {
        try {
            $catalog = $this->neoVehicleCatalogProvider->fetchCatalog();

            $stats = [
                'total' => 0,
                'linked' => 0,
                'created_brands_models_configs' => 0, // если хочешь — можно детализировать внутри mapper
                'no_api_id' => 0,
            ];

            foreach ($catalog->brands as $brandDto) {
                foreach ($brandDto->models as $modelDto) {
                    foreach ($modelDto->configs as $cfgDto) {
                        $stats['total']++;

                        if ($cfgDto->externalId === null) {
                            $stats['no_api_id']++;
                            continue;
                        }

                        // ВАЖНО: если в DTO brand/model/config уже нормализованы — ок.
                        // Если нет — mapper сам нормализует.
                        $this->vehicleExternalConfigMapper->mapOne(
                            brandName: $brandDto->name,
                            modelName: $modelDto->name,
                            configName: $cfgDto->name,
                            companyId: $cfgDto->companyId,
                            apiId: $cfgDto->externalId,
                          //  meta: $cfgDto->meta ?? [],
                        );

                        $stats['linked']++;
                    }
                }
            }

            Log::info('Gross vehicle mapping done', $stats);
            return $stats;

        } catch (Throwable $e) {
            Log::error('Gross vehicle mapping failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function kapital(bool $force = false): array
    {
        try {
            $catalog = $this->kapitalVehicleCatalogProvider->fetchCatalog();

            $stats = [
                'total' => 0,
                'linked' => 0,
                'created_brands_models_configs' => 0, // если хочешь — можно детализировать внутри mapper
                'no_api_id' => 0,
            ];

            foreach ($catalog->brands as $brandDto) {
                foreach ($brandDto->models as $modelDto) {
                    foreach ($modelDto->configs as $cfgDto) {
                        $stats['total']++;

                        if ($cfgDto->externalId === null) {
                            $stats['no_api_id']++;
                            continue;
                        }

                        // ВАЖНО: если в DTO brand/model/config уже нормализованы — ок.
                        // Если нет — mapper сам нормализует.
                        $this->vehicleExternalConfigMapper->mapOne(
                            brandName: $brandDto->name,
                            modelName: $modelDto->name,
                            configName: $cfgDto->name,
                            companyId: $cfgDto->companyId,
                            apiId: $cfgDto->externalId,
                        //  meta: $cfgDto->meta ?? [],
                        );

                        $stats['linked']++;
                    }
                }
            }

            Log::info('Gross vehicle mapping done', $stats);
            return $stats;

        } catch (Throwable $e) {
            Log::error('Gross vehicle mapping failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
