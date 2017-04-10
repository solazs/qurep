<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Solazs\QuReP\ApiBundle\Resources\Consts;
use Solazs\QuReP\ApiBundle\Resources\PropType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Solazs\QuReP\ApiBundle\Exception\FormErrorException;

class DataHandler
{
    /** @var \Doctrine\ORM\EntityManager $em */
    protected $em;
    /** @var \Solazs\QuReP\ApiBundle\Services\EntityFormBuilder $entityFormBuilder */
    protected $entityFormBuilder;
    /** @var \Solazs\QuReP\ApiBundle\Services\EntityParser $entityParser */
    protected $entityParser;
    protected $filters;
    protected $paging;
    /** @var \Psr\Log\LoggerInterface $logger */
    protected $logger;
    protected $loglbl = Consts::qurepLogLabel.'DataHandler: ';

    function __construct(
      EntityManager $entityManager,
      EntityFormBuilder $entityFormBuilder,
      EntityParser $entityParser,
      LoggerInterface $logger
    ) {
        $this->em = $entityManager;
        $this->entityFormBuilder = $entityFormBuilder;
        $this->entityParser = $entityParser;
        $this->filters = [];
        $this->paging = ['offset' => 0, 'limit' => 25];
        $this->logger = $logger;
    }

    /**
     * GET a collection
     * Builds a query based on paging and filters and executes it, then returns the data.
     *
     * @param string $entityClass
     * @param array  $meta
     * @return array
     */
    function getAll(string $entityClass, array &$meta)
    {
        $this->logger->debug($this->loglbl.'getAll called');
        $parameters = [];
        $qb = $this->em->createQueryBuilder();
        $conditions = $this->buildFilterSubQuery($qb, $parameters);
        $qb->select('ent')
          ->from($entityClass, 'ent');
        $this->buildJoinSubQuery($qb);

        $qb->setFirstResult($this->paging['offset']);
        $qb->setMaxResults($this->paging['limit']);

        if (count($conditions) > 0) {
            $qb->where($conditions)
              ->setParameters($parameters);
        }

        $query = $qb->getQuery();

        $this->logger->debug($this->loglbl.'getAll created query', ['query' => $query->getSQL()]);

        $paginator = new Paginator($query);

        $data = [];
        $meta['limit'] = $this->paging['limit'];
        $meta['offset'] = $this->paging['offset'];
        $meta['count'] = count($paginator);

        foreach ($paginator as $item) {
            array_push($data, $item);
        }

        //$data = $qb->getQuery()->getResult();
        $this->logger->debug($this->loglbl.'getAll returning data', $data);

        return $data;
    }

    /* ============================================== *
     * Functions necessary to build the query in GETs *
     * ============================================== *
     */

    private function buildFilterSubQuery(QueryBuilder $qb, array &$parameters)
    {
        $conditions = [];
        foreach ($this->filters as $filterGrp) {
            $andConditions = [];
            foreach ($filterGrp as $filter) {
                $propName = $this->walkFilterProp($filter["prop"]);
                $andConditions[] = call_user_func_array(
                  [$qb->expr(), $filter['operand']],
                  [$propName, ':'.str_replace('.', '_', $propName).'_param']
                );
                $parameters[':'.str_replace('.', '_', $propName).'_param'] = $filter['value'];
            }
            if (count($andConditions) > 0) {
                if (count($andConditions) > 1) {
                    $andConditions = call_user_func_array([$qb->expr(), 'andx'], $andConditions);
                    $conditions[] = $andConditions;
                } else {
                    $conditions[] = $andConditions[0];
                }
            }
        }
        if (count($conditions) > 0) {
            if (count($conditions) > 1) {
                $conditions = call_user_func_array([$qb->expr(), 'orx'], $conditions);
            } else {
                $conditions = $conditions[0];
            }
        }

        return $conditions;
    }

    private function walkFilterProp(array $prop, $joinCntr = 0)
    {
        if ($prop['children'] != null) {
            return $this->walkFilterProp($prop['children'], ++$joinCntr);
        } else {
            return 'ent'.($joinCntr == 0 ? '' : $joinCntr).'.'.$prop['name'];
        }
    }

    private function buildJoinSubQuery(QueryBuilder &$qb)
    {
        foreach ($this->filters as $filterGrp) {
            foreach ($filterGrp as $filter) {
                $this->walkJoinFilterProp($filter['prop'], $qb);
            }
        }
    }

    private function walkJoinFilterProp(array $prop, QueryBuilder &$qb, $joinCntr = 0)
    {
        if ($prop['children'] != null) {
            $qb->leftJoin('ent'.($joinCntr == 0 ? '' : $joinCntr).'.'.$prop['name'], 'ent'.++$joinCntr);
            $this->walkJoinFilterProp($prop['children'], $qb, $joinCntr);
        }
    }

    /**
     * Returns the fields of the entity that should be returned by default (based on Type annotation).
     * This means all properties except OneToOne, OneToMany and ManyToOne connections.
     *
     * @param string $entityClass
     * @return array
     */
    public function getFields(string $entityClass)
    {
        $fields = [];
        $props = $this->entityParser->getProps($entityClass);
        foreach ($props as $prop) {
            if (($prop['propType'] == PropType::PROP || $prop['propType'] == PropType::TYPED_PROP)) {
                array_push($fields, $prop['name']);
            }
        }

        return $fields;
    }

