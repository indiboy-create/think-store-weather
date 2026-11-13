<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

/**
 * 省/市站点树数据访问。
 *
 * 数据来自包内 src/data/province.php：省份 id 1-34、pid=0，
 * 城市以省份 id 为 pid，code 即中国天气网所需的 stationid。
 */
final class Province
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $tree = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tree(): array
    {
        if (self::$tree === null) {
            /** @var array<int, array<string, mixed>> $data */
            $data = (array) require dirname(__DIR__) . '/data/province.php';
            self::$tree = $data;
        }

        return self::$tree;
    }

    /**
     * 按省份名称或编码查找省份节点。
     *
     * @return array<string, mixed>|null
     */
    public static function findProvince(string $keyword): ?array
    {
        foreach (self::tree() as $province) {
            if (($province['title'] ?? '') === $keyword || ($province['code'] ?? '') === $keyword) {
                return $province;
            }
        }

        return null;
    }

    /**
     * 按站点编码定位所属省份与城市。
     *
     * @return array{province: array<string, mixed>, city: array<string, mixed>}|null
     */
    public static function locate(string $stationCode): ?array
    {
        foreach (self::tree() as $province) {
            foreach ((array) ($province['children'] ?? []) as $city) {
                if (($city['code'] ?? '') === $stationCode) {
                    return ['province' => $province, 'city' => $city];
                }
            }
        }

        return null;
    }
}