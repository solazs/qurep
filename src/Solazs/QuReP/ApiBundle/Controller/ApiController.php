<?php

namespace Solazs\QuReP\ApiBundle\Controller;

use Solazs\QuReP\ApiBundle\Resources\Action;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param Request $request
     * @param string $apiRoute
     * @return array
     */
    public function indexAction(Request $request, $apiRoute)
    {
        $routeAnalyzer = $this->get('qurep_api.route_analyzer');
        $action = $routeAnalyzer->getActionAndEntity($request, $apiRoute);
        $dataHandler = $this->get('qurep_api.data_handler');
        $filters = $routeAnalyzer->extractFilters($action['class']);
        $statusCode = 200;
        switch ($action['action']) {
            case Action::GET_SINGLE:
                $data = $dataHandler->get($action['class'], $action['id']);
                break;
            case Action::GET_COLLECTION:
                $data = $dataHandler->getAll($action['class'], $filters);
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
                $dataHandler->deleteCollection($action['class'], $request->request->all());
                $data = null;
                $statusCode = 204;
                break;
            default:
                $data = null;
        }

        $response = new Response();

        if ($data !== null) {
            $jsonData = $this->get('jms_serializer')->serialize($data, "json");
            $response->setContent($jsonData);
            $response->headers->set('Content-type', 'application/json');
            $response->setStatusCode($statusCode);
        } else {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
