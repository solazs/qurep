<?php

namespace Solazs\QuReP\ApiBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use JMS\SerializerBundle\DependencyInjection\JMSSerializerExtension;
use JMS\SerializerBundle\JMSSerializerBundle;
use Solazs\QuReP\ApiBundle\Resources\Action;
use Solazs\QuReP\ApiBundle\Serializer\FieldsListExclusionStrategy;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param Request $request
     * @param string  $apiRoute
     * @return array
     */
    public function indexAction(Request $request, string $apiRoute)
    {
        /* @var $logger \Monolog\Logger */
        $logger = $this->get('logger');
        $logger->info("ApiController:indexAction invoked with route: ".$apiRoute);
        $routeAnalyzer = $this->get('qurep_api.route_analyzer');
        $action = $routeAnalyzer->getActionAndEntity($request, $apiRoute);
        $dataHandler = $this->get('qurep_api.data_handler');
        $dataHandler->setFilters($routeAnalyzer->extractFilters($action['class'], $request));
        $statusCode = 200;
        switch ($action['action']) {
            case Action::GET_SINGLE:
                $data = $dataHandler->get($action['class'], $action['id']);
                break;
            case Action::GET_COLLECTION:
                $data = $dataHandler->getAll($action['class'], $routeAnalyzer->extractPaging($request));
                break;
            case Action::UPDATE_SINGLE:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->update($action['class'], $action['id'], $postData);
                $statusCode = 201;
                break;
            case Action::UPDATE_COLLECTION:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->bulkUpdate($action['class'], $postData);
                $statusCode = 201;
                break;
            case Action::POST_SINGLE:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->create($action['class'], $postData);
                $statusCode = 201;
                break;
            case Action::DELETE_SINGLE:
                $dataHandler->delete($action['class'], $action['id']);
                $data = null;
                $statusCode = 204;
                break;
            case Action::DELETE_COLLECTION:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $dataHandler->deleteCollection($action['class'], $postData);
                $data = null;
                $statusCode = 204;
                break;
            default:
                $data = null;
        }

        $response = new Response();
        $meta = [];

        if ($data !== null) {
            if (array_key_exists('meta', $data)) {
                foreach ($data['meta'] as $key => $value) {
                    $meta[$key] = $value;
                }
                unset($data['meta']);
                $data = $data['data'];
            }

            $expands = $routeAnalyzer->extractExpand($request, $action['class']);
            $data = $this->get("qurep_api.entity_expander")->expandEntity(
              $expands,
              $action['class'],
              $data,
              in_array(
                $action['action'],
                [
                  Action::DELETE_COLLECTION,
                  Action::GET_COLLECTION,
                  Action::UPDATE_COLLECTION,
                ]
              ),
              $dataHandler
            );
            /** @var $serializer Serializer */
            $serializer = $this->get('jms_serializer');
            $serializationContext = new SerializationContext();
            $serializationContext->addExclusionStrategy(new FieldsListExclusionStrategy($dataHandler, $expands));
            $jsonData = $serializer->serialize(
              ["data" => $data, "meta" => $meta],
              "json",
              $serializationContext
            );
            $response->setContent($jsonData);
            $response->headers->set('Content-type', 'application/json');
            $response->setStatusCode($statusCode);
        } else {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
