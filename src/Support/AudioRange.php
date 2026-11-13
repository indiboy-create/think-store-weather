<?php

declare(strict_types=1);

namespace Youvision\ThinkStoreWeather\Support;

/**
 * HTTP Range 解析。
 *
 * 只处理单段 `bytes=` 请求，非法或越界时返回 null，由调用方回退为完整响应。
 */
final class AudioRange
{
    /**
     * @return array{start: int, end: int, partial: bool}|null
     */
    public static function parse(?string $header, int $size): ?array
    {
        if ($size <= 0 || $header === null || trim($header) === '') {
            return null;
        }

        if (!preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $matches)) {
            return null;
        }

        $startRaw = $matches[1];
        $endRaw   = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return null;
        }

        if ($startRaw === '') {
            $suffix = (int) $endRaw;

            if ($suffix <= 0) {
                return null;
            }

            $start = max(0, $size - $suffix);
            $end   = $size - 1;
        } else {
            $start = (int) $startRaw;
            $end   = $endRaw === '' ? $size - 1 : min((int) $endRaw, $size - 1);
        }

        if ($start >= $size || $start > $end) {
            return null;
        }

        return ['start' => $start, 'end' => $end, 'partial' => true];
    }
}