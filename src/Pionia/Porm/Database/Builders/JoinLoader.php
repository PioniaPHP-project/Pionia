<?php

namespace Pionia\Porm\Database\Builders;

/**
 * Manual eager-load helper to avoid N+1 when looping parent rows.
 */
final class JoinLoader
{
    /**
     * Attach related rows to each parent by foreign key (one extra query).
     *
     * @param array<int, object|array> $parents
     * @param string $parentFk Column on each parent holding the related PK
     * @param string $relatedTable Table to load
     * @param string $relatedPk Primary key on the related table
     * @param string $attachAs Property/array key on each parent for the match
     * @param null|string|array $connection Connection name or config for table()
     * @return array<int, object|array>
     */
    public static function eager(
        array $parents,
        string $parentFk,
        string $relatedTable,
        string $relatedPk = 'id',
        string $attachAs = 'related',
        null | string | array $connection = null,
    ): array {
        if ($parents === []) {
            return $parents;
        }

        $ids = [];
        foreach ($parents as $parent) {
            $fk = is_object($parent) ? ($parent->{$parentFk} ?? null) : ($parent[$parentFk] ?? null);
            if ($fk !== null && $fk !== '') {
                $ids[] = $fk;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $parents;
        }

        $relatedRows = table($relatedTable, null, $connection)
            ->filter()
            ->whereIn($relatedPk, $ids)
            ->all() ?? [];

        $index = [];
        foreach ($relatedRows as $row) {
            $pk = is_object($row) ? ($row->{$relatedPk} ?? null) : ($row[$relatedPk] ?? null);
            if ($pk !== null) {
                $index[$pk] = $row;
            }
        }

        foreach ($parents as $indexKey => $parent) {
            $fk = is_object($parent) ? ($parent->{$parentFk} ?? null) : ($parent[$parentFk] ?? null);
            $match = $fk !== null ? ($index[$fk] ?? null) : null;

            if (is_object($parent)) {
                $parent->{$attachAs} = $match;
                $parents[$indexKey] = $parent;
            } else {
                $parent[$attachAs] = $match;
                $parents[$indexKey] = $parent;
            }
        }

        return $parents;
    }
}
