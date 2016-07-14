<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.15.
 * Time: 21:27
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Solazs\QuReP\ApiBundle\Exception\RouteException;
use Solazs\QuReP\ApiBundle\Resources\Action;
use Solazs\QuReP\ApiBundle\Resources\Consts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class RouteAnalyzer
{
    protected $entities = null;
    protected $entityParser;

    function __construct(EntityParser $entityParser)
    {
        $this->entityParser = $entityParser;
    }

    public function setConfig($entities)
    {
        $this->entities = $entities;
    }

    public function getActionAndEntity(Request $request, $apiRoute)
    {
        $class = null;
        $isBulk = false;
        $id = null;
        $method = $request->getMethod();

        if (strpos($apiRoute, '/') === false) {
            $class = $this->getEntityClassFromString($apiRoute);
        } else {
            if (strpos($apiRoute, '/') != strrpos($apiRoute, '/')) {
                throw new RouteException('Route '.$apiRoute.' is invalid, too many / signs.');
            } else {
                $class = $this->getEntityClassFromString(substr($apiRoute, 0, strpos($apiRoute, '/')));
                $id = substr($apiRoute, strpos($apiRoute, '/') + 1, strlen($apiRoute) - strpos($apiRoute, '/'));
                if ($id === "bulk") {
                    $isBulk = true;
                }
            }
        }

        $action = null;

        if ($method === "GET") {
            if ($isBulk) {
                throw new RouteException('Can not handle bulk GET request');
            }
            if ($id !== null) {
                $action = Action::GET_SINGLE;
            } else {
                $action = Action::GET_COLLECTION;
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
                    throw new MethodNotAllowedException(array("GET", "POST", "DELETE"), $method.' not supported.');
                }
            }
        }

        if ($action === null) {
            throw new RouteException('Could not determine action to take');
        }

        return array('class' => $class, 'action' => $action, 'id' => $id);
    }

    private function getEntityClassFromString($string)
    {
        foreach ($this->entities as $entity) {
            if ($string === $entity['entity_name']) {
                return $entity['class'];
            }
        }
        throw new RouteException('Could not find entity '.$string);
    }

    public function extractExpand(Request $request, string $entityClass) : array
    {
        $expands = [];
        if ($request->query->has("expand")) {
            $expandString = $request->query->get('expand');
            $bits = explode(',', $expandString);
            foreach ($bits as $bit) {
                if (strpos($bit,'.') !== false){
                    $names = explode('.', $bit);
                    $expands[] = $this->walkArray($names, $entityClass);
                } else {
                    $tmp = $this->verifyPropName($bit, $entityClass);
                    $tmp['children'] = null;
                    $expands[] = $tmp;
                }
            }

            return $expands;
        } else {
            return [];
        }
    }

    private function walkArray(array $propNames, string $entityClass, bool $forFilter = false)
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

    protected function verifyPropName(string $bit, string $entityClass, bool $forFilter = false)
    {
        $found = false;
        foreach ($this->entityParser->getProps($entityClass) as $prop) {
            if ($prop['name'] == $bit) {
                if (($prop['propType'] == Consts::pluralProp || $prop['propType'] == Consts::singleProp) || $forFilter) {
                    $found = $prop;
                }
            }
        }
        if (!$found) {
            throw new BadRequestHttpException('Illegal ' . ($forFilter ? 'filter' : 'expand') . " literal: '" . $bit . "'");
        } else {
            return $found;
        }
    }

    public function extractFilters($entityClass)
    {
        $queryValues = $this->fetchGetValuesFor("filter");
        $filters = array();

        foreach ($queryValues as $queryValue) {
            $bits = explode(";", $queryValue);
            $subFilter = array();
            foreach ($bits as $bit) {
                $subFilter[] = $this->explodeAndCheckFilter($bit, $entityClass);
            }
            $filters[] = $subFilter;
        }

        return $filters;
    }

    protected function explodeAndCheckFilter($filter, $entityClass)
    {
        $bits = explode(',', $filter);
        $bits[1] = strtolower($bits[1]);
        if (count($bits) > 3) {
            throw new BadRequestHttpException("Illegal filter expression: '".$filter."'");
        } elseif ((count($bits) < 3) && !($bits[1] == 'isnull' || $bits[1] == 'isnotnull')) {
            throw new BadRequestHttpException("Illegal filter expression: '".$filter."'");
        }

        if (!in_array($bits[1], Consts::validOperands)){
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

    /**
     * As PHP (with Symfony following its lead) does not handle multiple GET parameters
     * with the same name, a bit of tinkering is needed to be able to properly implement filters.
     *
     * @param $key string name of the parameter to be extracted
     * @return array array of values for the key specified (empty if not found)
     */
    protected function fetchGetValuesFor($key)
    {
        $values = array();

        if (array_key_exists('QUERY_STRING', $_SERVER)) {
            $queryData = explode('&', $_SERVER['QUERY_STRING']);

            foreach ($queryData as $param) {
                list($name, $value) = explode('=', $param, 2);
                if ($name == $key) {
                    $values[] = urldecode($value);
                }
            }
        }

        return $values;
    }
}