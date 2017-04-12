<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Psr\Log\LoggerInterface;
use Solazs\QuReP\ApiBundle\Exception\RouteException;
use Solazs\QuReP\ApiBundle\Resources\Action;
use Solazs\QuReP\ApiBundle\Resources\Consts;
use Solazs\QuReP\ApiBundle\Resources\PropType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * Analyzes the request and extracts information like
 * paging, extract, filter parameters.
 */
class RouteAnalyzer
{
    protected $entities = null;
    protected $entityParser;
    protected $logger;
    protected $loglbl = Consts::qurepLogLabel.'RouteAnalyzer: ';

    function __construct(EntityParser $entityParser, LoggerInterface $logger)
    {
        $this->entityParser = $entityParser;
        $this->logger = $logger;
    }

    public function setConfig(array $entities)
    {
        $this->entities = $entities;
        $this->logger->debug($this->loglbl.'Config set', $entities);
    }

    /**
     * Parse request string to get collection (thus the entity class) and the action we're requested to execute.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string                                    $apiRoute
     * @return array('class' => $class, 'action' => $action, 'id' => $id)
     * @throws \Solazs\QuReP\ApiBundle\Exception\RouteException
     */
    public function getActionAndEntity(Request $request, string $apiRoute) : array
    {
        $class = null;
        $isBulk = false;
        $isMeta = false;
        $id = null;
        $method = $request->getMethod();

        if (strpos($apiRoute, '/') === false) {
            // No / in route, this is an entity name.
            $class = $this->getEntityClassFromString($apiRoute);

        } else {
            if (strpos($apiRoute, '/') != strrpos($apiRoute, '/')) {
                // Nested routes are not supported ATM.
                throw new RouteException('Route '.$apiRoute.' is invalid, too many "/" in route.');
            } else {
                // This request is either bulk or has an ID at the end.
                $class = $this->getEntityClassFromString(substr($apiRoute, 0, strpos($apiRoute, '/')));
                $id = substr($apiRoute, strpos($apiRoute, '/') + 1, strlen($apiRoute) - strpos($apiRoute, '/'));
                if ($id === 'bulk') {
                    // Bulk it is!
                    $isBulk = true;
                } else {
                    if ($id === 'meta') {
                        $isMeta = true;
                    }
                }
            }
        }

        $action = null;

        // Determine action to take.

        if ($method === "GET") {
            if ($isBulk) {
                throw new MethodNotAllowedException(array('POST', 'DELETE'), $method.' not supported for bulk requests.');
            }
            if ($isMeta) {
                $action = Action::META;
            } else {
                if ($id !== null) {
                    $action = Action::GET_SINGLE;
                } else {
                    $action = Action::GET_COLLECTION;
                }
            }
        } else {
            if ($method === "POST") {
                if ($isBulk) {
                    $action = Action::UPDATE_COLLECTION;
                } else {
                    if ($id == null) {
                        $action = Action::POST_SINGLE;
                    } else {
                        $action = Action::UPDATE_SINGLE;
                    }
                }
            } else {
                if ($method === 'DELETE') {
                    if ($isBulk) {
                        $action = Action::DELETE_COLLECTION;
                    } else {
                        if ($id == null) {
                            throw new RouteException('Invalid action! Use entity/bulk to DELETE multiple objects or supply ID');
                        } else {
                            $action = Action::DELETE_SINGLE;
                        }
                    }
                } else {
                    throw new MethodNotAllowedException(array('GET', 'POST', 'DELETE'), $method.' not supported.');
                }
            }
        }

        if ($action === null) {
            $this->logger->error(
              $this->loglbl
              .'Could not determine action to take. Class: '.$class.', route: '.$apiRoute.", method: "
              .$request->getMethod()
            );
            throw new RouteException('Could not determine action to take');
        }

        $this->logger->info(
          $this->loglbl.
          'Class: '.$class.', id: '.($id === null ? 'null' : $id).', method: '.$request->getMethod()
          .', taking action: '.$action
        );

        return array('class' => $class, 'action' => $action, 'id' => $id);
    }

    private function getEntityClassFromString(string $string) : string
    {
        foreach ($this->entities as $entity) {
            if ($string === $entity['entity_name']) {
                $this->logger->debug($this->loglbl.'Found entity: '.$entity['class']);

                return $entity['class'];
            }
        }
        throw new RouteException('Could not find entity '.$string);
    }

    /**
     * Extracts paging parameters from request.
     * Returns sensible defaults if values are absent from the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array('offset'=>0,'limit=>25)
     * @throws \Solazs\QuReP\ApiBundle\Exception\RouteException
     */
    public function extractPaging(Request $request) : array
    {
        $paging = [
          'offset' => 0,
          'limit'  => 25,
        ];

        if ($request->query->has('limit')) {
            $limit = $request->query->get('limit');
            if (is_nan($limit)) {
                throw new RouteException('Invalid paging parameter! Limit must be a number.');
            } else {
                $paging['limit'] = $limit;
                $this->logger->debug($this->loglbl.'Found limit in request');
            }
        }

        if ($request->query->has('offset')) {
            $offset = $request->query->get('offset');
            if (is_nan($offset)) {
                throw new RouteException('Invalid paging parameter! Offset must be a number.');
            } else {
                $paging['offset'] = $offset;
                $this->logger->debug($this->loglbl.'Found offset in request');
            }
        }
        $this->logger->debug($this->loglbl.'Extracted paging data', $paging);

        return $paging;
    }

