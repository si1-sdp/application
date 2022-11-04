<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\Contracts\ConfigAwareInterface;
use DgfipSI1\Application\Contracts\ConfigAwareTrait;
use DgfipSI1\Application\Contracts\LoggerAwareInterface;
use DgfipSI1\Application\Contracts\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * class Application
 */
class Command extends SymfonyCommand implements ConfigAwareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use ConfigAwareTrait;
}
