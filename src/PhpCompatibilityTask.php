<?php

declare(strict_types=1);

namespace wunderio\PhpCompatibilityTask;

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
          'testVersion' => '7.3',
        ]
      );
      $resolver->addAllowedTypes('triggered_by', ['array']);
      $resolver->addAllowedTypes('testVersion', 'string');
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
        $arguments = $this->addArgumentsFromConfig($arguments, $config);
        $arguments->add('--standard=vendor/wunderio/grumphp-php-compatibility/php-compatibility.xml');
        $arguments->addFiles($files);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $this->formatter->format($process);
            return TaskResult::createFailed($this, $context, $output);
        }

        return TaskResult::createPassed($this, $context);
    }

    protected function addArgumentsFromConfig(
        ProcessArgumentsCollection $arguments,
        array $config
    ): ProcessArgumentsCollection {
        $arguments->addOptionalCommaSeparatedArgument('--extensions=%s', (array) $config['triggered_by']);
        $arguments->addSeparatedArgumentArray('--runtime-set', ['testVersion', (string) $config['testVersion']]);
        return $arguments;
    }
}
