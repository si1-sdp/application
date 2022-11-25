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
interface LoggerAwareInterface
{
    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return self
     */
    public function setLogger(LoggerInterface $logger);
    /**
     * Get the logger.
     *
     * @return LoggerInterface
     */
    public function getLogger();
}
