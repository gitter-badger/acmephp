<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class AccountRegisterCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('register')
            ->setDefinition(array(
                new InputArgument('email', InputArgument::OPTIONAL, 'An e-mail to use when certificates will expire soon'),
                new InputOption('agreement', null, InputOption::VALUE_REQUIRED, 'The server usage conditions you agree with', 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf'),
            ))
            ->setDescription('Register your account private key in the ACME server')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command creates an account key in the master storage if
needed and register it in the ACME server provided by the option --server (by default
it will use Let's Encrypt servers).

  <info>php %command.full_name%</info>

You can add an e-mail that will be added to your registration in order to alert you when
certificates will expire soon:

  <info>php %command.full_name% acmephp@example.com</info>
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    }
}
