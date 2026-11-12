<?php

namespace App\Support;

use InvalidArgumentException;

class OdontogramGeometryCatalog
{
    /**
     * Canonical surface order for persistence and comparison.
     *
     * @var array<int, string>
     */
    public const array SURFACE_ORDER = ['V', 'P', 'L', 'M', 'D', 'O', 'I'];

    /**
     * @return array{upper_right: array<int, int>, upper_left: array<int, int>, lower_right: array<int, int>, lower_left: array<int, int>}
     */
    public static function permanentQuadrants(): array
    {
        return [
            'upper_right' => [18, 17, 16, 15, 14, 13, 12, 11],
            'upper_left' => [21, 22, 23, 24, 25, 26, 27, 28],
            'lower_right' => [48, 47, 46, 45, 44, 43, 42, 41],
            'lower_left' => [31, 32, 33, 34, 35, 36, 37, 38],
        ];
    }

    /**
     * @return array{upper_right: array<int, int>, upper_left: array<int, int>, lower_right: array<int, int>, lower_left: array<int, int>}
     */
    public static function primaryQuadrants(): array
    {
        return [
            'upper_right' => [55, 54, 53, 52, 51],
            'upper_left' => [61, 62, 63, 64, 65],
            'lower_right' => [85, 84, 83, 82, 81],
            'lower_left' => [71, 72, 73, 74, 75],
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function validToothNumbers(): array
    {
        return [
            ...self::permanentQuadrants()['upper_right'],
            ...self::permanentQuadrants()['upper_left'],
            ...self::permanentQuadrants()['lower_right'],
            ...self::permanentQuadrants()['lower_left'],
            ...self::primaryQuadrants()['upper_right'],
            ...self::primaryQuadrants()['upper_left'],
            ...self::primaryQuadrants()['lower_right'],
            ...self::primaryQuadrants()['lower_left'],
        ];
    }

    public static function isValidTooth(int $toothNumber): bool
    {
        return in_array($toothNumber, self::validToothNumbers(), true);
    }

    public static function quadrant(int $toothNumber): int
    {
        self::assertValidTooth($toothNumber);

        return intdiv($toothNumber, 10);
    }

    public static function position(int $toothNumber): int
    {
        self::assertValidTooth($toothNumber);

        return $toothNumber % 10;
    }

    public static function isUpper(int $toothNumber): bool
    {
        return in_array(self::quadrant($toothNumber), [1, 2, 5, 6], true);
    }

    public static function isRightSide(int $toothNumber): bool
    {
        return in_array(self::quadrant($toothNumber), [1, 4, 5, 8], true);
    }

    public static function isPrimary(int $toothNumber): bool
    {
        return in_array(self::quadrant($toothNumber), [5, 6, 7, 8], true);
    }

    public static function isAnterior(int $toothNumber): bool
    {
        return in_array(self::position($toothNumber), [1, 2, 3], true);
    }

    public static function isPosterior(int $toothNumber): bool
    {
        return ! self::isAnterior($toothNumber);
    }

    public static function sameArch(int $firstTooth, int $secondTooth): bool
    {
        return self::isUpper($firstTooth) === self::isUpper($secondTooth);
    }

    public static function morphology(int $toothNumber): string
    {
        $position = self::position($toothNumber);

        return match (true) {
            in_array($position, [1, 2], true) => 'incisor',
            $position === 3 => 'canine',
            in_array($position, [4, 5], true) => self::isPrimary($toothNumber) ? 'molar' : 'premolar',
            default => 'molar',
        };
    }

    /**
     * Map the 5 geometric SVG polygons to anatomical dental surfaces by quadrant.
     *
     * @return array{top: string, bottom: string, left: string, right: string, center: string}
     */
    public static function surfaceMapping(int $toothNumber): array
    {
        $isUpper = self::isUpper($toothNumber);
        $isRightSide = self::isRightSide($toothNumber);

        return [
            'top' => $isUpper ? 'V' : 'L',
            'bottom' => $isUpper ? 'P' : 'V',
            'left' => $isRightSide ? 'D' : 'M',
            'right' => $isRightSide ? 'M' : 'D',
            'center' => self::isAnterior($toothNumber) ? 'I' : 'O',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedSurfacesForTooth(int $toothNumber): array
    {
        return array_values(self::surfaceMapping($toothNumber));
    }

    /**
     * Filter to valid surfaces for the tooth and sort in canonical clinical order.
     *
     * @param  array<int, string>  $surfaces
     * @return array<int, string>
     */
    public static function normalizeSurfaces(int $toothNumber, array $surfaces): array
    {
        $allowed = self::allowedSurfacesForTooth($toothNumber);
        $normalized = collect($surfaces)
            ->map(fn (mixed $surface): string => strtoupper(trim((string) $surface)))
            ->filter(fn (string $surface): bool => in_array($surface, $allowed, true))
            ->unique()
            ->values()
            ->all();

        usort(
            $normalized,
            fn (string $first, string $second): int => array_search($first, self::SURFACE_ORDER, true)
                <=> array_search($second, self::SURFACE_ORDER, true)
        );

        return $normalized;
    }

    /**
     * @param  array<int, string>  $first
     * @param  array<int, string>  $second
     */
    public static function surfacesOverlap(array $first, array $second): bool
    {
        return count(array_intersect($first, $second)) > 0;
    }

    /**
     * @return array<string, string>
     */
    public static function surfaceLabels(): array
    {
        return [
            'V' => __('Vestibular'),
            'P' => __('Palatine'),
            'L' => __('Lingual'),
            'M' => __('Mesial'),
            'D' => __('Distal'),
            'O' => __('Occlusal'),
            'I' => __('Incisal'),
        ];
    }

    protected static function assertValidTooth(int $toothNumber): void
    {
        if (! in_array($toothNumber, self::validToothNumbers(), true)) {
            throw new InvalidArgumentException("Invalid FDI tooth number: {$toothNumber}");
        }
    }
}
