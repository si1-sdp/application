<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use DgfipSI1\ConfigHelper\ConfigHelperInterface;

/**
 * ConfigAwareInterface - get access to config
 */
interface ConfigAwareInterface
{
   /**
     * Set the config reference.
     *
     * @param ConfigHelperInterface $config
     *
     * @return $this
     */
    public function setConfig(ConfigHelperInterface $config);

    /**
     * Get the config reference.
     *
     * @return ConfigHelperInterface
     */
    public function getConfig();
}
