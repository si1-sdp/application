<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use Consolidation\Config\ConfigInterface;

/**
 * ConfigAwareInterface - get access to config
 */
interface ConfigAwareInterface
{
   /**
     * Set the config reference.
     *
     * @param \Consolidation\Config\ConfigInterface $config
     *
     * @return $this
     */
    public function setConfig(ConfigInterface $config);

    /**
     * Get the config reference.
     *
     * @return \Consolidation\Config\ConfigInterface
     */
    public function conf();
}
