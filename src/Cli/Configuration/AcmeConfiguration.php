<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class AcmeConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('acmephp');

        $this->createRootNode($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function createRootNode(ArrayNodeDefinition $rootNode)
    {
        $this->createStorageNode($rootNode->children()->arrayNode('storage')->isRequired());
        $this->createMonitoringNode($rootNode->children()->arrayNode('monitoring')->isRequired());
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function createStorageNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->booleanNode('enable_backup')->defaultTrue()->end()
                ->arrayNode('formatters')
                    ->defaultValue([])
                    ->prototype('enum')->values(['nginxproxy'])->isRequired()->end()
                ->end()
            ->end();

        $this->createAdapterNode($rootNode->children()->arrayNode('master')->isRequired());
        $this->createAdapterNode($rootNode->children()->arrayNode('slaves')->defaultValue([])->prototype('array'));
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function createMonitoringNode(ArrayNodeDefinition $rootNode)
    {
    }

    /**
     * @param ArrayNodeDefinition $parentNode
     */
    private function createAdapterNode(ArrayNodeDefinition $parentNode)
    {
        $parentNode
            ->children()
                ->enumNode('type')->values(['local', 'ftp', 'sftp'])->isRequired()->end()
                ->scalarNode('root')->isRequired()->end()
            ->end();
    }
}
