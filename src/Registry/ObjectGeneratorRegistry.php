<?php

namespace InSquare\OpendxpSitemapBundle\Registry;

use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorWithContextInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ObjectGeneratorRegistry
{
    /** @var array<string, ObjectGeneratorInterface|ObjectGeneratorWithContextInterface> */
    private array $generatorsById = [];

    /** @var array<string, ObjectGeneratorInterface|ObjectGeneratorWithContextInterface> */
    private array $generatorsByClass = [];

    /**
     * @param iterable<ObjectGeneratorInterface|ObjectGeneratorWithContextInterface> $generators
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

    public function getById(string $id): ObjectGeneratorInterface|ObjectGeneratorWithContextInterface|null
    {
        return $this->generatorsById[$id] ?? null;
    }

    public function getByObjectClass(string $objectClass): ObjectGeneratorInterface|ObjectGeneratorWithContextInterface|null
    {
        return $this->generatorsByClass[$objectClass] ?? null;
    }

    /**
     * @return array<int, ObjectGeneratorInterface|ObjectGeneratorWithContextInterface>
     */
    public function all(): array
    {
        return array_values($this->generatorsById);
    }
}
