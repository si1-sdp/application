<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use Psr\Log\LoggerInterface;
use DgfipSI1\Application\Exception\RuntimeException;

/**
 * Implements methods for loggerAwareInterface
 */
trait LoggerAwareTrait
{
    /**
     * @var LoggerInterface|null $logger
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     *
     *  @return self
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }
        if (method_exists($this, 'getContainer') && $this->getContainer()->has('logger')) {
            /** @var LoggerInterface $contLogger */
            $contLogger = $this->getContainer()->get('logger');

            return $contLogger;
        }

        throw new RuntimeException('No logger implementation has been set.');
    }
}
