<?php

namespace QuReP\ApiBundle\Controller;

use QuReP\ApiBundle\Resources\Action;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param string $apiRoute
     * @return array
     */
    public function indexAction(Request $request, $apiRoute)
    {
        $action = $this->get('qurep_api.route_analyzer')->getActionAndEntity($request, $apiRoute);
        $dataHandler = $this->get('qurep_api.data_handler');
        switch ($action['action']) {
            case Action::GET_SINGLE:
                $data = $dataHandler->get($action['class'], $action['id']);
                if (!$data)
                {
                    throw new NotFoundHttpException('Entity with id ' . $action['id'] . ' was not found in the database.');
                }
                break;
            case Action::GET_COLLECTION:
                $data = $dataHandler->getAll($action['class']);
                break;
            case Action::UPDATE_SINGLE:
                $data = null;
                break;
            case Action::UPDATE_COLLECTION:
                $data = null;
                break;
            case Action::POST_SINGLE:
                $data = null;
                break;
            case Action::DELETE_SINGLE:
                $dataHandler->delete($action['class'], $action['id']);
                $data = null;
                break;
            case Action::DELETE_COLLECTION:
                $dataHandler->deleteCollection($action['class'], $request);
                $data = null;
                break;
            default:
                $data = null;
        }

        $response = new Response();

        if ($data !== null) {
            $jsonData = $this->get('jms_serializer')->serialize($data, 'json');
            $response->setContent($jsonData);
            $response->headers->set('Content-type', 'application/json');
        } else {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
