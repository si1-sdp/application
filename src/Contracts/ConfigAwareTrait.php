<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use DgfipSI1\ConfigHelper\ConfigHelperInterface;

/**
 * Implements methods for ConfigAwareInterface
 */
trait ConfigAwareTrait
{
    /**
     * @var ConfigHelperInterface
     */
    protected $config;

    /**
     * Set the config management object.
     *
     * @param ConfigHelperInterface $config
     *
     * @return $this
     */
    public function setConfig(ConfigHelperInterface $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the config management object.
     *
     * @return ConfigHelperInterface
     */
    public function getConfig()
    {
        return $this->config;
    }
}
