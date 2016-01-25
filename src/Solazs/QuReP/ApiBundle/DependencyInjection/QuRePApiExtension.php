<?php

namespace Solazs\QuReP\ApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class QuRePApiExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        foreach ($config['entities'] as $entity) {
            if (class_exists($entity['class'], false)) {
                throw new InvalidArgumentException("Invalid configuration value! Class " . $entity['class'] . " does not exist");
            }
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $routeAnalyzerServiceDefinition = $container->getDefinition('qurep_api.route_analyzer');
        $routeAnalyzerServiceDefinition->addMethodCall('setConfig', array($config['entities']));

        $routeAnalyzerServiceDefinition = $container->getDefinition('qurep_api.entity_form_builder');
        $routeAnalyzerServiceDefinition->addMethodCall('setConfig', array($config['entities']));
    }

    public function getAlias()
    {
        return 'qurep_api';
    }
}
