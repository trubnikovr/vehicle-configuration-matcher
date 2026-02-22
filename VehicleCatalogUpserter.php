<?php
declare(strict_types=1);

namespace App\Services\Vehicles;

use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleConfiguration;
use App\Models\VehicleConfigurationLink;
use App\Services\Vehicles\DTO\VehicleCatalogDTO;
use Illuminate\Support\Facades\DB;

final class VehicleCatalogUpserter
{
    /**
     * @return array{brands:int, models:int, configurations:int, updated_prices:int, updated_tokens:int}
     */
    public function upsert(VehicleCatalogDTO $catalog, bool $forceTokens = false): array
    {
        return DB::transaction(function () use ($catalog, $forceTokens) {
            $insertedBrands = 0;
            $insertedModels = 0;
            $insertedConfigs = 0;
            $updatedPrices = 0;
            $updatedTokens = 0;

            $brandMap = VehicleBrand::query()
                ->pluck('id', 'name')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($catalog->brands as $brandDto) {
                $brandName = preg_replace('/\s+/u', ' ', trim($brandDto->name));
                $brandName = mb_convert_case($brandName, MB_CASE_TITLE, 'UTF-8');

                $brandId = $brandMap[$brandName] ?? null;
                if (!$brandId) {
                    $brand = VehicleBrand::query()->create(['name' => $brandName]);
                    $brandId = (int) $brand->id;
                    $brandMap[$brandName] = $brandId;
                    $insertedBrands++;
                }

                $modelMap = VehicleModel::query()
                    ->where('brand_id', $brandId)
                    ->pluck('id', 'name')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($brandDto->models as $modelDto) {
                    $modelName = preg_replace('/\s+/u', ' ', trim($modelDto->name));
                    $modelName = mb_convert_case($modelName, MB_CASE_TITLE, 'UTF-8');

                    $modelId = $modelMap[$modelName] ?? null;
                    if (!$modelId) {
                        $model = VehicleModel::query()->create([
                            'brand_id' => $brandId,
                            'name' => $modelName,
                        ]);
                        $modelId = (int) $model->id;
                        $modelMap[$modelName] = $modelId;
                        $insertedModels++;
                    }

                    $cfgMap = VehicleConfiguration::query()
                        ->where('model_id', $modelId)
                        ->get(['id', 'name', 'tokens', 'token_key'])
                        ->keyBy('name');

                    foreach ($modelDto->configs as $cfgDto) {
                        $cfgName = preg_replace('/\s+/u', ' ', trim($cfgDto->name));
                        $cfgName = mb_convert_case($cfgName, MB_CASE_TITLE, 'UTF-8');

                        if (!$cfgMap->has($cfgName)) {
                            $tok = VehicleConfigTokenizer::tokenize($brandName, $modelName, $cfgName);

                            $cfg = VehicleConfiguration::query()->create([
                                'model_id'   => $modelId,
                                'name'       => $cfgName,
                                'model_key'  => $tok->modelKey,
                                'tokens'     => $tok->tokens,
                                'brand_key'  => $tok->brandKey,
                                'token_key'  => $tok->tokenKey,
                            ]);

                            if ($cfgDto->companyId && $cfgDto->externalId !== null) {
                                VehicleConfigurationLink::firstOrCreate([
                                    'configuration_id' => $cfg->id,
                                    'company_id'       => $cfgDto->companyId,
                                    'api_id'           => $cfgDto->externalId,
                                ]);
                            }

                            $insertedConfigs++;
                            continue;
                        }

                        $cfg = $cfgMap->get($cfgName);

                        // токены для существующих
                        $hasTokens = !empty($cfg->token_key) || !empty($cfg->tokens);
                        if ($forceTokens || !$hasTokens) {
                            $tok = VehicleConfigTokenizer::tokenize($brandName, $modelName, $cfgName);

                            $cfg->update([
                                'tokens' => $tok->tokens,
                                'token_key' => $tok->tokenKey,
                            ]);
                            $updatedTokens++;
                        }

                        // цены (если появятся/нужны)
                        // if ($cfgDto->price !== null && $cfg->price !== $cfgDto->price) { ... $updatedPrices++; }
                    }
                }
            }

            return [
                'brands' => $insertedBrands,
                'models' => $insertedModels,
                'configurations' => $insertedConfigs,
                'updated_prices' => $updatedPrices,
                'updated_tokens' => $updatedTokens,
            ];
        });
    }
}
