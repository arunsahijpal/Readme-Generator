<?php

namespace Innoraft\ReadmeGenerator\Scanner;

use Symfony\Component\Yaml\Yaml;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class CodebaseScanner
{
    private string $modulePath;

    public function __construct(string $modulePath)
    {
        $this->modulePath = rtrim($modulePath, '/');
    }

    public function scan(): array
    {
        $moduleInfo = $this->extractModuleInfo();
        $files = $this->listRelevantFiles();
        $parsedFunctionsAndClasses = $this->parseCodeFiles($files);
        $parsedUsefulData = $this->extractUsefulData($files);

        return array_merge($moduleInfo, $parsedFunctionsAndClasses, $parsedUsefulData);
    }

    private function extractModuleInfo(): array
    {
        $infoFile = glob($this->modulePath . '/*.info.yml');
        $info = ['name' => basename($this->modulePath), 'description' => 'No description found.', 'dependencies' => []];

        if (!empty($infoFile) && file_exists($infoFile[0])) {
            $moduleInfo = Yaml::parseFile($infoFile[0]);
            $info['name'] = $moduleInfo['name'] ?? basename($this->modulePath);
            $info['description'] = $moduleInfo['description'] ?? 'No description available.';
            $info['dependencies'] = $moduleInfo['dependencies'] ?? [];
        }
        
        return $info;
    }

    private function listRelevantFiles(): array
    {
        $extensions = ['php', 'module', 'yml']; // Important file types
        $importantPaths = [
            'src/Controller/', 'src/Form/', 'src/Entity/', 'src/Plugin/', 'src/Utility/',
            'config/install/', '*.module', '*.routing.yml', '*.schema.yml'
        ];
    
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->modulePath));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                $relativePath = str_replace($this->modulePath . '/', '', $filePath);

                // Check if file is in important paths
                foreach ($importantPaths as $importantPath) {
                    if (strpos($relativePath, $importantPath) === 0) {
                        $files[] = $filePath;
                        continue 2;
                    }
                }

                // Check if file has an important extension
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                if (in_array($extension, $extensions)) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }

    private function parseCodeFiles(array $files): array
    {
        $functions = [];
        $classes = [];

        foreach ($files as $file) {
            $relativePath = str_replace($this->modulePath . '/', '', $file);
            $code = file_get_contents($file);

            // Extract functions
            if (preg_match_all('/function\s+(\w+)\s*\(/', $code, $matches)) {
                foreach ($matches[1] as $function) {
                    $functions[] = "$relativePath::$function";
                }
            }

            // Extract classes
            if (preg_match_all('/class\s+(\w+)/', $code, $matches)) {
                foreach ($matches[1] as $class) {
                    $classes[] = "$relativePath::$class";
                }
            }
        }

        return ['classes' => $classes, 'functions' => $functions];
    }

    private function extractUsefulData(array $files): array
    {
        $hooks = [];
        $controllers = [];
        $forms = [];

        foreach ($files as $file) {
            $relativePath = str_replace($this->modulePath . '/', '', $file);
            $code = file_get_contents($file);

            // Find Drupal Hooks
            if (preg_match_all('/function\s+(hook_[a-zA-Z_]+)\s*\(/', $code, $matches)) {
                foreach ($matches[1] as $hook) {
                    $hooks[] = "$relativePath::$hook";
                }
            }

            // Find Controller Classes
            if (strpos($relativePath, 'src/Controller/') !== false) {
                if (preg_match('/class\s+(\w+)/', $code, $match)) {
                    $controllers[] = "$relativePath::{$match[1]}";
                }
            }

            // Find Form Classes
            if (strpos($relativePath, 'src/Form/') !== false) {
                if (preg_match('/class\s+(\w+)/', $code, $match)) {
                    $forms[] = "$relativePath::{$match[1]}";
                }
            }
        }

        return ['hooks' => $hooks, 'controllers' => $controllers, 'forms' => $forms];
    }
}
