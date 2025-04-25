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
            $output->writeln("<error>:x: .env file not found.</error>");
            $output->writeln("Please create a .env file with the following keys:");
            $output->writeln("AI_PROVIDER=\nAPI_KEY=\n");
            return Command::FAILURE;
        }
        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();
        $provider = strtolower(trim($_ENV['AI_PROVIDER'] ?? ''));
        $apiKey = trim($_ENV['API_KEY'] ?? '');
        $ai_model = isset($_ENV['AI_MODEL']) && strlen(trim($_ENV['AI_MODEL'])) > 0
            ? trim($_ENV['AI_MODEL'])
            : 'gpt-3.5-turbo';
        if (empty($provider) || empty($apiKey)) {
            $output->writeln("<error>:x: Missing AI_PROVIDER or API_KEY in .env file.</error>");
            return Command::FAILURE;
        }
        $providerConfigs = [
            'groq' => [
                'base_uri' => 'https://api.groq.com/',
                'chat_endpoint' => 'openai/v1/chat/completions',
                'model' => 'llama3-8b-8192',
            ],
            'openai' => [
                'base_uri' => 'https://api.openai.com/v1/',
                'chat_endpoint' => 'chat/completions',
                'model' => 'gpt-4',
            ],
        ];
        if (!isset($providerConfigs[$provider])) {
            $output->writeln("<error>:x: Unsupported AI provider: $provider</error>");
            $output->writeln("Supported providers: " . implode(', ', array_keys($providerConfigs)));
            return Command::FAILURE;
        }
        $config = [
            'api_key' => $apiKey,
            'base_uri' => $providerConfigs[$provider]['base_uri'],
            'chat_endpoint' => $providerConfigs[$provider]['chat_endpoint'],
            'model' => $ai_model
        ];
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
        $output->writeln("<info>:white_tick: AI-generated README.md created at:</info> $readmePath");
        return Command::SUCCESS;
    }
}
