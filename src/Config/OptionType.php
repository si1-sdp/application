<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Symfony\Component\Console\Input\InputOption;

/**
 * Option types
 */
enum OptionType: string
{
    case Boolean = 'boolean';
    case Scalar  = 'scalar';
    case Array   = 'array';

    /**
     * translate option type into symfony InputOption mode
     *
     * @return int
     */
    public function mode(): int
    {
        return match ($this) {
            OptionType::Boolean => InputOption::VALUE_NONE|InputOption::VALUE_NEGATABLE,
            OptionType::Scalar  => InputOption::VALUE_REQUIRED,
            OptionType::Array   => InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED
        };
    }
}
