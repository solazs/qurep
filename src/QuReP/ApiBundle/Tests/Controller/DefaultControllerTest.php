<?php

namespace QuReP\ApiBundle\Tests\Controller;

use QuReP\ApiBundle\Tests\RestTestCase;

class DefaultControllerTest extends RestTestCase
{
    private $initialData = [
        [   // A full, valid user
            "username" => "test_elek",
            "displayName" => "Test Elek",
            "email" => "test@qurep.com"
        ],
        [   // test some validation

        ]
    ];

    public function setUp()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        if (!isset($metadatas)) {
            $metadatas = $em->getMetadataFactory()->getAllMetadata();
        }
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        if (!empty($metadatas)) {
            $schemaTool->createSchema($metadatas);
        }
        $this->postFixtureSetup();

        $fixtures = array(
            'Acme\MyBundle\DataFixtures\ORM\LoadUserData',
        );
        $this->loadFixtures($fixtures);
    }

    public function testIndex()
    {
        $client = static::createClient();


        $data = $client->request('GET', '/users/1');

        $this->assertJsonResponse($client->getResponse(), 200);
        $this->assertEquals('solazs', json_decode($client->getResponse()->getContent(), true)['username']);
    }
}
