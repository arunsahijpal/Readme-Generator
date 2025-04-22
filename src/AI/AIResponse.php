<?php

namespace Innoraft\ReadmeGenerator\AI;

use GuzzleHttp\Client;

/**
 * Class AIResponse
 *
 * Handles communication with the AI service to generate README.md content
 * for Drupal modules based on provided metadata.
 *
 * @package Innoraft\ReadmeGenerator\AI
 */
class AIResponse
{
    /**
     * The Guzzle HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected Client $client;

    /**
     * Configuration array containing API credentials and settings.
     *
     * @var array
     */
    protected array $config;

    /**
     * AIResponse constructor.
     *
     * Initializes the HTTP client with base URI and headers.
     *
     * @param array $config
     *   Configuration array including 'base_uri', 'api_key', 'chat_endpoint', and 'model'.
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $this->config['base_uri'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generates a summarized README.md content from an array of module data.
     *
     * @param array $moduleData
     *   Associative array containing parsed module information.
     *
     * @return string
     *   Generated README content or an error message.
     */
    public function summarizeArray(array $moduleData): string
    {
        $jsonContent = json_encode($moduleData, JSON_PRETTY_PRINT);

        $template = <<<EOT
        You are a Drupal module documentation expert. Your task is to generate only the contents of a README.md file for a Drupal module.
        
        IMPORTANT:
        - Do NOT start with lines like "Here is the README for..."
        - The output should START DIRECTLY with the line: "CONTENTS OF THIS FILE"
        - Follow the exact format below.
        
        CONTENTS OF THIS FILE
        
        - Introduction
        - Requirements
        - Installation
        - Recommended modules
        - Configuration
        - Maintainers
        
        # [Module Name]
        
        ## Introduction
        Write a detailed explanation of what the module does in 4 to 5 lines.
        
        ## Requirements
        Only list the names of required modules or Drupal core. Do not explain them, also start name of the module from capital letter.
        
        ## Installation
        Only write the composer command:
        composer require drupal/module_machine_name
        
        ## Recommended modules
        List names of recommended modules. No descriptions.
        
        ## Configuration
        Explain in detail and in points how to configure the module after enabling it.
        
        ## Maintainers
        Add a placeholder for the maintainer.
        
        Now, analyze the following Drupal module file and generate the README.md content accordingly:
    
        {$jsonContent}
        EOT;

        $maxTokens = 500;

        try {
            $response = $this->client->post($this->config['chat_endpoint'], [
                'json' => [
                    'model' => $this->config['model'],
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $template,
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            $readmeContent = $body['choices'][0]['message']['content'] ?? 'No README generated.';

            if (preg_match('/CONTENTS OF THIS FILE/i', $readmeContent, $matches, PREG_OFFSET_CAPTURE)) {
                $startPos = $matches[0][1];
                $readmeContent = substr($readmeContent, $startPos);
            } else {
                $readmeContent = 'Error: "CONTENTS OF THIS FILE" not found in AI response.';
            }

            return trim($readmeContent);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}
