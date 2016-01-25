<?php

namespace Solazs\QuReP\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('qurep_api')
            ->children()
            ->arrayNode('entities')
            ->requiresAtLeastOneElement()
            ->prototype("array")
            ->children()
            ->scalarNode('entity_name')
            ->IsRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('class')
            ->IsRequired()
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