    /**
     * Bulk update. Determines whether the entity exists in the database (by querying its ID if there is any),
     * then posts/updates accordingly.
     *
     * FIXME: implement this with embedded forms
     *
     * @param string $entityClass
     * @param array  $postData Data from the request body
     * @return array
     */
    public function bulkUpdate(string $entityClass, array $postData = array())
    {
        $this->logger->debug($this->loglbl.'bulkUpdate called');

        if (!is_array($postData)) {
            throw new BadRequestHttpException('Data is not an array');
        }
        if (count($postData) == 0) {
            throw new BadRequestHttpException('Data is an empty array');
        }
        $returnData = [];

        foreach ($postData as $item) {
            if (array_key_exists('id', $item)) {
                $data = $item;
                unset($data['id']);
                $entity = $this->update($entityClass, $item['id'], $data);
            } else {
                $entity = $this->create($entityClass, $item);
            }
            $returnData[] = $entity;
        }


        $this->logger->debug($this->loglbl.'bulkUpdate successful');

        return $returnData;
    }

    /**
     * Updates a single resource of the supplied entity class with the supplied ID.
     * Upon success, the updated entity is returned.
     * Upon failure, form error handling is performed, which throws an exception.
     *
     *
     * @param string $entityClass
     * @param int    $id
     * @param array  $postData
     * @return mixed
     */
    public function update(string $entityClass, $id = 0, array $postData = array())
    {
        $this->logger->debug($this->loglbl.'update called');
        if (count($postData) == 0) {
            throw new BadRequestHttpException('No data supplied');
        }
        $entity = $this->em->getReference($entityClass, $id);
        if (!$entity) {
            throw new NotFoundHttpException('Entity with id '.$id.' was not found in the database.');
        }

        /** @var Form $form */
        $form = $this->entityFormBuilder->getForm($entityClass, $entity);
        $form->submit($postData, false);

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            $this->logger->debug($this->loglbl.'update successful');

            return $this->get($entityClass, $entity->getId());
        } else {
            $this->handleFormError($form);

            return null;
        }

    }

    /**
     * Get a single resource of the supplied class with the given ID.
     *
     * @param string $entityClass
     * @param int    $id
     * @return mixed
     */
    public function get(string $entityClass, int $id = 0)
    {
        $this->logger->debug($this->loglbl.'get called');
        $qb = $this->em->createQueryBuilder()
          ->select('ent')
          ->from($entityClass, 'ent');
        $qb->where('ent.id = :id')
          ->setParameter(':id', $id);
        $data = $qb->getQuery()
          ->getOneOrNullResult();

        if (!$data) {
            throw new NotFoundHttpException('Entity with id '.$id.' was not found in the database.');
        }

        $this->logger->debug($this->loglbl.'get successful');

        return $data;
    }

    private function handleFormError($form)
    {
        throw new FormErrorException($form);
    }

    /**
     * Inserts a resource into the database and returns the newly created entity.
     *
     * @param string $entityClass
     * @param array  $postData
     * @return mixed
     */
    function create(string $entityClass, array $postData = array())
    {
        $this->logger->debug($this->loglbl.'create called');
        if (count($postData) == 0) {
            throw new BadRequestHttpException('No data supplied');
        }
        $entity = new $entityClass;

        /** @var Form $form */
        $form = $this->entityFormBuilder->getForm($entityClass, $entity);
        $form->submit($postData);

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            $this->logger->debug($this->loglbl.'create successful');

            return $this->get($entityClass, $entity->getId());
        } else {
            $this->handleFormError($form);

            return null;
        }

    }

    /**
     * Deletes a single resource of the supplied class with the given ID.
     *
     * @param string $entityClass
     * @param int    $id
     */
    public function delete(string $entityClass, int $id = 0)
    {
        $this->logger->debug($this->loglbl.'delete called');
        $entity = $this->em->getReference($entityClass, $id);
        if (!$entity) {
            throw new NotFoundHttpException('Entity with id '.$id.' was not found in the database.');
        }
        $this->em->remove($entity);
        $this->em->flush();
        $this->logger->debug($this->loglbl.'delete successful');
    }

    /**
     * Deletes resources of the supplied class with the given IDs.
     *
     * @param string $entityClass
     * @param array  $postData
     */
    public function deleteCollection(string $entityClass, array $postData = array())
    {
        $this->logger->debug($this->loglbl.'deleteCollection called');
        $ids = [];
        foreach ($postData as $item) {
            array_push($ids, $item['id']);
        }
        $this->em->createQueryBuilder()
          ->delete($entityClass, 'ent')
          ->where('ent.id IN (:ids)')
          ->setParameter(':ids', $ids)
          ->getQuery()
          ->execute();
        $this->logger->debug($this->loglbl.'deleteCollection successful');
    }

    /**
     * Returns information about the resource
     *
     * @param string $entityClass
     * @return array
     */
    public function meta(string $entityClass): array
    {
        $this->logger->debug($this->loglbl.'meta called');
        $props = $this->entityParser->getProps($entityClass);
        $success = usort(
          $props,
          function ($a, $b) {
              return strcmp($a['label'], $b['label']);
          }
        );
        if (!$success) {
            throw new HttpException(500, 'Failed to sort metadata');
        }

        foreach ($props as &$prop) {
            $success = ksort($prop);

            if (!$success) {
                throw new HttpException(500, 'Failed to sort metadata');
            }
        }
        $this->logger->debug($this->loglbl.'meta successful');

        return $props;
    }

    /**
     * @param array $filters
     * @param array $paging
     */
    public function setupClass(array $filters, array $paging)
    {
        $this->filters = $filters;
        $this->paging = $paging;
        $this->logger->debug($this->loglbl.'DataHandler setup done', ['filters' => $filters, 'paging' => $paging]);
    }
}