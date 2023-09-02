#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Main;

use Closure;
use Exception;
use Generator;
use Grano22\CaringCharmer\CaringCharmer;
use Grano22\CaringCharmer\CaringCharmerDenormalizer;
use Grano22\CaringCharmer\Exception\ApplicationException;
use Grano22\CaringCharmer\Exception\CannotNormalizeData;
use Grano22\CaringCharmer\Exception\ReasonBasedException;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

require_once "vendor/autoload.php";

function non_block_read($fd): ?Generator {
    $read = [$fd];
    $write = null;
    $except = null;
    $result = stream_select($read, $write, $except, 0);
    if($result === false || $result === 0) return null;

    while ($nextStreamLine = stream_get_line($fd, 1))
        yield $nextStreamLine;
}

class SimpleCliOption {
    public const OPTIONAL = 0b010;
    public const REQUIRED = 0b001;
    public const NEGABLE = 0b001;

    private mixed $value;

    public function __construct(
        private string $name,
        private int $options = self::NEGABLE,
        /** @var string[] $aliases */
        private array $aliases = []
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getOptions(): int
    {
        return $this->options;
    }

    public function bindValue(mixed $newValue): self
    {
        $this->value = $newValue;

        return $this;
    }

    public function getValue(): mixed
    {
        if (!isset($this->value) && $this->value !== null) {
            throw new RuntimeException("Option of name {$this->name} is not bound");
        }

        return $this->value;
    }
}

/** @template T */
abstract class CollectionStructure {
    /** @var array<string, T> $elements */
    protected array $elements;

    /** @return T|null */
    public function getByName(string $name): ?object
    {
        return
            array_key_exists($name, $this->elements) ?
                $this->elements[$name] :
                null;
    }
}

class SimpleCliOptionsCollection {
    /** @var array<string, SimpleCliOption> $elements */
    private array $elements;

    public function __construct(SimpleCliOption ...$cliArguments) {
        $this->elements = array_combine(
            array_map(static fn (SimpleCliOption $cliArgument) => $cliArgument->getName(), $cliArguments),
            $cliArguments
        );
    }

    public function getByName(string $name): ?SimpleCliOption
    {
        return
            array_key_exists($name, $this->elements) ?
                $this->elements[$name] :
                null;
    }

    /** @return SimpleCliOption[] */
    public function toArray(): array
    {
        return array_values($this->elements);
    }
}

class SimpleCliArgument {
    public const OPTIONAL = 0b010;
    public const REQUIRED = 0b001;
    public const PIPED = 0b100;

    private mixed $value;

    public function __construct(
        private string $name,
        private int $options = 0
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOptions(): int
    {
        return $this->options;
    }

    public function bindValue(mixed $newValue): self
    {
        $this->value = $newValue;

        return $this;
    }

    public function getValue(): mixed
    {
        if (!isset($this->value) && $this->value !== null) {
            throw new RuntimeException("Argument of name {$this->name} is not bound");
        }

        return $this->value;
    }
}

class SimpleCliArgumentsCollection {
    /** @var array<string, SimpleCliArgument> $elements */
    private array $elements;

    public function __construct(SimpleCliArgument ...$cliArguments) {
        $this->elements = array_combine(
            array_map(static fn (SimpleCliArgument $cliArgument) => $cliArgument->getName(), $cliArguments),
            $cliArguments
        );
    }

    public function getPiped(): ?SimpleCliArgument
    {
        return array_filter(
            array_values($this->elements),
            static fn (SimpleCliArgument $argument) => $argument->getOptions() & SimpleCliArgument::PIPED
        )[0] ?? null;
    }

    public function getByName(string $name): ?SimpleCliArgument
    {
        return
            array_key_exists($name, $this->elements) ?
                $this->elements[$name] :
                null;
    }

    /** @return SimpleCliArgument[] */
    public function toArray(): array
    {
        return array_values($this->elements);
    }
}

/** @implements CollectionStructure<SimpleCliCommand> */
class SimpleCliCommandsStack extends CollectionStructure {
    public function __construct(SimpleCliCommand ...$cliCommands) {
        $this->elements = array_combine(
            array_map(static fn (SimpleCliCommand $cliArgument) => $cliArgument->getName(), $cliCommands),
            $cliCommands
        );
    }
}

class SimpleCliCommandInput {
    public function __construct(
        private SimpleCliArgumentsCollection $arguments,
        private SimpleCliOptionsCollection $options,
        private ?string $pipeData
    ) {}

