<?php

namespace Innoraft\ReadmeGenerator\Scanner;

use Symfony\Component\Yaml\Yaml;

/**
 * Class CodebaseScanner
 *
 * Scans a Drupal module directory and extracts relevant metadata,
 * including module info, file listings, defined functions, classes,
 * submodules, and useful Drupal-specific components.
 */
class CodebaseScanner
{
    /**
     * The absolute path to the Drupal module.
     *
     * @var string
     */
    private string $modulePath;

    /**
     * CodebaseScanner constructor.
     *
     * @param string $modulePath
     *   Path to the Drupal module directory.
     */
    public function __construct(string $modulePath)
    {
        $this->modulePath = rtrim($modulePath, '/');
    }

    /**
     * Scans the module and returns structured information.
     *
     * @return array
     *   An array containing module metadata, functions, classes, hooks, etc.
     */
    public function scan(): array
    {
        $moduleInfo = $this->extractModuleInfo();
        $files = $this->listRelevantFiles();
        $parsedFunctionsAndClasses = $this->parseCodeFiles($files);
        $parsedUsefulData = $this->extractUsefulData($files);
        $submodules = $this->listSubmodules();

        return array_merge(
            $moduleInfo,
            $parsedFunctionsAndClasses,
            $parsedUsefulData,
            ['submodules' => $submodules]
        );
    }

    /**
     * Extracts module name, description, and dependencies from .info.yml.
     *
     * @return array
     *   Basic module metadata.
     */
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

    /**
     * Lists relevant Drupal module files (YAML, PHP, install configs).
     *
     * @return array
     *   An array of file paths.
     */
    private function listRelevantFiles(): array
    {
        $files = [];

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

    /**
     * Lists submodules within the main module (if present).
     *
     * @return array
     *   An array of submodule names and descriptions.
     */
    private function listSubmodules(): array
    {
        $submodules = [];

        foreach (glob($this->modulePath . '/modules/*/*.info.yml') as $infoFile) {
            $machineName = basename($infoFile, '.info.yml');
            $infoData = Yaml::parseFile($infoFile);

            $submodules[] = [
                'name' => $machineName,
                'description' => $infoData['description'] ?? 'No description available.',
            ];
        }

        return $submodules;
    }

    /**
     * Parses PHP files to extract class and function names.
     *
     * @param array $files
     *   List of files to scan.
     *
     * @return array
     *   Array containing function and class definitions.
     */
    private function parseCodeFiles(array $files): array
    {
        $functions = [];
        $classes = [];

        foreach ($files as $file) {
            $relativePath = str_replace($this->modulePath . '/', '', $file);
            $code = file_get_contents($file);

            if (preg_match_all('/function\s+(\w+)\s*\(/', $code, $matches)) {
                foreach ($matches[1] as $function) {
                    $functions[] = "$relativePath::$function";
                }
            }

            if (preg_match_all('/class\s+(\w+)/', $code, $matches)) {
                foreach ($matches[1] as $class) {
                    $classes[] = "$relativePath::$class";
                }
            }
        }

        return ['classes' => $classes, 'functions' => $functions];
    }

    /**
     * Extracts Drupal hooks, controller classes, and form classes.
     *
     * @param array $files
     *   List of files to scan.
     *
     * @return array
     *   Array containing hooks, controllers, and forms.
     */
    private function extractUsefulData(array $files): array
    {
        $hooks = [];
        $controllers = [];
        $forms = [];

        foreach ($files as $file) {
            $relativePath = str_replace($this->modulePath . '/', '', $file);
            $code = file_get_contents($file);

            if (preg_match_all('/function\s+(hook_[a-zA-Z_]+)\s*\(/', $code, $matches)) {
                foreach ($matches[1] as $hook) {
                    $hooks[] = "$relativePath::$hook";
                }
            }

            if (strpos($relativePath, 'src/Controller/') !== false) {
                if (preg_match('/class\s+(\w+)/', $code, $match)) {
                    $controllers[] = "$relativePath::{$match[1]}";
                }
            }

            if (strpos($relativePath, 'src/Form/') !== false) {
                if (preg_match('/class\s+(\w+)/', $code, $match)) {
                    $forms[] = "$relativePath::{$match[1]}";
                }
            }
        }

        return ['hooks' => $hooks, 'controllers' => $controllers, 'forms' => $forms];
    }
}
