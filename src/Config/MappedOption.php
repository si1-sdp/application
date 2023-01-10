<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Consolidation\AnnotatedCommand\Attributes\Option;
use DgfipSI1\Application\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Mapped Option class
 */
class MappedOption
{
    public const OPT_SHORT               = 'short_option';
    public const OPT_DESCRIPTION         = 'description';
    public const OPT_DEFAULT_VALUE       = 'default';
    public const OPT_TYPE                = 'type';
    public const OPT_REQUIRED            = 'required';

    /** @var InputOption|InputArgument $input */
    protected $input;
    /** @var string $name */
    protected $name;
    /** @var string $description */
    protected $description;
    /** @var mixed $defaultValue */
    protected $defaultValue;
    /** @var OptionType $type */
    protected $type;
    /** @var bool $required */
    protected $required;

    /** @var string|null $commandName */
    protected $commandName;

    /**
     * @param string                                  $name
     * @param OptionType                              $type
     * @param string                                  $description
     * @param string|null                             $optShort
     * @param array<mixed>|bool|float|int|string|null $default
     * @param bool                                    $required
     *
     * @return void;
     */
    public function __construct($name, $type, $description = '', $optShort = null, $default = null, $required = false)
    {
        $ioName = str_replace('_', '-', $name);
        $confName = self::getConfName($name);
        $this->name         = $confName;
        $this->type         = $type;
        $this->description  = $description;
        $this->required     = $required;
        if (null === $default) {
            if (OptionType::Boolean === $type) {
                $default = false;
            } elseif (OptionType::Array === $type) {
                $default = [];
            }
        }
        $this->defaultValue = $default;
        $mode = $type->mode($required);
        if (OptionType::Argument !== $type) {
            if (OptionType::Boolean === $type) {
                if (true === $default) {
                    $comment = " <comment>[default : true, use --no-$ioName to set to false]</comment>";
                } else {
                    $comment = " <comment>[default : false]</comment>";
                }
                $description = $description.$comment;
                $default = null;
            }
            $this->input = new InputOption($ioName, $optShort, $mode, $description, $default);
        } else {
            $this->input = new InputArgument($ioName, $mode, $description, $default);
        }
    }

    /**
     *
     * @param string              $name
     * @param array<string,mixed> $options
     *
     * @return MappedOption
     */
    public static function createFromConfig($name, $options)
    {
        /** @var string|null $shortOpt */
        $shortOpt   = array_key_exists(self::OPT_SHORT, $options) ? $options[self::OPT_SHORT] : null;
        /** @var string $optDesc */
        $optDesc    = array_key_exists(self::OPT_DESCRIPTION, $options) ? $options[self::OPT_DESCRIPTION] : '';
        /** @var array<mixed>|bool|float|int|string|null $optDefault */
        $optDefault = array_key_exists(self::OPT_DEFAULT_VALUE, $options) ? $options[self::OPT_DEFAULT_VALUE] : null;
        /** @var bool $required */
        $required    = array_key_exists(self::OPT_REQUIRED, $options) ? $options[self::OPT_REQUIRED] : false;
        if (!array_key_exists(self::OPT_TYPE, $options)) {
            throw new RuntimeException(sprintf('Missing option type for %s', $name));
        }
        /** @var string $optType */
        $optType = $options[self::OPT_TYPE];
        try {
            $type = OptionType::from($optType);
            $option = new MappedOption($name, $type, $optDesc, $shortOpt, $optDefault, $required);
        } catch (\ValueError $e) {
            throw new RuntimeException(sprintf("Unknown option type '%s' for option '%s'", $optType, $name));
        }

        return $option;
    }

    /**
     * inputOption getter
     *
     * @return InputOption
     */
    public function getOption()
    {
        if (!$this->input instanceof InputOption) {
            throw new RuntimeException("Cannot getOption() on InputArgument");
        }

        return $this->input;
    }
    /**
     * get symfony option mode
     *
     * @return int
     */
    public function getMode()
    {
        return $this->type->mode();
    }
    /**
     * inputArgument getter
     *
     * @return InputArgument
     */
    public function getArgument()
    {
        if (!$this->input instanceof InputArgument) {
            throw new RuntimeException("Cannot getArgument() on InputOption");
        }

        return $this->input;
    }
    /**
     * name getter
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    /**
     * description getter
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
    /**
     * command getter
     *
     * @return string|null
     */
    public function getCommand()
    {
        return $this->commandName;
    }
    /**
     * command setter
     *
     * @param string $command
     *
     * @return self
     */
    public function setCommand($command)
    {
        $this->commandName = $command;

        return $this;
    }
    /**
     * default value getter
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
    /**  isBool
     * @return bool
    */
    public function isBool(): bool
    {
        return (OptionType::Boolean === $this->type);
    }
    /**  isArray
    * @return bool
    */
    public function isArray(): bool
    {
        return (OptionType::Array === $this->type);
    }
    /**  isScalar
     * @return bool
    */
    public function isScalar(): bool
    {
        return (OptionType::Scalar === $this->type);
    }
    /**  isArgument
     * @return bool
    */
    public function isArgument(): bool
    {
        return (OptionType::Argument === $this->type);
    }
    /**
     * get configHelper compatible option name
     *
     * @param string $name
     *
     * @return string
     */
    public static function getConfName($name)
    {
        return str_replace(['.', '-' ], '_', $name);
    }
}
