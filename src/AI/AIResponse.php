<?php

namespace Arun\ReadmeGenerator\AI;

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
You are a Drupal module documentation expert. Generate a README.md file using this format:

CONTENTS OF THIS FILE

- Introduction
- Requirements
- Installation
- Recommended modules
- Configuration
- Upgrading
- Maintainers

# [Module Name]

## Introduction
(Write a detailed intro...)

...

Now, analyze the following Drupal module file and generate a README section for it:

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

            return $readmeContent;
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}
