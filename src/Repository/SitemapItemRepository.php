<?php

namespace InSquare\OpendxpSitemapBundle\Repository;

use Doctrine\DBAL\Connection;

final class SitemapItemRepository
{
    private const TABLE = 'sitemap_item';
    private const COLUMNS = [
        'element_type',
        'element_id',
        'element_class',
        'site_id',
        'locale',
        'url',
        'lastmod',
        'priority',
        'changefreq',
        'last_seen_run_token',
    ];
    private const FILTER_COLUMNS = [
        'id',
        'element_type',
        'element_id',
        'element_class',
        'site_id',
        'locale',
        'url',
        'lastmod',
        'priority',
        'changefreq',
        'last_seen_run_token',
    ];
    private const UPSERT_KEY_COLUMNS = [
        'element_type',
        'element_id',
        'element_class',
        'site_id',
        'locale',
    ];
    private const UPSERT_UPDATE_COLUMNS = [
        'url',
        'lastmod',
        'priority',
        'changefreq',
        'last_seen_run_token',
    ];

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function insert(array $data): int
    {
        $row = $this->normalizeRowWithDefaults($data);

        $this->connection->insert(self::TABLE, $row);

        return (int) $this->connection->lastInsertId();
    }

    public function upsert(array $data): void
    {
        $row = $this->normalizeRowWithDefaults($data);
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());

        if ($platform === 'mysql' || $platform === 'mariadb') {
            $this->executeMySqlUpsert($row);

            return;
        }

        if ($platform === 'sqlite' || $platform === 'postgresql' || $platform === 'postgres') {
            $this->executeSqliteOrPostgresUpsert($row);

            return;
        }

