<?php

namespace Umbrella\CoreBundle\DataTable\Adapter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Umbrella\CoreBundle\DataTable\DTO\DataTableResult;
use Umbrella\CoreBundle\DataTable\DTO\DataTableState;

class EntityAdapter extends DataTableAdapter implements DoctrineAdapterInterface
{
    protected EntityManagerInterface $em;

    /**
     * EntityCollector constructor.
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired('class')
            ->setAllowedTypes('class', 'string')

            ->setDefault('query_alias', 'e')
            ->setAllowedTypes('query_alias', 'string')

            ->setDefault('query', null)
            ->setAllowedTypes('query', ['callable', 'null'])

            /*
             * Paginator / query options (use for optimization)
             *
             * To fetch large data (~over 1m) use options :
             *  [
             *      'fetch_join_collection' => false,
             *      'use_output_walker' => false,
             *      'use_distinct_hint' => false
             * ]
             */
            ->setDefault('fetch_join_collection', true)
            ->setAllowedTypes('fetch_join_collection', 'bool')

            ->setDefault('use_output_walker', null)
            ->setAllowedTypes('use_output_walker', ['null', 'bool'])

            ->setDefault('use_distinct_hint', true)
            ->setAllowedTypes('use_distinct_hint', 'bool');
    }

    public function getResult(DataTableState $state, array $options): DataTableResult
    {
        $query = $this->getQueryBuilder($state, $options)->getQuery();
        if (false === $options['use_distinct_hint']) {
            $query->setHint(CountWalker::HINT_DISTINCT, false);
        }

        $paginator = new Paginator($query, $options['fetch_join_collection']);
        if (null !== $options['use_output_walker']) {
            $paginator->setUseOutputWalkers($options['use_output_walker']);
        }

        return new DataTableResult($paginator);
    }

    public function getQueryBuilder(DataTableState $state, array $options): QueryBuilder
    {
        $dataTable = $state->getDataTable();
        $formData = $state->getFormData();

        $qb = $this->em->createQueryBuilder()
            ->select($options['query_alias'])
            ->from($options['class'], $options['query_alias']);

        if (is_callable($options['query'])) {
            call_user_func($options['query'], $qb, $formData);
        }

        // pagination
        $qb->setFirstResult($state->getStart());
        if ($state->getLength() >= 0) {
            $qb->setMaxResults($state->getLength());
        }

        // order by
        foreach ($state->getOrderBy() as [$column, $direction]) {
            foreach ($column->getOrderBy() as $path) {
                // if path is not a sub property path, prefix it by alias
                if (false === strpos($path, '.')) {
                    $path = sprintf('%s.%s', $options['query_alias'], $path);
                }

                $qb->addOrderBy($path, strtoupper($direction));
            }
        }

        return $qb;
    }
}
