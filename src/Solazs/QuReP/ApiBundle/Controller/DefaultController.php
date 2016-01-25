<?php

namespace Solazs\QuReP\ApiBundle\Controller;

use Solazs\QuReP\ApiBundle\Resources\Action;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DefaultController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @param Request $request
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
                break;
            case Action::GET_COLLECTION:
                $data = $dataHandler->getAll($action['class']);
                break;
            case Action::UPDATE_SINGLE:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->update($action['class'], $action['id'], $postData);
                break;
            case Action::UPDATE_COLLECTION:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->bulkUpdate($action['class'], $postData);
                break;
            case Action::POST_SINGLE:
                $postData = json_decode($request->getContent(), true);
                if ($postData === null) {
                    throw new BadRequestHttpException('Invalid JSON or null content');
                }
                $data = $dataHandler->create($action['class'], $postData);
                break;
            case Action::DELETE_SINGLE:
                $dataHandler->delete($action['class'], $action['id']);
                $data = null;
                break;
            case Action::DELETE_COLLECTION:
                $dataHandler->deleteCollection($action['class'], $request->request->all());
                $data = null;
                break;
            default:
                $data = null;
        }

        $response = new Response();

        if ($data instanceof Form) {
            //readable error msg
        }

        if ($data !== null) {
            $jsonData = json_encode($data);
            $response->setContent($jsonData);
            $response->headers->set('Content-type', 'application/json');
        } else {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
