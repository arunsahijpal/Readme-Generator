<?php

namespace Arun\ReadmeGenerator\Console;

use Arun\ReadmeGenerator\Scanner\CodebaseScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Arun\ReadmeGenerator\AI\AIResponse;

class GenerateReadmeCommand extends Command
{
    public function __construct()
    {
        parent::__construct('generate-readme');
    }

    protected function configure()
    {
        $this
            ->setDescription('Generates a README.json file for a module')
            ->addArgument('module_path', InputArgument::REQUIRED, 'Path to the module');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modulePath = $input->getArgument('module_path');

        if (!is_dir($modulePath)) {
            $output->writeln("<error>Invalid module path.</error>");
            return Command::FAILURE;
        }

        $scanner = new CodebaseScanner($modulePath);
        $moduleData = $scanner->scan();

        // Sanitize and ensure structure
        $structuredData = [
            'name' => $moduleData['name'] ?? 'Unknown Module',
            'description' => $moduleData['description'] ?? 'No description available.',
            'dependencies' => $moduleData['dependencies'] ?? [],
            'files' => $moduleData['files'] ?? [],
            'classes' => $moduleData['classes'] ?? [],
            'functions' => $moduleData['functions'] ?? [],
        ];

        // Save as JSON
        $jsonPath = $modulePath . '/_scan_summary.json';
        file_put_contents($jsonPath, json_encode($moduleData, JSON_PRETTY_PRINT));

        $output->writeln("<info>README_DATA.json generated at:</info> $jsonPath");
        $aiKey = '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'; 
        $ai = new AIResponse($aiKey);

        // Pass the JSON file path for summarization
        $summary = $ai->summarizeFile($jsonPath);

        // Save final README
        $readmePath = $modulePath . '/README_NEW.md';
        file_put_contents($readmePath, $summary);
        unlink($jsonPath);

        $output->writeln("<info>✅ AI-generated README.md created at:</info> $readmePath");

        return Command::SUCCESS;
    }
}
