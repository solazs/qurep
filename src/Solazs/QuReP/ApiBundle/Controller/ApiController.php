<?php

namespace Solazs\QuReP\ApiBundle\Controller;

use JMS\Serializer\Serializer;
use Solazs\QuReP\ApiBundle\Resources\Action;
use Solazs\QuReP\ApiBundle\Resources\Consts;
use Solazs\QuReP\ApiBundle\Serializer\FieldsListExclusionStrategy;
use Solazs\QuReP\ApiBundle\Serializer\QuRePSerializationContext;
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
    protected $loglbl = Consts::qurepLogLabel.'ApiController: ';

    /**
     * Main action used to catch all requests.
     *
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param Request $request
     * @param string  $apiRoute
     * @return array|\Symfony\Component\HttpFoundation\Response
     * @throws \Solazs\QuReP\ApiBundle\Exception\RouteException
     */
    public function indexAction(Request $request, string $apiRoute)
    {
        /* @var \Monolog\Logger $logger */
        $loglbl = $this->loglbl.'indexAction: ';
        $logger = $this->get('logger');
        $logger->info($loglbl.'Invoked with route: '.$apiRoute);

        /** @var RouteAnalyzer $routeAnalyzer */
        $routeAnalyzer = $this->get('qurep_api.route_analyzer');
        // Determine entity class and action to take
        $action = $routeAnalyzer->getActionAndEntity($request, $apiRoute);

        $logger->debug($loglbl.'Got action array.', $action);

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get('qurep_api.data_handler');
        // Initialize DataHandler with filter & paging information from the query parameters
        $dataHandler->setupClass(
          $routeAnalyzer->extractFilters($action['class'], $request),
          $routeAnalyzer->extractPaging($request)
        );
        $logger->debug($loglbl.'Initialized DataHandler');


        // Execute action using the DataHandler
        $statusCode = 200;
        $meta = [];
        switch ($action['action']) {
            case Action::GET_SINGLE:
                $data = $dataHandler->get($action['class'], $action['id']);
                break;
            case Action::GET_COLLECTION:
                $data = $dataHandler->getAll($action['class'], $meta);
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
            case Action::META:
                $data = $dataHandler->meta($action['class']);
                break;
            default:
                $data = null;
        }

        $logger->debug($loglbl.'DataHandler returned.', ['data' => $data]);

        // Create response

        $response = new Response();

        if ($data !== null) {
            // We have some data to return

            // Extract expand information from the request
            $expands = $routeAnalyzer->extractExpand($request, $action['class']);
            if (count($expands) > 0) {
                // Expand our data
                $logger->debug($loglbl.'Expanding expands');
                $data = $this->get('qurep_api.entity_expander')->expandEntity(
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
                $logger->debug($loglbl.'expands array is empty, skipping expandEntity call');
            }

            //Serialization
            /** @var $serializer Serializer */
            $serializer = $this->get('jms_serializer');
            // We need to create a custom SerializationContext to be able to whitelist properties in the response
            // TODO: projection (or field list) should be exposed on the API
            $serializationContext = new QuRePSerializationContext();
            $serializationContext->addExclusionStrategy(new FieldsListExclusionStrategy($dataHandler, $expands));
            $logger->debug($loglbl.'serializing data');
            $jsonData = $serializer->serialize(
              ["data" => $data, "meta" => $meta],
              "json",
              $serializationContext
            );

            $response->setContent($jsonData);
            $response->headers->set('Content-type', 'application/json');
            $response->setStatusCode($statusCode);
            $logger->debug($loglbl.'Request processing is complete, returning data with status code '.$statusCode);
        } else {
            // Empty response
            $response->setStatusCode(204);
            $logger->debug($loglbl.'Returning empty response');
        }

        // Send data. TODO: It'd be more elegant to write a custom view layer.

        return $response;
    }
}
