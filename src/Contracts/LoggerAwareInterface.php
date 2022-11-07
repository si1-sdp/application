<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use Consolidation\Config\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * LoggerAwareInterface - get access to logger
 */
interface LoggerAwareInterface extends \Psr\Log\LoggerAwareInterface
{
    /**
     * Get the logger.
     *
     * @return LoggerInterface
     */
    public function logger();
}
