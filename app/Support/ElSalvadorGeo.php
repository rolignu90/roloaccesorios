<?php

namespace App\Support;

use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;

/**
 * Departamentos y municipios de El Salvador (catálogo histórico clásico).
 * Fuente: dataset público JSON (npoint / comunidades) en resources/data.
 */
class ElSalvadorGeo
{
    private static ?array $raw = null;

    /**
     * @return list<array{id: string, name: string, municipalities: list<array{id: string, name: string, postal_code: ?string}>}>
     */
    public static function departments(): array
    {
        return self::collection()
            ->map(fn (array $department) => [
                'id' => (string) $department['id'],
                'name' => (string) $department['nombre'],
                'municipalities' => collect($department['municipios'] ?? [])
                    ->map(fn (array $municipality) => [
                        'id' => (string) ($municipality['id_mun'] ?? ''),
                        'name' => (string) ($municipality['nombre'] ?? ''),
                        'postal_code' => isset($municipality['codigo_postal'])
                            ? (string) $municipality['codigo_postal']
                            : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function departmentNames(): array
    {
        return self::collection()->pluck('nombre')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * @return list<string>
     */
    public static function municipalityNames(?string $department = null): array
    {
        $query = self::collection();

        if (filled($department)) {
            $query = $query->where('nombre', $department);
        }

        return $query
            ->flatMap(fn (array $departmentRow) => collect($departmentRow['municipios'] ?? [])->pluck('nombre'))
            ->map(fn ($n) => (string) $n)
            ->unique()
            ->values()
            ->all();
    }

    public static function isValidPair(?string $department, ?string $municipality): bool
    {
        if (! filled($department) || ! filled($municipality)) {
            return false;
        }

        return in_array($municipality, self::municipalityNames($department), true);
    }

    public static function postalCodeFor(?string $department, ?string $municipality): ?string
    {
        if (! filled($department) || ! filled($municipality)) {
            return null;
        }

        foreach (self::departments() as $departmentRow) {
            if ($departmentRow['name'] !== $department) {
                continue;
            }

            foreach ($departmentRow['municipalities'] as $municipalityRow) {
                if ($municipalityRow['name'] === $municipality) {
                    return $municipalityRow['postal_code'];
                }
            }
        }

        return null;
    }

    /**
     * Payload para selects en cascada (JS).
     *
     * @return list<array{name: string, municipalities: list<array{name: string, postal_code: ?string}>}>
     */
    public static function forJs(): array
    {
        return collect(self::departments())
            ->map(fn (array $department) => [
                'name' => $department['name'],
                'municipalities' => collect($department['municipalities'])
                    ->map(fn (array $municipality) => [
                        'name' => $municipality['name'],
                        'postal_code' => $municipality['postal_code'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function collection(): Collection
    {
        return collect(self::raw());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function raw(): array
    {
        if (self::$raw !== null) {
            return self::$raw;
        }

        $path = resource_path('data/el_salvador_departamentos.json');

        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el catálogo geográfico de El Salvador.');
        }

        try {
            /** @var list<array<string, mixed>> $decoded */
            $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Catálogo geográfico inválido: '.$e->getMessage(), 0, $e);
        }

        return self::$raw = $decoded;
    }
}
