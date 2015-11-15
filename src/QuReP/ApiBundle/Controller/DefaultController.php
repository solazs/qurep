<?php

namespace QuReP\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/{apiRoute}", requirements={"apiRoute"=".+"})
     * @Template()
     * @param string $apiRoute
     * @return array
     */
    public function indexAction($apiRoute)
    {
        return array('name' => $apiRoute);
    }
}
