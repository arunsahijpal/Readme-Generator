<?php

namespace Innoraft\ReadmeGenerator\AI;

use GuzzleHttp\Client;

class AIResponse {
    protected $client;
    protected $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.groq.com/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function summarizeFile(string $filePath, string $model = 'llama3-8b-8192', int $maxTokens = 500): string
    {
        if (!file_exists($filePath)) {
            return 'Error: File not found.';
        }

        $fileContent = file_get_contents($filePath);

        $template = <<<EOT
You are a Drupal module documentation expert. Your task is to generate only the contents of a README.md file for a Drupal module.

IMPORTANT:
- Do NOT include any introduction or extra explanation.
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
Explain in detail how to configure the module after enabling it.

## Maintainers
Add a placeholder for the maintainer.

Now, analyze the following Drupal module file and generate the README.md content accordingly:

{$fileContent}
EOT;

        try {
            $response = $this->client->post('openai/v1/chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $template]],
                    'max_tokens' => $maxTokens,
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            $readmeContent = $body['choices'][0]['message']['content'] ?? 'No README generated.';

            // Remove anything before "CONTENTS OF THIS FILE"
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
