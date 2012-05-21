<?php

namespace Claroline\CoreBundle\Listener;

use Doctrine\ORM\Events;
use Claroline\CoreBundle\Library\Testing\FunctionalTestCase;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Tests\Stub\Entity\SpecificResource1;
use Claroline\CoreBundle\Tests\Stub\Entity\SpecificResource2;

class ResourceExtenderTest extends FunctionalTestCase
{
    /** @var Doctrine\ORM\EntityManager */
    private $em;

    protected function setUp()
    {
        parent::setUp();
        $this->em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->markTestSkipped("AbstractResource 'replaced' by ResourceInstace");
    }

    public function testResourceExtenderIsSubscribed()
    {

        $listeners = $this->em->getEventManager()->getListeners(Events::loadClassMetadata);
        
        foreach ($listeners as $listener)
        {
            if ($listener instanceof ResourceExtender)
            {
                return;
            }
        }
        
        $this->fail('The ResourceExtender listener is not attached to the default EntityManager.');
    }
    
    public function testExtenderAddsPluginResourceTypesToTheDiscriminatorMap()
    {
        $this->registerSpecificResourceTypes();     
        $this->createSpecificResources();
        
        $allRes = $this->em
            ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->findAll();
        $firstSpecRes = $this->em
            ->getRepository('Claroline\CoreBundle\Tests\Stub\Entity\SpecificResource1')
            ->findAll();
        $secondSpecRes = $this->em
            ->getRepository('Claroline\CoreBundle\Tests\Stub\Entity\SpecificResource2')
            ->findAll();
        $dirRes = $this->em
            ->getRepository('Claroline\CoreBundle\Entity\Resource\Directory')
            ->findAll();
        
        $this->assertEquals(4, count($allRes));
        $this->assertEquals(1, count($firstSpecRes));
        $this->assertEquals(2, count($secondSpecRes));
        $this->assertEquals(1, count($dirRes));
    }
    
    /**
     * Helper method inserting two plugin resource types. It uses raw sql to avoid
     * loading entity metadata (otherwise the extender listener will be called
     * before the insertion of plugin types and these types won't be added to
     * the discriminator map : this is an issue only if resource types are added 
     * and resources are retrieved via the entity manager in the same script 
     * invocation, which is unlikely to happen in a production context)
     */
    private function registerSpecificResourceTypes()
    {
        $conn = $this->em->getConnection();
        
        // Insert a fake extension plugin
        $sql = "INSERT INTO claro_plugin (type, bundle_fqcn, vendor_name, short_name, name_translation_key, description, discr)"
            . " VALUES ('plugin x', 'TestTest', '', 'Test', 'test', 'test', 'extension')";
        $conn->exec($sql);       
        $pluginId = $conn->lastInsertId();
        $sql = "INSERT INTO claro_extension (id) VALUES ({$pluginId})";
        $conn->exec($sql);
        
        // Insert two specific resource types (see test/Stub/Entity)
        $sql = "INSERT INTO claro_resource_type (plugin_id, class, type, is_listable, is_navigable)"
            . " VALUES ({$pluginId}, 'Claroline\\\CoreBundle\\\Tests\\\Stub\\\Entity\\\SpecificResource1', 'SpecificResource1', true, false),"
            . " ({$pluginId}, 'Claroline\\\CoreBundle\\\Tests\\\Stub\\\Entity\\\SpecificResource2', 'SpecificResource2', true, false)";
        $conn->exec($sql);
    }
    
    private function createSpecificResources()
    {      
        $this->loadUserFixture();
      
        $firstRes = new SpecificResource1();
        $firstRes->setSomeField('Test');
        $firstRes->setUser($this->getFixtureReference('user/user'));
        
        $secondRes = new SpecificResource2();
        $secondRes->setSomeField('Test');
        $secondRes->setUser($this->getFixtureReference('user/ws_creator'));
        
        $thirdRes = new SpecificResource2();
        $thirdRes->setSomeField('Test');
        $thirdRes->setUser($this->getFixtureReference('user/admin'));
        
        $fourthRes = new Directory();
        $fourthRes->setName('Test');
        $fourthRes->setUser($this->getFixtureReference('user/admin'));
        
        $this->em->persist($firstRes);
        $this->em->persist($secondRes);
        $this->em->persist($thirdRes);
        $this->em->persist($fourthRes);
        
        $this->em->flush();
    }
}