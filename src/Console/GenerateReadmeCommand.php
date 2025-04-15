<?php

namespace Innoraft\ReadmeGenerator\Console;

use Innoraft\ReadmeGenerator\Scanner\CodebaseScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Innoraft\ReadmeGenerator\AI\AIResponse;

/**
 * Class GenerateReadmeCommand
 *
 * A Symfony Console Command that generates a README.md file
 * for a given Drupal module using AI-generated summaries.
 */
class GenerateReadmeCommand extends Command
{
    /**
     * GenerateReadmeCommand constructor.
     */
    public function __construct()
    {
        parent::__construct('generate-readme');
    }

    /**
     * Configures the command name, description, and input arguments.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Generates a README.md file for a Drupal module')
            ->addArgument('module_path', InputArgument::REQUIRED, 'Path to the module');
    }

    /**
     * Executes the console command to generate the README file.
     *
     * @param InputInterface $input
     *   The input interface.
     * @param OutputInterface $output
     *   The output interface.
     *
     * @return int
     *   Command exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = require __DIR__ . '/../../config/ai.php';

        $requiredKeys = ['api_key', 'base_uri', 'chat_endpoint', 'model'];
        $missing = [];

        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $output->writeln("<error>❌ Missing values in config/ai.php:</error>");
            foreach ($missing as $key) {
                $output->writeln(" - $key");
            }
            $output->writeln("\n<comment>Please provide the required values in config/ai.php before running the command.</comment>");
            return Command::FAILURE;
        }

        $modulePath = $input->getArgument('module_path');

        if (!is_dir($modulePath)) {
            $output->writeln("<error>Invalid module path.</error>");
            return Command::FAILURE;
        }

        $scanner = new CodebaseScanner($modulePath);
        $moduleData = $scanner->scan();

        $structuredData = [
            'name' => $moduleData['name'] ?? 'Unknown Module',
            'description' => $moduleData['description'] ?? 'No description available.',
            'dependencies' => $moduleData['dependencies'] ?? [],
            'files' => $moduleData['files'] ?? [],
            'classes' => $moduleData['classes'] ?? [],
            'functions' => $moduleData['functions'] ?? [],
        ];

        $ai = new AIResponse();
        $summary = $ai->summarizeArray($structuredData);

        $readmePath = $modulePath . '/README.md';
        file_put_contents($readmePath, $summary);

        $output->writeln("<info>✅ AI-generated README.md created at:</info> $readmePath");

        return Command::SUCCESS;
    }
}
