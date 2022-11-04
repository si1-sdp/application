<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * ApplicationAwareInterface
 */
interface ApplicationAwareInterface extends ConfigurationInterface
{
    /**
     * @return array<MappedOption>
     */
    public function getConfigOptions();
}
