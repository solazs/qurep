<?php

namespace Solazs\QuReP\ApiBundle\Controller;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Solazs\QuReP\ApiBundle\Resources\Action;
use Solazs\QuReP\ApiBundle\Serializer\FieldsListExclusionStrategy;
use Solazs\QuReP\ApiBundle\Services\DataHandler;
use Solazs\QuReP\ApiBundle\Services\RouteAnalyzer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * Main controller of QuReP.
 * Handles all calls, determines action to take and creates response.
 **/
class ApiController extends Controller
{
    /**
     * Main action used to catch all requests.
     *
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param Request $request
     * @param string  $apiRoute
     * @return array|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, string $apiRoute)
    {
        /* @var \Monolog\Logger $logger */
        $logger = $this->get('logger');
        $logger->info("ApiController:indexAction invoked with route: ".$apiRoute);

        /** @var RouteAnalyzer $routeAnalyzer */
        $routeAnalyzer = $this->get('qurep_api.route_analyzer');
        $action = $routeAnalyzer->getActionAndEntity($request, $apiRoute); // Determine class and action to take

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get('qurep_api.data_handler');
        $dataHandler->setFilters($routeAnalyzer->extractFilters($action['class'], $request)); // Fetch filter expressions


        // Execute action using the DataHandler
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

        // Create response

        $response = new Response();
        $meta = [];

        if ($data !== null) {
            // We have some data to return

            // Fetch meta from the data if any. TODO: this is a bit hacky.
            if (array_key_exists('meta', $data)) {
                foreach ($data['meta'] as $key => $value) {
                    $meta[$key] = $value;
                }
                unset($data['meta']);
                $data = $data['data'];
            }

            // Fill in expanded data
            $expands = $routeAnalyzer->extractExpand($request, $action['class']);
            if (count($expands) > 0) {
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
            } else {
                $logger->debug('ApiController:indexAction expands array is empty, skipping expandEntity call');
            }

            //Serialization
            /** @var $serializer Serializer */
            $serializer = $this->get('jms_serializer');
            // We need to create a custom SerializationContext to be able to whitelist properties in the response
            // TODO: projection (or field list) should be exposed on the API
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
            // Empty response
            $response->setStatusCode(204);
        }

        // Send data. (TBD: Might be more elegant to write a custom view layer)

        return $response;
    }
}