    public function getArguments(): SimpleCliArgumentsCollection
    {
        return $this->arguments;
    }

    public function getOptions(): SimpleCliOptionsCollection
    {
        return $this->options;
    }

    public function getPipedData(bool $switched = false): string
    {
        if ($switched) {
            return $this->arguments->getPiped()->getValue();
        }

        return $this->pipeData;
    }
}

class SimpleCliCommand {
    public function __construct(
       private string $name,
       private Closure $executionLogic,
       private SimpleCliArgumentsCollection $definedArguments,
       private SimpleCliOptionsCollection $definedOptions
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinedArguments(): SimpleCliArgumentsCollection
    {
        return $this->definedArguments;
    }

    public function getDefinedOptions(): SimpleCliOptionsCollection
    {
        return $this->definedOptions;
    }

    public function execute(?string $stdinPipedData, SimpleCliAppExecutionContext $context): int
    {
        $returnCode = $this->executionLogic->__invoke(
            new SimpleCliCommandInput(
                $this->definedArguments,
                $this->definedOptions,
                $stdinPipedData
            ),
            $context
        );

        if (!is_int($returnCode)) {
            echo "Command {$this->getName()} missing return code";

            exit(1);
        }

        return $returnCode;
    }
}

class SimpleCliApp {
    private SimpleCliCommandsStack $definedCommands;
    private SimpleCliOperator $cliOperator;

    public static function createWithConfigAndCommands(SimpleCliAppConfig $config, array $commands): self
    {
        return new self($config, $commands);
    }

    private function __construct(
        private SimpleCliAppConfig $config,
        /** @var SimpleCliCommand[] $commands */
        array $commands = []
    ) {
        $this->definedCommands = new SimpleCliCommandsStack(...$commands);
        $this->cliOperator = new SimpleCliOperator();
    }

    public function getConfig(): SimpleCliAppConfig
    {
        return $this->config;
    }

    /** @return never-return */
    #[NoReturn]
    public function autoExecuteCommand()
    {
        $commandToUse = $this->cliOperator->prepareActualRequestedCommand($this->definedCommands);

        exit(
            $commandToUse->execute(
                $this->cliOperator->readPipeData(),
                new SimpleCliAppExecutionContext(
                    $this->config
                )
            )
        );
    }
}

class SimpleCliAppExecutionContext {
    public function __construct(
        private SimpleCliAppConfig $appConfig,
    ) {}

    public function getAppConfig(): SimpleCliAppConfig
    {
        return $this->appConfig;
    }
}

class SimpleCliInvokedCommand
{
    public static function build(): self
    {
        global $argv, $argc;

        $commandParts = [];
        for ($argi = 1; $argi < $argc; $argi++) {
            if (
                str_starts_with('--', $argv[$argi]) &&
                in_array($argv[$argi], $commandParts)
            ) {
                throw new RuntimeException("Duplicated option: {$argv[$argi]}");
            }

            $commandParts[] = ['content' => $argv[$argi], 'used' => false];
        }

        return new self(
            basename($argv[0]),
            $commandParts
        );
    }

    public function __construct(
        private string $scriptPath,
        private array  $commandParts
    )
    {
    }

    public function markArgumentAsUsed(int $argumentIndex): void
    {
        $this->commandParts[$argumentIndex]['used'] = true;
    }

    public function getValueAssociatedToOption(string $optionName, bool $short, bool $shouldHaveValue): string|null|bool
    {
        if ($short) {
            $foundShortOption = current(
                array_filter(
                    $this->commandParts,
                    static fn(array $part) => !$part['used'] && str_starts_with($part['content'], '-' . $optionName)
                )
            );

            if (!$foundShortOption) {
                return null;
            }

            $partIndex = array_search(['content' => '-' . $optionName, 'used' => false], $this->commandParts);
            $this->commandParts[$partIndex]['used'] = true;

            if (!$shouldHaveValue) {
                return true;
            }

            return str_replace('-' . $optionName, '', $foundShortOption);
        }

        $partIndex = array_search(['content' => '--' . $optionName, 'used' => false], $this->commandParts);

        if ($partIndex === false) {
            return null;
        }

        $this->commandParts[$partIndex]['used'] = true;

        if (!$shouldHaveValue) {
            return true;
        }

        if (!isset($this->commandParts[$partIndex + 1])) {
            throw new RuntimeException("Missing value for long option $optionName");
        }

        $optionValue = $this->commandParts[$partIndex + 1]['content'];

//        if (str_replace(' ', '', $optionValue) === $optionValue && !ctype_alnum($optionValue)) {
//            throw new RuntimeException("Invalid long option value");
//        }

        $this->commandParts[$partIndex + 1]['used'] = true;

        return $optionValue;
    }

