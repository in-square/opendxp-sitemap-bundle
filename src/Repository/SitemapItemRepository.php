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
}