    /**
     * Extracts and validates extract parameters from the request.
     * Returned data is an array of arrays with the following format
     * array(
     * 'name'=>$name,
     * 'label'=>$label,
     * 'propType'=>$propType,
     * 'children'=>array|null // recursive array or null
     * )
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string                                    $entityClass
     * @return array
     */
    public function extractExpand(Request $request, string $entityClass) : array
    {
        $expands = [];
        if ($request->query->has('expand')) {
            $expandString = $request->query->get('expand');
            $bits = explode(',', $expandString);
            foreach ($bits as $bit) {
                if (strpos($bit, '.') !== false) {
                    $names = explode('.', $bit);
                    $expands[] = $this->walkArray($names, $entityClass);
                } else {
                    $tmp = $this->verifyPropName($bit, $entityClass);
                    $tmp['children'] = null;
                    $expands[] = $tmp;
                }
            }

            $this->logger->debug($this->loglbl.'found expand', $expands);

            return $expands;
        } else {
            $this->logger->debug($this->loglbl.'No expands found');

            return [];
        }
    }

    private function walkArray(array $propNames, string $entityClass, bool $forFilter = false) : array
    {
        $ret = $this->verifyPropName($propNames[0], $entityClass, $forFilter);
        if (count($propNames) == 1) {
            $ret['children'] = null;
        } else {
            array_shift($propNames);
            $ret['children'] = $this->walkArray($propNames, $entityClass, $forFilter);
        }

        return $ret;
    }

    private function verifyPropName(string $bit, string $entityClass, bool $forFilter = false) : array
    {
        $found = false;
        foreach ($this->entityParser->getProps($entityClass) as $prop) {
            if ($prop['label'] == $bit) {
                if (($prop['propType'] == PropType::PLURAL_PROP || $prop['propType'] == PropType::SINGLE_PROP) || $forFilter) {
                    $found = $prop;
                }
            }
        }
        if (!$found) {
            throw new BadRequestHttpException('Illegal '.($forFilter ? 'filter' : 'expand')." literal: '".$bit."'");
        } else {
            $this->logger->debug($this->loglbl.'Found valid prop for '.($forFilter ? 'filter' : 'expand').': '.$bit);

            return $found;
        }
    }

    /**
     * Extracts and validates filter parameters from the request.
     *
     * @param string                                    $entityClass
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array
     */
    public function extractFilters(string $entityClass, Request $request) : array
    {
        $queryValues = $this->fetchGetValuesFor("filter", $request);
        $filters = [];

        foreach ($queryValues as $queryValue) {
            $bits = explode(";", $queryValue);
            $subFilter = array();
            foreach ($bits as $bit) {
                $subFilter[] = $this->explodeAndCheckFilter($bit, $entityClass);
            }
            $filters[] = $subFilter;
        }

        if (count($queryValues) > 0) {
            $this->logger->debug($this->loglbl.'Found filters', $filters);
        } else {
            $this->logger->debug($this->loglbl.'No filters found');
        }

        return $filters;
    }

    /**
     * As PHP (with Symfony following its lead) does not handle multiple GET parameters
     * with the same name, a bit of tinkering is needed to be able to properly implement filters.
     *
     * @param                                           $key string name of the parameter to be extracted
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array array of values for the key specified (empty if not found)
     */
    protected function fetchGetValuesFor(string $key, Request $request) : array
    {
        $values = array();

        if ($request->server->has('QUERY_STRING') && strlen($request->server->get('QUERY_STRING')) > 0) {
            $queryData = explode('&', $request->server->get('QUERY_STRING'));

            foreach ($queryData as $param) {
                list($name, $value) = explode('=', $param, 2);
                if ($name == $key) {
                    $values[] = urldecode($value);
                }
            }
        }

        return $values;
    }

    private function explodeAndCheckFilter(string $filter, string $entityClass) : array
    {
        $bits = explode(',', $filter);
        $bits[1] = strtolower($bits[1]);
        if (count($bits) > 3) {
            throw new BadRequestHttpException("Illegal filter expression: '".$filter."'");
        } elseif ((count($bits) < 3) && !($bits[1] == 'isnull' || $bits[1] == 'isnotnull')) {
            throw new BadRequestHttpException("Illegal filter expression: '".$filter."'");
        }

        if (!in_array($bits[1], Consts::validOperands)) {
            throw new BadRequestHttpException("Illegal filter operand in expression: '".$filter."'");
        }

        $filterExp = [];

        if (strpos($bits[0], '.') !== false) {
            $names = explode('.', $bits[0]);
            $filterExp['prop'] = $this->walkArray($names, $entityClass, true);
        } else {
            $tmp = $this->verifyPropName($bits[0], $entityClass, true);
            $tmp['children'] = null;
            $filterExp['prop'] = $tmp;
        }

        $filterExp['operand'] = $bits[1];
        $filterExp['value'] = array_key_exists(2, $bits) ? $bits[2] : null;

        return $filterExp;
    }
}