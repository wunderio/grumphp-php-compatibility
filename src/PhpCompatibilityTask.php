<?php

declare(strict_types=1);

namespace hkirsman\PhpCompatibilityTask;

use GrumPHP\Collection\ProcessArgumentsCollection;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Symfony\Component\OptionsResolver\OptionsResolver;
use GrumPHP\Task\AbstractExternalTask;

class PhpCompatibilityTask extends AbstractExternalTask
{
    public function getName(): string
    {
        return 'php_compatibility';
    }

    public function getConfigurableOptions(): OptionsResolver
    {
      $resolver = new OptionsResolver();
      $resolver->setDefaults(
        [
          'triggered_by' => ['php', 'inc', 'module', 'install'],
        ]
      );
      $resolver->addAllowedTypes('triggered_by', ['array']);
      return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
      $config = $this->getConfiguration();
      $files = $context->getFiles()->extensions($config['triggered_by']);
      if (0 === count($files)) {
        return TaskResult::createSkipped($this, $context);
      }

        $arguments = $this->processBuilder->createArgumentsForCommand('phpcs');
        // @todo: Start using configurations from yml.
//        $arguments = $this->addArgumentsFromConfig($arguments, $config);
//        $arguments->add('--report-json');
        // @todo: Start using configurations from yml.
        $arguments->add('--standard=vendor/hkirsman/grumphp-php-compatibility/php-compatibility.xml');
        $arguments->addFiles($files);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $this->formatter->format($process);
//            try {
//                $arguments = $this->processBuilder->createArgumentsForCommand('phpcbf');
//                $arguments = $this->addArgumentsFromConfig($arguments, $config);
//                $output .= $this->formatter->formatErrorMessage($arguments, $this->processBuilder);
//            } catch (RuntimeException $exception) { // phpcbf could not get found.
//                $output .= PHP_EOL.'Info: phpcbf could not get found. Please consider to install it for suggestions.';
//            }

            return TaskResult::createFailed($this, $context, $output);
        }

        return TaskResult::createPassed($this, $context);
    }

    protected function addArgumentsFromConfig(
        ProcessArgumentsCollection $arguments,
        array $config
    ): ProcessArgumentsCollection {
        $arguments->addOptionalCommaSeparatedArgument('--standard=%s', (array) $config['standard']);
        $arguments->addOptionalArgument('--tab-width=%s', $config['tab_width']);
        $arguments->addOptionalArgument('--encoding=%s', $config['encoding']);
        $arguments->addOptionalArgument('--report=%s', $config['report']);
        $arguments->addOptionalIntegerArgument('--report-width=%s', $config['report_width']);
        $arguments->addOptionalIntegerArgument('--severity=%s', $config['severity']);
        $arguments->addOptionalIntegerArgument('--error-severity=%s', $config['error_severity']);
        $arguments->addOptionalIntegerArgument('--warning-severity=%s', $config['warning_severity']);
        $arguments->addOptionalCommaSeparatedArgument('--sniffs=%s', $config['sniffs']);
        $arguments->addOptionalCommaSeparatedArgument('--ignore=%s', $config['ignore_patterns']);
        $arguments->addOptionalCommaSeparatedArgument('--exclude=%s', $config['exclude']);

        return $arguments;
    }
}
