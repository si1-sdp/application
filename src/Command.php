<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use DgfipSI1\Application\Config\ConfiguredApplicationInterface;
use DgfipSI1\Application\Config\ConfiguredApplicationTrait;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * class Application
 */
class Command extends SymfonyCommand implements ConfiguredApplicationInterface
{
    use ConfiguredApplicationTrait;
}
