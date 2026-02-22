<?php
declare(strict_types=1);

namespace App\Services\Vehicles;

use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleConfiguration;
use App\Models\VehicleConfigurationLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VehicleExternalConfigMapper
{
    private ?int $companyId = null;

    /** @var array<string, Collection<int,VehicleConfiguration>> */
    private array $brandConfigsCache = [];

    public function mapOne(
        string $brandName,
        string $modelName,
        string $configName,
        int $companyId,
        ?string $apiId = null,
        int $threshold = 85, // один порог на всё
    ): int {
        $this->companyId = $companyId;

        return DB::transaction(function () use ($brandName, $modelName, $configName, $apiId, $threshold): int {

            $brandDisplay  = $this->normalizeTitle($brandName);
            $modelDisplay  = $this->normalizeTitle($modelName);
            $configDisplay = $this->normalizeSpaces($configName);

            $brandKey = VehicleConfigTokenizer::brandKey($brandName);

            // tokens уже включают токены модели (обязательно!)
            $tokCfg = VehicleConfigTokenizer::tokenize($brandName, $modelName, $configDisplay);

            // 1) конфиги бренда
            $configs = $this->getConfigsByBrandKey($brandKey);

            // 2) если вообще нет конфигов бренда — создаём всё
            if ($configs->isEmpty()) {
                $cfgId = $this->createAll($brandDisplay, $brandKey, $modelDisplay, $configDisplay, $tokCfg, $apiId);
                return $cfgId;
            }

            // 3) exact по token_key (самый быстрый и точный)
            $exact = $configs->firstWhere('token_key', $tokCfg->tokenKey);
            if ($exact) {
                if ($apiId !== null) $this->link((int)$exact->id, $apiId);
                return (int)$exact->id;
            }

            // 4) fuzzy по tokens среди всех конфигов бренда
            [$bestId, $bestScore] = $this->pickBestByTokens($tokCfg->tokens, $configs->all());

            if ($bestId !== null && $bestScore >= $threshold) {
                if ($apiId !== null) $this->link($bestId, $apiId);
                return $bestId;
            }

            // 5) не нашли — создаём новую модель + новый конфиг
            $cfgId = $this->createAll($brandDisplay, $brandKey, $modelDisplay, $configDisplay, $tokCfg, $apiId);

            // инвалидация кеша бренда
            unset($this->brandConfigsCache[$brandKey]);

            return $cfgId;
        });
    }

    private function getConfigsByBrandKey(string $brandKey): Collection
    {
        if (isset($this->brandConfigsCache[$brandKey])) {
            return $this->brandConfigsCache[$brandKey];
        }

        $configs = VehicleConfiguration::query()
            ->where('brand_key', $brandKey)
            ->get(['id', 'model_id', 'name', 'tokens', 'token_key', 'brand_key', 'model_key']);

        $this->brandConfigsCache[$brandKey] = $configs;
        return $configs;
    }

    private function createAll(
        string $brandDisplay,
        string $brandKey,
        string $modelDisplay,
        string $configDisplay,
        object $tokCfg,
        ?string $apiId
    ): int {
        $brand = VehicleBrand::query()->firstOrCreate(
        // лучше: ['brand_key'=>$brandKey] если колонка есть
            ['name' => $brandDisplay],
            ['name' => $brandDisplay]
        );

        $model = VehicleModel::query()->create([
            'brand_id' => (int)$brand->id,
            'name'     => $modelDisplay,
        ]);

        $cfg = VehicleConfiguration::query()->create([
            'model_id'   => (int)$model->id,
            'name'       => $configDisplay,
            'brand_key'  => $tokCfg->brandKey,
            'model_key'  => $tokCfg->modelKey,  // можешь оставить массив токенов модели или вообще убрать из логики
            'tokens'     => $tokCfg->tokens,    // ВАЖНО: тут уже есть токены модели
            'token_key'  => $tokCfg->tokenKey,
        ]);

        $cfgId = (int)$cfg->id;

        if ($apiId !== null) {
            $this->link($cfgId, $apiId);
        }

        return $cfgId;
    }

    private function link(int $configurationId, string $apiId): void
    {
        VehicleConfigurationLink::updateOrCreate(
            ['company_id' => $this->companyId, 'api_id' => $apiId],
            ['configuration_id' => $configurationId]
        );
    }

    // ТВОЯ функция (без изменений)
    private function pickBestByTokens(array $needleTokens, array $cands): array
    {
        $needle = array_values(array_unique($needleTokens));
        $needleCount = count($needle);
        $needleSet = array_flip($needle);

        $bestId = null;
        $bestScore = 0;

        foreach ($cands as $cand) {
            $candTokens = is_array($cand->tokens)
                ? $cand->tokens
                : (json_decode((string)$cand->tokens, true) ?: []);

            $candTokens = array_values(array_unique($candTokens));
            $candSet = array_flip($candTokens);

            if ($needleCount === 0 || count($candTokens) === 0) continue;

            $missing = 0;
            foreach ($needle as $t) {
                if (!isset($candSet[$t])) $missing++;
            }

            if ($missing === 0) {
                $extraCand = count($candTokens) - $needleCount;
                $score = 100 - min(max($extraCand, 0), 10);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = (int)$cand->id;
                }
                continue;
            }

            $hit = 0;
            foreach ($candTokens as $t) {
                if (isset($needleSet[$t])) $hit++;
            }

            if ($hit < 2) continue;

            $coverage = $hit / count($candTokens);
            $score = (int) round($coverage * 100);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int)$cand->id;
            }
        }

        return [$bestId, $bestScore];
    }

    private function normalizeSpaces(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return $s ?? trim($s);
    }

    private function normalizeTitle(string $s): string
    {
        $s = $this->normalizeSpaces($s);
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }
}