    public function getAllParts(): array
    {
        return $this->commandParts;
    }

    public function getUnusedParts(): array
    {
        return array_filter($this->commandParts, static fn(array $part) => !$part['used']);
    }
}

class SimpleCliOperator {
    private SimpleCliInvokedCommand $invokedCommand;
    private string $pipedData;

    public function __construct() {
        $this->invokedCommand = SimpleCliInvokedCommand::build();
    }

    public function prepareActualRequestedCommand(SimpleCliCommandsStack $definedCommands): ?SimpleCliCommand
    {
        global $argv;

        $requestedCommand = $argv[1];
        $commandToUse = $definedCommands->getByName($requestedCommand);

        if (!$commandToUse) {
            echo "Unknown command $requestedCommand";
            exit(1);
        }

        $this->invokedCommand->markArgumentAsUsed(0);

        $this->readPipeData();
        $this->readOptions($commandToUse);
        $this->readArguments($commandToUse);

        $unusedParts = $this->invokedCommand->getUnusedParts();

        if ($unusedParts !== []) {
            throw new RuntimeException(
                "Unused parts: " .
                implode(',', array_map(static fn(array $unusedPart) => $unusedPart['content'], $unusedParts))
            );
        }

        return $commandToUse;
    }

    private function hasPipedData(): bool
    {
        return !!$this->pipedData;
    }

    public function readPipeData(): string
    {
        if (!isset($this->pipedData)) {
            $this->pipedData = '';

            foreach (non_block_read(STDIN) as $fragmentOfPipedData) {
                $this->pipedData .= $fragmentOfPipedData;
            }
        }

        return $this->pipedData;
    }

    private function readArguments(SimpleCliCommand $command): void
    {
        //$remainingParts = $this->invokedCommand->getUnusedParts();
        $allParts = $this->invokedCommand->getAllParts();
        $index = 1;

        foreach ($command->getDefinedArguments()->toArray() as $argument) {
            if (($argument->getOptions() & SimpleCliArgument::PIPED) && $this->hasPipedData()) {
                $argument->bindValue($this->readPipeData());

                continue;
            }

            if (($argument->getOptions() & SimpleCliArgument::REQUIRED) && !isset($allParts[$index])) {
                echo "Argument " . $index + 1 . " named {$argument->getName()} must be specified";
                exit(1);
            }

            if (($argument->getOptions() & SimpleCliArgument::OPTIONAL) && !isset($allParts[$index])) {
                $argument->bindValue(null);

                continue;
            }

            $argument->bindValue($allParts[$index]['content']);
            $this->invokedCommand->markArgumentAsUsed($index);

            $index++;
        }
    }

