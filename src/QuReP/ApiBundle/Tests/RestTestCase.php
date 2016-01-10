<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.08.
 * Time: 22:25
 */

namespace QuReP\ApiBundle\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class RestTestCase extends WebTestCase
{
    protected function assertJsonResponse(Response $response, $statusCode = 200)
    {
        $this->assertEquals(
            $statusCode, $response->getStatusCode(),
            $response->getContent()
        );
        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            $response->headers
        );
    }
}