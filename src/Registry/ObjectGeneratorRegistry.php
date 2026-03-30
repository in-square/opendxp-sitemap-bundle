<?php

namespace InSquare\OpendxpSitemapBundle\Registry;

use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ObjectGeneratorRegistry
{
    /** @var array<string, ObjectGeneratorInterface> */
    private array $generatorsById = [];

    /** @var array<string, ObjectGeneratorInterface> */
    private array $generatorsByClass = [];

    /**
     * @param iterable<ObjectGeneratorInterface> $generators
     */
    public function __construct(
        #[TaggedIterator('in_square_opendxp_sitemap.object_generator')] iterable $generators
    ) {
        foreach ($generators as $generator) {
            $id = $generator->getId();
            if (isset($this->generatorsById[$id])) {
                throw new \RuntimeException(sprintf('Duplicate object generator id "%s".', $id));
            }

            $class = $generator->getObjectClass();
            if (isset($this->generatorsByClass[$class])) {
                throw new \RuntimeException(sprintf('Duplicate object generator class "%s".', $class));
            }

            $this->generatorsById[$id] = $generator;
            $this->generatorsByClass[$class] = $generator;
        }
    }

    public function getById(string $id): ?ObjectGeneratorInterface
    {
        return $this->generatorsById[$id] ?? null;
    }

    public function getByObjectClass(string $objectClass): ?ObjectGeneratorInterface
    {
        return $this->generatorsByClass[$objectClass] ?? null;
    }

    /**
     * @return ObjectGeneratorInterface[]
     */
    public function all(): array
    {
        return array_values($this->generatorsById);
    }
}
