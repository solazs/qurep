<?php

namespace QuReP\ApiBundle\Controller;

use QuReP\ApiBundle\Resources\Action;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @Template()
     * @param string $apiRoute
     * @return array
     */
    public function indexAction(Request $request, $apiRoute)
    {
        $action = $this->get('qurep_api.route_analyzer')->getActionAndEntity($request, $apiRoute);
        switch ($action['action']){
            case Action::GET_SINGLE:

                break;
            case Action::GET_COLLECTION:

                break;
            case Action::UPDATE_SINGLE:

                break;
            case Action::UPDATE_COLLECTION:

                break;
            case Action::POST_SINGLE:

                break;
            case Action::DELETE_SINGLE:

                break;
            case Action::DELETE_COLLECTION:

                break;
        }
        return array('name' => $apiRoute . "action: " . $action['action'] . ', entityClass: ' . $action['class']);
    }
}
