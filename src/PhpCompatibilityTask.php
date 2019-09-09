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
          'ignore_patterns' => ['*/vendor/*','*/node_modules/*'],
          'codebase_path' => '.',
        ]
      );
      $resolver->addAllowedTypes('triggered_by', ['array']);
      $resolver->addAllowedTypes('testVersion', 'string');
      $resolver->addAllowedTypes('ignore_patterns', ['array']);
      $resolver->addAllowedTypes('codebase_path', 'string');
      return $resolver;
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
      $config = $this->getConfiguration();
      $files = $context
        ->getFiles()
        ->notPaths($config['ignore_patterns'])
        ->extensions($config['triggered_by']);

      if (0 === count($files)) {
        return TaskResult::createSkipped($this, $context);
      }

        $arguments = $this->processBuilder->createArgumentsForCommand('phpcs');
        $arguments = $this->addArgumentsFromConfig($arguments, $config);
        $arguments->add('--standard=vendor/wunderio/grumphp-php-compatibility/php-compatibility.xml');

      // @todo: Until GrumPHP does not have solution for 'run' command with lots of files we'll use our custom codebase_path parameter with custom check for 'run' command.
      if ($this->isRunningFullCodeBase()) {
        $arguments->add($config["codebase_path"]);
      }
      else {
        $arguments->addFiles($files);
      }

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
        $arguments->addOptionalCommaSeparatedArgument('--ignore=%s', $config['ignore_patterns']);
        return $arguments;
    }

  /**
   * Check if running against full codebase.
   *
   * @return bool
   */
    private function isRunningFullCodeBase() {
      global $argv;
      return in_array('run', $argv);
    }
}
