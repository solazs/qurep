<?php

namespace QuReP\ApiBundle\Tests\Controller;

use QuReP\ApiBundle\Tests\RestTestCase;

class DefaultControllerTest extends RestTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $data = $client->request('GET', '/users/1');

        $this->assertJsonResponse($client->getResponse(), 200);
        $this->assertEquals('solazs', json_decode($client->getResponse()->getContent(), true)['username']);
    }
}
