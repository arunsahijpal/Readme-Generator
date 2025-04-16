<?php

namespace Innoraft\ReadmeGenerator\Console;

use Innoraft\ReadmeGenerator\Scanner\CodebaseScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Innoraft\ReadmeGenerator\AI\AIResponse;
use Dotenv\Dotenv;

/**
 * Class GenerateReadmeCommand
 *
 * Symfony Console Command to generate a README.md file for a Drupal module
 * using AI-generated content based on the module's code and metadata.
 *
 * @package Innoraft\ReadmeGenerator\Console
 */
class GenerateReadmeCommand extends Command
{
    /**
     * Command constructor.
     *
     * Sets the command name.
     */
    public function __construct()
    {
        parent::__construct('generate-readme');
    }

    /**
     * Configures the command with description and required arguments.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Generates a README.md file for a Drupal module')
            ->addArgument('module_path', InputArgument::REQUIRED, 'Path to the module');
    }

    /**
     * Executes the command logic.
     *
     * - Loads environment variables
     * - Validates inputs
     * - Scans the module path for metadata
     * - Generates README using AI
     * - Saves the README to the module path
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   Input interface instance.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   Output interface instance.
     *
     * @return int
     *   Returns the command exit status.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $searchDirs = [
            $cwd,
            realpath($cwd . '/../../../..'),
            dirname(__DIR__, 4),
        ];
    
        $envPath = null;
        foreach ($searchDirs as $dir) {
            if ($dir && file_exists($dir . '/.env')) {
                $envPath = $dir;
                break;
            }
        }
    
        if (!$envPath) {
            $output->writeln("<error>❌ .env file not found.</error>");
            $output->writeln("Please create a .env file with the following keys:");
            $output->writeln("API_KEY=");
            $output->writeln("BASE_URI=");
            $output->writeln("CHAT_ENDPOINT=");
            $output->writeln("MODEL=");
            return Command::FAILURE;
        }
    
        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();

        $config = [
            'api_key' => $_ENV['API_KEY'] ?? '',
            'base_uri' => $_ENV['BASE_URI'] ?? '',
            'chat_endpoint' => $_ENV['CHAT_ENDPOINT'] ?? '',
            'model' => $_ENV['MODEL'] ?? '',
        ];

        foreach ($config as $key => $value) {
            if (empty($value)) {
                $output->writeln("<error>❌ Missing env variable:</error> $key");
                return Command::FAILURE;
            }
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

        $ai = new AIResponse($config);
        $summary = $ai->summarizeArray($structuredData);

        $readmePath = $modulePath . '/README.md';
        file_put_contents($readmePath, $summary);

        $output->writeln("<info>✅ AI-generated README.md created at:</info> $readmePath");

        return Command::SUCCESS;
    }
}