        throw new \RuntimeException(sprintf('Unsupported database platform for sitemap upsert: %s', $platform));
    }

    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = self::COLUMNS;
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            self::TABLE,
            implode(', ', $columns),
            $placeholders
        );
        $statement = $this->connection->prepare($sql);

        $count = 0;
        $this->connection->beginTransaction();
        try {
            foreach ($rows as $row) {
                $normalized = $this->normalizeRowWithDefaults($row);
                $statement->executeStatement(array_values($normalized));
                $count++;
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $count;
    }

    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id',
            ['id' => $id]
        );

        return $row === false ? null : $row;
    }

    public function findBy(array $criteria, ?int $limit = null, ?int $offset = null): array
    {
        $criteria = $this->filterCriteria($criteria);

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE);

        foreach ($criteria as $column => $value) {
            $queryBuilder
                ->andWhere($column . ' = :' . $column)
                ->setParameter($column, $value);
        }

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        if ($offset !== null) {
            $queryBuilder->setFirstResult($offset);
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function update(int $id, array $data): int
    {
        $row = $this->normalizeRow($data);
        if ($row === []) {
            return 0;
        }

        return $this->connection->update(self::TABLE, $row, ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->connection->delete(self::TABLE, ['id' => $id]);
    }

    public function deleteAll(): int
    {
        return $this->connection->executeStatement('DELETE FROM ' . self::TABLE);
    }

    public function truncate(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE ' . self::TABLE);
    }

    public function deleteRowsNotSeenInRunToken(string $runToken, ?int $siteId = null, ?string $locale = null): int
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('last_seen_run_token IS NULL OR last_seen_run_token <> :runToken')
            ->setParameter('runToken', $runToken);

        if ($siteId !== null) {
            $queryBuilder
                ->andWhere('site_id = :siteId')
                ->setParameter('siteId', $siteId);
        }

        if ($locale !== null && $locale !== '') {
            $queryBuilder
                ->andWhere('locale = :locale')
                ->setParameter('locale', $locale);
        }

        return $queryBuilder->executeStatement();
    }

    public function fetchBatchBySiteLocale(int $siteId, string $locale, int $limit, int $offset): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where('site_id = :siteId')
            ->andWhere('locale = :locale')
            ->setParameter('siteId', $siteId)
            ->setParameter('locale', $locale)
            ->orderBy('id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function fetchBatchBySiteLocaleAndType(
        int $siteId,
        string $locale,
        string $elementType,
        int $limit,
        int $offset
    ): array {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where('site_id = :siteId')
            ->andWhere('locale = :locale')
            ->andWhere('element_type = :elementType')
            ->setParameter('siteId', $siteId)
            ->setParameter('locale', $locale)
            ->setParameter('elementType', $elementType)
            ->orderBy('id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function fetchBatchBySiteLocaleTypeAndClass(
        int $siteId,
        string $locale,
        string $elementType,
        string $elementClass,
        int $limit,
        int $offset
    ): array {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where('site_id = :siteId')
            ->andWhere('locale = :locale')
            ->andWhere('element_type = :elementType')
            ->andWhere('element_class = :elementClass')
            ->setParameter('siteId', $siteId)
            ->setParameter('locale', $locale)
            ->setParameter('elementType', $elementType)
            ->setParameter('elementClass', $elementClass)
            ->orderBy('id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<int, array{locale: string, url: string}>>
     */
    public function fetchAlternatesBySiteTypeAndElementIds(
        int $siteId,
        string $elementType,
        ?string $elementClass,
        array $elementIds
    ): array {
        $normalizedElementIds = array_values(array_unique(array_map('intval', $elementIds)));
        if ($normalizedElementIds === []) {
            return [];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('element_id', 'locale', 'url')
            ->from(self::TABLE)
            ->where('site_id = :siteId')
            ->andWhere('element_type = :elementType')
            ->setParameter('siteId', $siteId)
            ->setParameter('elementType', $elementType)
            ->orderBy('element_id', 'ASC')
            ->addOrderBy('locale', 'ASC');

        $placeholders = [];
        foreach ($normalizedElementIds as $index => $elementId) {
            $parameterName = 'elementId' . $index;
            $placeholders[] = ':' . $parameterName;
            $queryBuilder->setParameter($parameterName, $elementId);
        }
        $queryBuilder->andWhere('element_id IN (' . implode(', ', $placeholders) . ')');

        if ($elementClass === null) {
            $queryBuilder->andWhere('(element_class IS NULL OR element_class = \'\')');
        } else {
            $queryBuilder
                ->andWhere('element_class = :elementClass')
                ->setParameter('elementClass', $elementClass);
        }

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        $result = [];

        foreach ($rows as $row) {
            $id = (int) ($row['element_id'] ?? 0);
            $locale = (string) ($row['locale'] ?? '');
            $url = (string) ($row['url'] ?? '');

            if ($id <= 0 || $locale === '' || $url === '') {
                continue;
            }

            $result[$id][] = [
                'locale' => $locale,
                'url' => $url,
            ];
        }

        return $result;
    }


    private function normalizeRow(array $data): array
    {
        $row = [];
        foreach (self::COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];
            if ($column === 'lastmod' && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            if ($column === 'element_class') {
                $value = $this->normalizeElementClass($value);
            }

            $row[$column] = $value;
        }

        return $row;
    }

    private function normalizeRowWithDefaults(array $data): array
    {
        $row = [];
        foreach (self::COLUMNS as $column) {
            $value = $data[$column] ?? null;
            if ($column === 'lastmod' && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            if ($column === 'element_class') {
                $value = $this->normalizeElementClass($value);
            }

            $row[$column] = $value;
        }

        return $row;
    }

    private function filterCriteria(array $criteria): array
    {
        $filtered = [];
        foreach ($criteria as $column => $value) {
            if (!in_array($column, self::FILTER_COLUMNS, true)) {
                continue;
            }

            $filtered[$column] = $value;
        }

        return $filtered;
    }

    private function executeMySqlUpsert(array $row): void
    {
        $columns = self::COLUMNS;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updateSql = implode(', ', array_map(
            static fn (string $column): string => sprintf('%1$s = VALUES(%1$s)', $column),
            self::UPSERT_UPDATE_COLUMNS
        ));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            self::TABLE,
            implode(', ', $columns),
            $placeholders,
            $updateSql
        );

        $this->connection->executeStatement($sql, array_values($row));
    }

    private function executeSqliteOrPostgresUpsert(array $row): void
    {
        $columns = self::COLUMNS;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $conflictColumns = implode(', ', self::UPSERT_KEY_COLUMNS);
        $updateSql = implode(', ', array_map(
            static fn (string $column): string => sprintf('%1$s = excluded.%1$s', $column),
            self::UPSERT_UPDATE_COLUMNS
        ));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
            self::TABLE,
            implode(', ', $columns),
            $placeholders,
            $conflictColumns,
            $updateSql
        );

        $this->connection->executeStatement($sql, array_values($row));
    }

    private function normalizeElementClass(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
