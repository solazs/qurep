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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class RouteAnalyzer
{
    protected $entities = null;

    public function setConfig($entities)
    {
        $this->entities = $entities;
    }

    private function getEntityClassFromString($string)
    {
        foreach ($this->entities as $entity) {
            if ($string === $entity['entity_name']) {
                return $entity['class'];
            }
        }
        throw new RouteException('Could not find entity ' . $string);
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
                throw new RouteException('Route ' . $apiRoute . ' is invalid, too many / signs.');
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
                    throw new MethodNotAllowedException(array("GET", "POST", "DELETE"), $method . ' not supported.');
                }
            }
        }

        if ($action === null) {
            throw new RouteException('Could not determine action to take');
        }

        return array('class' => $class, 'action' => $action, 'id' => $id);
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

    }

    protected function explodeAndCheckFilter($filter, $entitClass)
    {
        $bits = explode(",", $filter);
        if (count($bits) > 3) {
            throw new BadRequestHttpException("Illegal filter expression: '" . $filter . "'");
        } elseif (count($bits) < 3) {
            if ($bits[1] != "isnull") {
                throw new BadRequestHttpException("Illegal filter expression: '" . $filter . "'");
            } else {


                // TODO: Check EVERYTHING and throw errors

                return array("prop" => $bits[0], "operand" => $bits[1], "value" => array_key_exists(2, $bits) ? $bits[2] : null);
            }
        }
    }

    /**
     * As PHP (with Symfony following its lead) does not handle multiple GET parameters
     * with the same name, a bit of tinkering is needed to be able to properly implement filters.
     * @param $key string name of the parameter to be extracted
     * @return array array of values for the key specified (empty if not found)
     */
    protected function fetchGetValuesFor($key)
    {
        $queryData = explode('&', $_SERVER['QUERY_STRING']);
        $values = array();

        foreach ($queryData as $param) {
            list($name, $value) = explode('=', $param, 2);
            if ($name == $key) {
                $values[] = urldecode($value);
            }
        }
        return $values;
    }
}