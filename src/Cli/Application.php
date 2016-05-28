<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli;

use AcmePhp\Cli\Command\AccountRegisterCommand;
use AcmePhp\Cli\Command\ContainerAwareCommand;
use AcmePhp\Cli\Configuration\AcmeConfiguration;
use AcmePhp\Persistence\StorageInterface;
use AcmePhp\Ssl\Generator\KeyPairGenerator;
use AcmePhp\Ssl\KeyPair;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Application extends BaseApplication
{
    const VERSION = '1.0.0-alpha8';

    const CONFIG_FILE = '~/.acmephp/acmephp.conf';
    const CONFIG_REFERENCE = __DIR__.'/../../res/acmephp.conf.dist';

    /**
     * @var string
     */
    private $configFile;

    /**
     * @var array
     */
    private $config;

    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('Acme PHP - Let\'s Encrypt client', self::VERSION);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$this, 'loadConfiguration']);
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$this, 'buildContainer']);
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$this, 'loadAccountKey']);
        $dispatcher->addListener(ConsoleEvents::COMMAND, [$this, 'compileContainer']);

        $this->setDispatcher($dispatcher);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption(
            'server',
            null,
            InputOption::VALUE_REQUIRED,
            'Set the ACME server directory to use',
            'https://acme-v01.api.letsencrypt.org/directory'
        ));

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands()
    {
        return [
            new HelpCommand(),
            new ListCommand(),
            new AccountRegisterCommand(),
        ];
    }

    /**
     * @return array
     */
    public function loadConfiguration(ConsoleCommandEvent $event)
    {
        $filesystem = new Filesystem();

        $this->configFile = Path::canonicalize(self::CONFIG_FILE);

        if (!$filesystem->exists($this->configFile)) {
            try {
                if (!$filesystem->exists(dirname($this->configFile))) {
                    $filesystem->mkdir(dirname($this->configFile));
                }

                $filesystem->dumpFile($this->configFile, file_get_contents(self::CONFIG_REFERENCE));
            } catch (\Exception $e) {
                throw new IOException('Configuration file '.$this->configFile.' is not writable.', 0, $e);
            }

            $event->getOutput()->writeln(sprintf(
                'Configuration file %s did not not exists, it has been created using default values',
                $this->configFile
            ));
        } else {
            $event->getOutput()->writeln(sprintf('Using configuration file %s', $this->configFile));
        }

        $this->config = [ 'acmephp' => Yaml::parse(file_get_contents($this->configFile)) ];
    }

    /**
     * @param ConsoleCommandEvent $event
     */
    public function buildContainer(ConsoleCommandEvent $event)
    {
        $this->container = new ContainerBuilder();

        // Application services and parameters
        $this->container->set('application', $this);
        $this->container->setParameter('application.version', self::VERSION);
        $this->container->setParameter('application.config_file', $this->configFile);
        $this->container->setParameter('application.server', $event->getInput()->getOption('server'));

        // Load configuration
        $processor = new Processor();
        $config = $processor->processConfiguration(new AcmeConfiguration(), $this->config);

        $this->container->setParameter('storage.enable_backup', $config['storage']['enable_backup']);
        $this->container->setParameter('storage.master', $config['storage']['master']);
        $this->container->setParameter('storage.slaves', $config['storage']['slaves']);
        $this->container->setParameter('storage.formatters', $config['storage']['formatters']);

        // Load services
        $loader = new XmlFileLoader($this->container, new FileLocator(__DIR__.'/Resources'));
        $loader->load('services.xml');
    }

    /**
     * @param ConsoleCommandEvent $event
     */
    public function loadAccountKey(ConsoleCommandEvent $event)
    {
        /** @var StorageInterface $storage */
        $storage = $this->container->get('storage');

        if (! $storage->hasAccountKeyPair()) {
            $event->getOutput()->writeln('Account key pair does not exist, generating it...');
            $accountKeyPair = $this->container->get('key_pair_generator')->generateKeyPair();
        } else {
            $accountKeyPair = $storage->loadAccountKeyPair();
        }

        // Synchronize slaves
        $storage->storeAccountKeyPair($accountKeyPair);

        $this->container->set('account_key_pair', $accountKeyPair);
    }

    /**
     * @param ConsoleCommandEvent $event
     */
    public function compileContainer(ConsoleCommandEvent $event)
    {
        // Compile and pass container to the command
        $this->container->compile();

        $command = $event->getCommand();

        if ($command instanceof ContainerAwareCommand) {
            $command->setContainer($this->container);
        }
    }
}