    private function readOptions(SimpleCliCommand $command): void
    {
//        $optionsProtoShorts = '';
//        $optionsProtoFull = [];
//
//        foreach ($command->getDefinedOptions()->toArray() as $option) {
//            $optionsProtoFull[] = $option->getName();
//
//            foreach ($option->getAliases() as $alias) {
//                if (strlen($alias) === 1) {
//                    $optionsProtoShorts .= $alias;
//
//                    continue;
//                }
//
//                $optionsProtoFull[] = $alias;
//            }
//        }
//
//        $receivedOptions = getopt($optionsProtoShorts, $optionsProtoFull);

//        foreach ($command->getDefinedOptions()->toArray() as $option) {
//            $occuredTimes = 0;
//
//            foreach ([$option->getName(), ...$option->getAliases()] as $alias) {
//                $occuredTimes += (int)isset($receivedOptions[$alias]);
//
//                if ($occuredTimes > 1) {
//                    echo "Option {$option->getName()} as alias $alias can be only specified one time";
//
//                    exit(1);
//                }
//
//                if ($occuredTimes) {
//                    $option->bindValue($receivedOptions[$alias]);
//
//                    continue 2;
//                }
//            }
//
//            if (!$occuredTimes && ($option->getOptions() & SimpleCliOption::REQUIRED)) {
//                echo "Option {$option->getName()} is missing";
//
//                exit(1);
//            }
//
//            $option->bindValue(null);
//        }

        foreach ($command->getDefinedOptions()->toArray() as $option) {
            $occuredTimes = 0;

            foreach ([$option->getName(), ...$option->getAliases()] as $alias) {
                $optionValue = $this->invokedCommand->getValueAssociatedToOption($alias, $alias !== $option->getName(), !($option->getOptions() & SimpleCliOption::NEGABLE));
                $occuredTimes += (int)isset($optionValue);

                if ($occuredTimes > 1) {
                    echo "Option {$option->getName()} as alias $alias can be only specified one time";

                    exit(1);
                }

                if ($occuredTimes) {
                    $option->bindValue($optionValue);

                    continue 2;
                }
            }

            if (!$occuredTimes && ($option->getOptions() & SimpleCliOption::REQUIRED)) {
                echo "Option {$option->getName()} is missing";

                exit(1);
            }

            $option->bindValue(null);
        }
    }
}

class SimpleCliAppConfig {
    public function __construct(
        private bool $debug
    ) {
    }

    public function isDebugEnabled(): bool
    {
        return $this->debug;
    }
}

class SimpleCliAppFactory {
    /** @var array<string, SimpleCliCommand> $definedCommands */
    private array $definedCommands = [];

    /** @var array<string, SimpleCliArgumentsCollection> $definedArgumentsForCommand */
    private array $definedArgumentsForCommand = [];

    /** @var array<string, SimpleCliOptionsCollection> $definedOptionsForCommand */
    private array $definedOptionsForCommand = [];

    private SimpleCliAppConfig $appConfig;

    public static function create(): self
    {
        return new self();
    }

    private function __construct()
    {
        $this->appConfig = new SimpleCliAppConfig(
            debug: false
        );
    }

    public function withCliAppConfiguration(
       ?bool $debug = null
    ): self {
        $this->appConfig = new SimpleCliAppConfig(
            debug: $debug ?? $this->appConfig->isDebugEnabled()
        );

        return $this;
    }

    public function withCommandArgument(
        string $commandName,
        string $name,
        int $options
    ): self {
        $previouslyDefinedArguments = $this->definedArgumentsForCommand[$commandName] ?? [];

        $previouslyDefinedArguments[] = new SimpleCliArgument(
            $name,
            $options
        );

        $this->definedArgumentsForCommand[$commandName] = new SimpleCliArgumentsCollection(...$previouslyDefinedArguments);

        return $this;
    }

    public function withCommandsArguments(
        /** @param string[] $commandNames */
        array $commandNames,
        string $name,
        int $options
    ): self {
        foreach ($commandNames as $commandName) {
            $this->withCommandArgument($commandName, $name, $options);
        }

        return $this;
    }

    public function withCommandOption(
        string $commandName,
        string $name,
        int $options
    ): self {
        $previouslyDefinedOptions = $this->definedOptionsForCommand[$commandName] ?? [];

        $previouslyDefinedOptions[] = new SimpleCliOption(
            $name,
            $options
        );

        $this->definedOptionsForCommand[$commandName] = new SimpleCliOptionsCollection(...$previouslyDefinedOptions);

        return $this;
    }

    public function withCommandsOptions(
        /** @param string[] $commandNames */
        array $commandNames,
        string $name,
        int $options
    ): self {
        foreach ($commandNames as $commandName) {
            $this->withCommandOption($commandName, $name, $options);
        }

        return $this;
    }

    /** @param callable(SimpleCliCommandInput, SimpleCliAppExecutionContext): int $executionLogic */
    public function withCommand(
        string $name,
        callable $executionLogic
    ): self {
        if (!array_key_exists($name, $this->definedArgumentsForCommand)) {
            $this->definedArgumentsForCommand[$name] = new SimpleCliArgumentsCollection();
        }

        if (!array_key_exists($name, $this->definedOptionsForCommand)) {
            $this->definedOptionsForCommand[$name] = new SimpleCliOptionsCollection();
        }

        $this->definedCommands[$name] = new SimpleCliCommand(
            $name,
            Closure::fromCallable($executionLogic),
            $this->definedArgumentsForCommand[$name],
            $this->definedOptionsForCommand[$name]
        );

        return $this;
    }

