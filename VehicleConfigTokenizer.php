<?php
declare(strict_types=1);

namespace App\Services\Vehicles;

final class VehicleConfigTokenizer
{
    public static function brandKey(string $brand): string
    {
        $s = mb_strtolower(trim($brand));
        $s = str_replace('ё', 'е', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9а-я]+/iu', '', $s) ?? $s;

        return $s;
    }

    public static function modelTokens(string $modelName): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $modelName, -1, PREG_SPLIT_NO_EMPTY);

        $tokens = [];

        foreach ($parts as $p) {
            $p = $aliases[$p] ?? $p;
            $tokens[] = $p;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /** @return object{brandKey:string, modelKey:string, tokens:string[], tokenKey:string} */
    public static function tokenize(string $brandName, string $modelName, string $cfgName): object
    {
        $brandKey = self::brandKey($brandName);
        $modelKey = self::modelTokens($modelName);

        $s = mb_strtolower(trim($cfgName));
        $s = str_replace('ё', 'е', $s);
        $parts = preg_split('/[^\p{L}\p{N}.]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);

        $aliases = [
            'hibrid' => 'hybrid', 'гибрид' => 'hybrid', 'hybrid' => 'hybrid', 'dmi' => 'dmi',
            'avtomat' => 'automatic', 'автомат' => 'automatic', 'automatic' => 'automatic', 'at' => 'automatic',
            'mehanika' => 'manual', 'механика' => 'manual', 'manual' => 'manual', 'mt' => 'manual',
            'престиж' => 'prestige', 'prestige' => 'prestige',
            'комфорт' => 'comfort', 'comfort' => 'comfort',
            'flagship' => 'flagship', 'флагман' => 'flagship',
        ];

        $stop = ['комплектация', 'trim'];

        $tokens = $modelKey;

        foreach ($parts as $p) {
            if (in_array($p, $stop, true)) continue;

            $p = $aliases[$p] ?? $p;
            $tokens[] = $p;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        $tokenKey = implode('|', $tokens);

        return (object)[
            'brandKey' => $brandKey,
            'modelKey' => $modelKey,
            'tokens' => $tokens,
            'tokenKey' => $tokenKey,
        ];
    }
}
