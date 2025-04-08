<?php

namespace Innoraft\ReadmeGenerator\Scanner;

use Symfony\Component\Yaml\Yaml;

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
        $submodules = $this->listSubmodules(); // ✅ Submodule info added

        return array_merge(
            $moduleInfo,
            $parsedFunctionsAndClasses,
            $parsedUsefulData,
            ['submodules' => $submodules] // ✅ Merge submodule data
        );
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
        $files = [];

        // Top-level important files
        $topLevelPatterns = [
            '*.info.yml', '*.module', '*.install',
            '*.routing.yml', '*.permissions.yml',
            '*.links.menu.yml', '*.links.task.yml',
            '*.schema.yml',
        ];

        foreach ($topLevelPatterns as $pattern) {
            foreach (glob($this->modulePath . '/' . $pattern) as $file) {
                $files[] = $file;
            }
        }

        // Scan key src subdirectories
        $subDirs = [
            'src/Controller/*.php',
            'src/Form/*.php',
            'src/Plugin/*.php',
            'src/Entity/*.php',
            'src/Utility/*.php',
            'config/install/*.yml',
        ];

        foreach ($subDirs as $pattern) {
            foreach (glob($this->modulePath . '/' . $pattern) as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function listSubmodules(): array
    {
        $submodules = [];

        foreach (glob($this->modulePath . '/modules/*/*.info.yml') as $infoFile) {
            $machineName = basename($infoFile, '.info.yml');
            $infoData = Yaml::parseFile($infoFile);
             // Parse the .info.yml

            $submodules[] = [
                'name' => $machineName,
                'description' => $infoData['description'] ?? 'No description available.',
            ];
        }

        return $submodules;
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
