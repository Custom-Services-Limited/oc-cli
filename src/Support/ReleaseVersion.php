<?php

namespace OpenCart\CLI\Support;

final class ReleaseVersion
{
    public const BASELINE = '1.0.2';

    public static function normalize(string $version): ?string
    {
        $version = trim($version);

        if ($version === '') {
            return null;
        }

        if (strpos($version, 'refs/tags/') === 0) {
            $version = substr($version, 10);
        }

        if (preg_match('/^v(?=\d)/', $version) === 1) {
            $version = substr($version, 1);
        }

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return null;
        }

        return $version;
    }

    public static function next(string $version): string
    {
        $normalized = self::normalize($version);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Invalid release version: ' . $version);
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $normalized));

        $patch++;
        if ($patch > 9) {
            $patch = 0;
            $minor++;
        }

        if ($minor > 9) {
            $minor = 0;
            $major++;
        }

        return $major . '.' . $minor . '.' . $patch;
    }

    /**
     * @param array<int, string> $tags
     */
    public static function selectLatest(array $tags, string $fallback = self::BASELINE): string
    {
        $latest = self::normalize($fallback) ?? self::BASELINE;

        foreach ($tags as $tag) {
            $normalized = self::normalize($tag);
            if ($normalized === null) {
                continue;
            }

            if (self::compare($normalized, $latest) > 0) {
                $latest = $normalized;
            }
        }

        return $latest;
    }

    private static function compare(string $left, string $right): int
    {
        [$leftMajor, $leftMinor, $leftPatch] = array_map('intval', explode('.', $left));
        [$rightMajor, $rightMinor, $rightPatch] = array_map('intval', explode('.', $right));

        foreach (
            [
                [$leftMajor, $rightMajor],
                [$leftMinor, $rightMinor],
                [$leftPatch, $rightPatch],
            ] as [$leftPart, $rightPart]
        ) {
            if ($leftPart < $rightPart) {
                return -1;
            }

            if ($leftPart > $rightPart) {
                return 1;
            }
        }

        return 0;
    }
}
