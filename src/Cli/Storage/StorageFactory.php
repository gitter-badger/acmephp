<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Cli\Storage;

use AcmePhp\Cli\Exception\AcmeCliException;
use AcmePhp\Persistence\Adapter\AdapterInterface;
use AcmePhp\Persistence\Adapter\FlysystemAdapter;
use AcmePhp\Persistence\Adapter\LocalAdapter;
use AcmePhp\Persistence\Formatter\FormatterInterface;
use AcmePhp\Persistence\Formatter\NginxProxyFormatter;
use AcmePhp\Persistence\Storage;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class StorageFactory
{
    /**
     * @var array
     */
    private $master;

    /**
     * @var array
     */
    private $slaves;

    /**
     * @var array
     */
    private $formatters;

    /**
     * @var boolean
     */
    private $backupEnabled;

    /**
     * @param array $master
     * @param array $slaves
     * @param array $formatters
     * @param boolean $backupEnabled
     */
    public function __construct(array $master, array $slaves, array $formatters, $backupEnabled)
    {
        $this->master = $master;
        $this->slaves = $slaves;
        $this->formatters = $formatters;
        $this->backupEnabled = $backupEnabled;
    }

    /**
     * @return Storage
     */
    public function createStorage()
    {
        $storage = new Storage($this->createAdapter($this->master), $this->backupEnabled);

        foreach ($this->slaves as $slave) {
            $storage->addAdapter($this->createAdapter($slave));
        }

        foreach ($this->formatters as $formatter) {
            $storage->addFormatter($this->createFormatter($formatter));
        }

        return $storage;
    }

    /**
     * @param array $adapterConfig
     * @return AdapterInterface
     */
    private function createAdapter($adapterConfig)
    {
        if ($adapterConfig['type'] === 'local') {
            return new LocalAdapter($adapterConfig['root']);
        }

        if ($adapterConfig['type'] === 'ftp') {
            return new FlysystemAdapter(new Filesystem(new Ftp([
                'host' => $adapterConfig['host'],
                'username' => $adapterConfig['username'],
                'password' => $adapterConfig['password'],
                'port' => $adapterConfig['port'],
                'root' => $adapterConfig['root'],
                'passive' => $adapterConfig['passive'],
                'ssl' => $adapterConfig['ssl'],
                'timeout' => $adapterConfig['timeout'],
            ])));
        }

        if ($adapterConfig['type'] === 'sftp') {
            return new FlysystemAdapter(new Filesystem(new SftpAdapter([
                'host' => $adapterConfig['host'],
                'username' => $adapterConfig['username'],
                'password' => $adapterConfig['password'],
                'port' => $adapterConfig['port'],
                'root' => $adapterConfig['root'],
                'privateKey' => $adapterConfig['private_key'],
                'timeout' => $adapterConfig['timeout'],
            ])));
        }

        throw new AcmeCliException('Type of adapter "%s" is not supported (supported: local, ftp, sftp)');
    }

    /**
     * @param string $name
     * @return FormatterInterface
     */
    private function createFormatter($name)
    {
        $formatters = [
            'nginxproxy' => NginxProxyFormatter::class,
        ];

        if (isset($formatters[$name])) {
            return new $formatters[$name]();
        }

        throw new AcmeCliException('Type of formatter "%s" is not supported (supported: '.implode(array_keys($formatters)).')');
    }
}