    public function build(): SimpleCliApp
    {
        return SimpleCliApp::createWithConfigAndCommands($this->appConfig, $this->definedCommands);
    }
}

function getInputDataByPipeOrFile(SimpleCliCommandInput $input): string {
    $inputData = $input->getPipedData(false);

    if (!$inputData) {
        $fileName = $input->getArguments()->getByName('inputData')->getValue();

        if (!file_exists($fileName)) {
            throw new Exception("File $fileName not found");
        }

        $inputData = file_get_contents($fileName);
    }

    return $inputData;
}

function getUnknownErrorMessage(Exception $exception): string
{
    $baseMessage = 'Something broken, please report error on github';

    if ($exception instanceof ApplicationException) {
        $baseMessage .= ", error code: {$exception->getUniqId()}";
    }

    return $baseMessage;
}

function getOtherErrorMessage(SimpleCliAppExecutionContext $context, Exception $exception): string
{
    return $context->getAppConfig()->isDebugEnabled() ? $exception->getMessage() : getUnknownErrorMessage($exception);
}

function prepareHandledErrorMessage(
        string $productionMessage,
        SimpleCliAppExecutionContext $context,
        Exception $exception
): string {
    $finalMessage = $productionMessage;

    if ($context->getAppConfig()->isDebugEnabled()) {
        $nextException = $exception;

        while ($nextException) {
            $finalMessage .= PHP_EOL . $nextException::class . " Details:" . PHP_EOL . $nextException->getMessage();

            $nextException = $nextException->getPrevious();
        }
    }

    return $finalMessage;
}

$debug = true;

$cliOperator = SimpleCliAppFactory::create()
    ->withCliAppConfiguration(
        debug: $debug
    )
    ->withCommandOption(
        commandName: 'anon',
        name: 'output',
        options: SimpleCliOption::OPTIONAL
    )
    ->withCommandsArguments(
        commandNames: ['anon', 'tokenize'],
        name: 'inputData',
        options: SimpleCliArgument::REQUIRED | SimpleCliArgument::PIPED
    )
    ->withCommand(
        name: 'anon',
        executionLogic: static function(
            SimpleCliCommandInput $input,
            SimpleCliAppExecutionContext $context
        ): int {
            $caringCharmer = new CaringCharmer();
            $caringCharmerDenormalizer = new CaringCharmerDenormalizer();
            $inputData = getInputDataByPipeOrFile($input);
            $outputOption = $input->getOptions()->getByName('output')?->getValue();

            try {
                $anonData = $caringCharmer->autoAnonymise($inputData, "json");
                $denormalizedAnonData = $caringCharmerDenormalizer->to('json', $anonData);
            } catch(ReasonBasedException $exception) {
                echo match ($exception::class) {
                    CannotNormalizeData::class => match ($exception->getReason()) {
                        CannotNormalizeData::REASON_INVALID_STRUCTURE =>
                        prepareHandledErrorMessage(
                            "Malformed {$exception->getFormat()} data in argument 1",
                            $context,
                            $exception
                        ),
                        CannotNormalizeData::REASON_MISSING_SUPPORT_FOR_FORMAT =>
                        prepareHandledErrorMessage(
                        "Application currently not supporting {$exception->getFormat()}",
                            $context,
                            $exception
                        ),
                        default => getOtherErrorMessage($context, $exception)
                    },
                    default => getOtherErrorMessage($context, $exception)
                } . PHP_EOL;

                exit(1);
            }

            if ($outputOption) {
                file_put_contents($outputOption, $denormalizedAnonData);

                return 0;
            }

            echo $denormalizedAnonData;

            return 0;
        }
    )
    ->withCommand(
         name: 'tokenize',
         executionLogic: static function(
              SimpleCliCommandInput $input,
              SimpleCliAppExecutionContext $context
         ): int {
            $caringCharmer = new CaringCharmer();
            $inputData = getInputDataByPipeOrFile($input);

            $tokenizedValues = $caringCharmer->tokenize($inputData, "json");
            echo "Tokenized Values:" . PHP_EOL . implode(PHP_EOL, $tokenizedValues) . PHP_EOL;

            return 0;
        }
    )
    ->build()
;
$cliOperator->autoExecuteCommand();