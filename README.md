# OpenAI SDK PHP

[![Latest Stable Version](https://poser.pugx.org/shanginn/openai-sdk-php/v)](https://packagist.org/packages/shanginn/openai-sdk-php) <!-- Replace with actual badge URLs -->
[![Total Downloads](https://poser.pugx.org/shanginn/openai-sdk-php/downloads)](https://packagist.org/packages/shanginn/openai-sdk-php)
[![License](https://poser.pugx.org/shanginn/openai-sdk-php/license)](https://packagist.org/packages/shanginn/openai-sdk-php) <!-- Replace with your license -->
[![Build Status](https://github.com/shanginn/openai-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/shanginn/openai-sdk-php/actions/workflows/ci.yml) <!-- Replace with actual repo path -->
[![Coverage Status](https://coveralls.io/repos/github/shanginn/openai-sdk-php/badge.svg?branch=main)](https://coveralls.io/github/shanginn/openai-sdk-php?branch=main) <!-- Replace with actual repo path -->

A modern, strongly-typed PHP SDK for interacting with the OpenAI API, focusing initially on the Chat Completions endpoint. Built with asynchronous capabilities in mind using `amphp/http-client` and robust serialization/deserialization via `crell/serde`.

## Features

*   Access to the OpenAI Chat Completions API (`/v1/chat/completions`).
*   **Strongly-Typed Objects:** Uses PHP classes for Requests, Responses, Messages, Tools, and Schemas, providing better IDE autocompletion and type safety.
*   **Tool Calling:** Define and use tools (functions) that the OpenAI models can invoke. Includes automatic deserialization of tool arguments into PHP objects based on class definitions with attributes like `Spiral\JsonSchemaGenerator\Attribute\Field`.
*   **JSON Schema Mode:** Force the model to output JSON conforming to a specific structure defined by your PHP classes implementing `JsonSchemaInterface`, utilizing attributes like `Spiral\JsonSchemaGenerator\Attribute\Field` for detailed schema generation.
*   **Image Input:** Supports sending images along with text prompts using `UserMessage` and `ImageContentPart` (compatible with models like GPT-4o).
*   **Asynchronous Client:** Leverages `amphp/http-client` for non-blocking I/O (though the current `OpenaiClient` implementation buffers the full response).
*   **Serialization:** Uses `crell/serde` and `symfony/serializer` for mapping between PHP objects and OpenAI's JSON format.
*   **Simplified Wrapper:** Includes an `OpenaiSimple` class for common use cases like simple text generation, JSON object generation, and tool calling with less boilerplate.
*   **Custom Exceptions:** Provides specific exceptions for different API error conditions (e.g., `OpenaiErrorResponseException`, `OpenaiNoChoicesException`, `OpenaiWrongSchemaException`).

## Installation

Install the package via Composer:

```bash
composer require shanginn/openai-sdk-php spiral/json-schema-generator
```

## Usage

### Simple Text Generation (`OpenaiSimple`)

This is the easiest way to get a text response for a simple prompt.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiException;

// Ensure you have your OpenAI API Key
$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

// 1. Initialize the client and services
$client = new OpenaiClient($apiKey);
$openaiCore = new Openai($client, 'gpt-5-mini'); // Choose your model
$openaiSimple = new OpenaiSimple($openaiCore);

// 2. Define prompts
$systemPrompt = "You are a helpful assistant that translates English to French.";
$userPrompt = "Hello, world!";
$history = []; // Optional: Add previous MessageInterface objects

// 3. Generate the response
try {
    $result = $openaiSimple->generate(
        system: $systemPrompt,
        userMessage: $userPrompt,
        history: $history,
        temperature: 0.7
    );

    echo "Assistant: {$result}\n";
    // Example Output: Assistant: Bonjour, le monde !

} catch (OpenaiErrorResponseException $e) {
    echo "API Error: {$e->response->message}\n";
} catch (OpenaiException $e) {
    echo "SDK Exception: {$e->getMessage()}\n";
} catch (\Throwable $e) {
     echo "General Error: {$e->getMessage()}\n";
}
```

### Basic Completion (`Openai` Core Class)

If you need more control over the request parameters or want to handle the response object directly.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\ErrorResponse;
use Shanginn\Openai\Exceptions\OpenaiException;

// Ensure you have your OpenAI API Key
$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

// 1. Initialize the HTTP Client
$client = new OpenaiClient($apiKey); // Optional: Pass custom API URL

// 2. Initialize the Openai service
$openai = new Openai(
    client: $client,
    model: 'gpt-5-mini' // Choose your desired model
);

// 3. Prepare your messages
$messages = [
    new UserMessage(content: 'What is the chemical symbol for water?')
];

// 4. Make the completion request
try {
    $response = $openai->completion(
        messages: $messages,
        temperature: 0.5, // Optional parameters
        maxTokens: 50
    );

    // 5. Handle the response
    if ($response instanceof ErrorResponse) {
        // Handle API error (e.g., authentication, rate limits)
        echo "API Error: {$response->message} (Type: {$response->type}, Code: {$response->code})\n";
    } elseif (count($response->choices) > 0) {
        // Get the first choice's message content
        $content = $response->choices[0]->message->content;
        echo "Assistant: {$content}\n";
        // Example Output: Assistant: The chemical symbol for water is H₂O.
    } else {
        echo "No choices returned.\n";
    }

} catch (OpenaiException $e) {
    // Handle SDK-specific exceptions or transport errors
    echo "SDK Exception: {$e->getMessage()}\n";
} catch (\Throwable $e) {
    // Handle other potential errors (e.g., network issues from client)
     echo "General Error: {$e->getMessage()}\n";
}

```

## Advanced Usage

### Tool Calling

Define a tool class implementing `ToolInterface` (often by extending `AbstractTool` and using `#[OpenaiToolSchema]`) and detail its parameters using `#[Field]` attributes from `spiral/json-schema-generator`. The SDK will attempt to deserialize the model's arguments into an instance of your tool class.

**1. Define the Tool Schema:**

```php
<?php

declare(strict_types=1);

// Example: src/Tool/SendNotificationTool.php
namespace App\Tool; // Your application's namespace

use Shanginn\Openai\ChatCompletion\Tool\AbstractTool;
use Shanginn\Openai\ChatCompletion\Tool\OpenaiToolSchema;
use Spiral\JsonSchemaGenerator\Attribute\Field; // Use this for detailed fields

#[OpenaiToolSchema(
    name: 'send_notification',
    description: 'Sends a notification message to a specified user.'
)]
class SendNotificationTool extends AbstractTool
{
    public function __construct(
        #[Field(
            title: 'User ID',
            description: 'The unique identifier of the user to notify.'
        )]
        public string $userId,

        #[Field(
            title: 'Message Content',
            description: 'The text content of the notification message.'
        )]
        public string $message,

        #[Field(
            title: 'Priority',
            description: 'Notification priority level.',
            enum: ['low', 'medium', 'high'] // Example of defining allowed enum values
        )]
        public string $priority = 'medium',
    ) {}
}
```

**2. Call the Tool using `OpenaiSimple`:**

This simplifies the process of forcing a specific tool call and getting the parsed arguments.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use App\Tool\SendNotificationTool; // Import your tool class

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

$client = new OpenaiClient($apiKey);
$openaiCore = new Openai($client, 'gpt-5-mini');
$openaiSimple = new OpenaiSimple($openaiCore);

$system = "You are an assistant that executes tasks by calling tools.";
$text = "Please notify user 'usr_123' that their report is ready. Set priority to high.";

try {
    /**
     * Use a specific type hint for better static analysis
     * @var SendNotificationTool $notificationArgs
     */
    $notificationArgs = $openaiSimple->callTool(
        system: $system,
        text: $text,
        tool: SendNotificationTool::class // Pass the tool class string
    );

    echo "Executing Tool: {$notificationArgs::getName()}\n";
    echo "User ID: {$notificationArgs->userId}\n";    // Output: User ID: usr_123
    echo "Message: {$notificationArgs->message}\n";   // Output: Message: Your report is ready. (or similar)
    echo "Priority: {$notificationArgs->priority}\n"; // Output: Priority: high

    // $notificationArgs->execute(); // Call your tool execution logic here

} catch (OpenaiErrorResponseException $e) {
    echo "API Error: {$e->response->message}\n";
} catch (OpenaiWrongSchemaException $e) {
    // This is thrown if the model didn't call the tool or provided invalid arguments
    echo "Tool Call Error: Model response did not conform to the expected tool schema.\n";
    // Inspect $e->response for details, e.g., $e->response->choices[0]->message->content
} catch (\Throwable $e) {
    echo "General Error: {$e->getMessage()}\n";
}
```

**3. Call the Tool using Core `Openai` Class:**

This gives you more control over the request and access to the full response, including the tool call ID.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\UnknownFunctionCall;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice\ToolChoiceType; // Enum for tool choice types
use App\Tool\SendNotificationTool; // Your tool class

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

$client = new OpenaiClient($apiKey);
$openai = new Openai($client, 'gpt-5-mini');

$messages = [
    new UserMessage("Send a low priority notification to user 'jane_doe' saying 'Meeting rescheduled'.")
];

try {
    $response = $openai->completion(
        messages: $messages,
        tools: [SendNotificationTool::class], // Provide tool class string(s)
        // Force this specific tool:
        toolChoice: ToolChoice::useTool(SendNotificationTool::class)
        // Or let the model choose: new ToolChoice(ToolChoiceType::AUTO)
        // Or require any tool: new ToolChoice(ToolChoiceType::REQUIRED)
    );

    if ($response instanceof \Shanginn\Openai\ChatCompletion\ErrorResponse) {
        echo "API Error: {$response->message}\n";
    } elseif (isset($response->choices[0]->message->toolCalls[0])) {
        $toolCall = $response->choices[0]->message->toolCalls[0];

        // Check if the SDK successfully parsed the arguments into your tool class
        if ($toolCall instanceof KnownFunctionCall && $toolCall->arguments instanceof SendNotificationTool) {
            /** @var SendNotificationTool $notificationArgs */
            $notificationArgs = $toolCall->arguments;

            echo "Tool Call ID: {$toolCall->id}\n"; // Useful for sending back results
            echo "Function Called: {$toolCall->tool::getName()}\n";
            echo "User ID: {$notificationArgs->userId}\n";     // Output: User ID: jane_doe
            echo "Message: {$notificationArgs->message}\n";    // Output: Message: Meeting rescheduled.
            echo "Priority: {$notificationArgs->priority}\n";  // Output: Priority: low

            // Execute logic and potentially send back a ToolMessage in a subsequent call
            // $result = $notificationArgs->execute();
            // $toolResultMsg = new ToolMessage(content: json_encode(['success' => $result]), toolCallId: $toolCall->id);
            // $openai->completion(messages: [...$messages, $response->choices[0]->message, $toolResultMsg]);

        } elseif ($toolCall instanceof UnknownFunctionCall) {
            // The model called a function, but arguments didn't match the schema or deserialization failed
            echo "Unknown Function Call Detected:\n";
            echo "Function Name: {$toolCall->name}\n";
            echo "Raw Arguments JSON: {$toolCall->arguments}\n";
            // You might try to manually parse $toolCall->arguments here
        } else {
             echo "Unexpected tool call structure.\n";
        }
    } else {
        // The model generated text instead of calling a tool
        echo "No tool call detected.\n";
        echo "Assistant Content: " . ($response->choices[0]->message->content ?? 'N/A') . "\n";
    }

} catch (\Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

### JSON Schema Output

Force the model to generate a JSON object conforming to your predefined PHP class structure. Define the schema class implementing `JsonSchemaInterface` (often by extending `AbstractJsonSchema` and using `#[OpenaiSchema]`) and detail its properties using `#[Field]` attributes.

**1. Define the JSON Schema Class:**

```php
<?php

declare(strict_types=1);

// Example: src/Schema/ExtractedEventSchema.php
namespace App\Schema; // Your application's namespace

use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\AbstractJsonSchema;
use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\OpenaiSchema;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiSchema(
    name: 'event_details', // This name MUST be used in the prompt
    description: 'Schema for structured event information extracted from text.',
    isStrict: true // Recommended: Disallows extra properties not in the schema
)]
class ExtractedEventSchema extends AbstractJsonSchema
{
    public function __construct(
        #[Field(
            title: 'Event Title',
            description: 'A concise title for the event.'
        )]
        public string $title,

        #[Field(
            title: 'Date',
            description: 'The date of the event in YYYY-MM-DD format.',
        )]
        public string $date,

        #[Field(
            title: 'Location',
            description: 'The location where the event takes place. Null if virtual or not specified.'
        )]
        public ?string $location,

        #[Field(
            title: 'Attendees',
            description: 'A list of attendee names mentioned.'
        )]
        public array $attendees = [], // Default to empty array
    ) {}
}

```

**2. Generate JSON using `OpenaiSimple`:**

This simplifies getting the deserialized schema object directly.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use App\Schema\ExtractedEventSchema; // Import your schema class

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

$client = new OpenaiClient($apiKey);
$openaiCore = new Openai($client, 'gpt-5-mini');
$openaiSimple = new OpenaiSimple($openaiCore);

// IMPORTANT: You MUST instruct the model to use the specific schema by its name.
$system = "Extract event details from the user's text. Format the output strictly according to the 'event_details' JSON schema. Only output the JSON.";
$text = "Meeting with Bob and Alice on 2024-08-15 at the main office.";

try {
    /**
     * Use a specific type hint for the expected schema object
     * @var ExtractedEventSchema $eventDetails
     */
    $eventDetails = $openaiSimple->generate(
        system: $system,
        userMessage: $text,
        schema: ExtractedEventSchema::class // Pass the schema class string
    );

    echo "Extracted Event Details:\n";
    echo "Title: {$eventDetails->title}\n";      // Output: Title: Meeting (or similar)
    echo "Date: {$eventDetails->date}\n";        // Output: Date: 2024-08-15
    echo "Location: {$eventDetails->location}\n"; // Output: Location: main office
    echo "Attendees: " . implode(', ', $eventDetails->attendees) . "\n"; // Output: Attendees: Bob, Alice

} catch (OpenaiErrorResponseException $e) {
    echo "API Error: {$e->response->message}\n";
} catch (OpenaiWrongSchemaException $e) {
    // Thrown if the model's output couldn't be deserialized into ExtractedEventSchema
    echo "Schema Error: Model response did not conform to the expected JSON schema.\n";
    // You can inspect the raw JSON attempt (if any) via $e->response->choices[0]->message->content
} catch (\Throwable $e) {
    echo "General Error: {$e->getMessage()}\n";
}

```

**3. Generate JSON using Core `Openai` Class:**

Provides access to the full response, including the raw JSON string before deserialization.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\SchemedAssistantMessage;
use App\Schema\ExtractedEventSchema; // Your schema class

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

$client = new OpenaiClient($apiKey);
$openai = new Openai($client, 'gpt-5-mini');

$messages = [
    new UserMessage('Project deadline discussion is on 2024-09-01 with Charlie.')
];

// Define the response format requesting your schema
$responseFormat = new ResponseFormat(
    type: ResponseFormatEnum::JSON_SCHEMA,
    jsonSchema: ExtractedEventSchema::class // Pass the schema class string
);

try {
    // IMPORTANT: Instruct the model to use the schema by name in the prompt!
    $systemPrompt = "Extract event details using the 'event_details' JSON schema. Output only the JSON object.";

    $response = $openai->completion(
        messages: $messages,
        system: $systemPrompt,
        responseFormat: $responseFormat // Pass the format object
    );

    if ($response instanceof \Shanginn\Openai\ChatCompletion\ErrorResponse) {
         echo "API Error: {$response->message}\n";
    } elseif (isset($response->choices[0]->message) && $response->choices[0]->message instanceof SchemedAssistantMessage) {
        // The SDK successfully deserialized the response content into your schema object
        /** @var SchemedAssistantMessage $schemedMessage */
        $schemedMessage = $response->choices[0]->message;

        if ($schemedMessage->schemedContend instanceof ExtractedEventSchema) {
            /** @var ExtractedEventSchema $eventDetails */
            $eventDetails = $schemedMessage->schemedContend;

            echo "Extracted Event Details (Core):\n";
            echo "Title: {$eventDetails->title}\n";      // Output: Title: Project deadline discussion
            echo "Date: {$eventDetails->date}\n";        // Output: Date: 2024-09-01
            echo "Location: " . ($eventDetails->location ?? 'N/A') . "\n"; // Output: Location: N/A
            echo "Attendees: " . implode(', ', $eventDetails->attendees) . "\n"; // Output: Attendees: Charlie

            // Access the original raw JSON string if needed:
            // echo "Raw JSON: {$schemedMessage->content}\n";

        } else {
            // Should not happen if SchemedAssistantMessage is constructed, but for safety:
            echo "Schema type mismatch after deserialization.\n";
        }
    } else {
        // The response was received, but it wasn't deserialized into SchemedAssistantMessage
        // This usually means the model's output was not valid JSON or didn't match the schema.
        echo "Response is not a valid schemed message or has no choices.\n";
         // Check raw content if available:
         if (isset($response->choices[0]->message->content)) {
            echo "Raw Content from Model: " . $response->choices[0]->message->content . "\n";
         }
    }

} catch (\Throwable $e) {
    // Catches transport errors or potential issues during deserialization setup
    echo "Error: {$e->getMessage()}\n";
}
```

### Image Input

Provide an array of `ContentPartInterface` objects (`TextContentPart`, `ImageContentPart`) to the `UserMessage` constructor. Requires a vision-capable model like `gpt-4o`.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageDetailLevelEnum;
use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

$client = new OpenaiClient($apiKey);
// Use a model that supports vision, like gpt-4o or gpt-5-mini
$openai = new Openai($client, 'gpt-5-mini');

// Example using a URL
$imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/1280px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg';

// Example using base64 encoded image
// $imageData = base64_encode(file_get_contents('path/to/your/image.jpg'));
// $imageBase64Url = 'data:image/jpeg;base64,' . $imageData;

$messages = [
    new UserMessage(content: [ // Pass an array of content parts
        new TextContentPart(text: "What season does this image depict?"),
        new ImageContentPart(
            url: $imageUrl,
            detail: ImageDetailLevelEnum::LOW // Optional: LOW, HIGH, or AUTO (default)
        )
        // Add more ImageContentPart for multiple images if needed
        // new ImageContentPart(url: $imageBase64Url)
    ])
];

try {
    $response = $openai->completion(messages: $messages, maxTokens: 100);

    if ($response instanceof \Shanginn\Openai\ChatCompletion\ErrorResponse) {
         echo "API Error: {$response->message}\n";
    } elseif (count($response->choices) > 0) {
        echo "Assistant: {$response->choices[0]->message->content}\n";
        // Example Output: Assistant: The image appears to depict summer or late spring...
    } else {
        echo "No choices returned.\n";
    }

} catch (\Throwable $e) {
     echo "Error: {$e->getMessage()}\n";
}

```

### Error Handling

The SDK throws specific exceptions found in the `Shanginn\Openai\Exceptions` namespace for easier error management.

*   `OpenaiErrorResponseException`: Wraps an `ErrorResponse` object returned directly by the OpenAI API (e.g., invalid API key, rate limit exceeded). Access the details via `$e->response`.
*   `OpenaiRefusedResponseException`: Thrown by `OpenaiSimple` when the model explicitly refuses to answer (contains a `refusal` message). Access via `$e->refusal` and `$e->response`.
*   `OpenaiNoChoicesException`: Thrown when the API returns a valid response but with an empty `choices` array. Access the original response via `$e->response`.
*   `OpenaiNoContentException`: Thrown by `OpenaiSimple` when a choice exists but has no `content`. Access via `$e->response`.
*   `OpenaiWrongSchemaException`: Thrown by `OpenaiSimple` or potentially during core `Openai` processing if JSON schema/tool calling was requested, but the response didn't conform as expected (e.g., deserialization failed). Access via `$e->response`.
*   `OpenaiInvalidResponseException`: Base class for response validation issues like `NoChoices`, `NoContent`, `WrongSchema`.
*   `OpenaiException`: Base exception for all SDK-specific errors.

```php
<?php

use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiRefusedResponseException;
use Shanginn\Openai\Exceptions\OpenaiNoChoicesException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use Shanginn\Openai\Exceptions\OpenaiException; // Base SDK exception

// ... setup $openaiSimple ...

try {
    $result = $openaiSimple->generate(
        system: "You only respond with 'I cannot answer that.'",
        userMessage: "What is 2+2?",
        // potentially add schema or tool here to trigger other exceptions
        temperature: 0
    );
    echo $result . "\n";
} catch (OpenaiErrorResponseException $e) {
    // API returned an error object (e.g., bad API key, rate limit)
    echo "API Error [{$e->response->code} {$e->response->type}]: {$e->response->message}\n";
} catch (OpenaiRefusedResponseException $e) {
    // Model refused to answer (specific to OpenaiSimple detection logic)
    echo "Model Refused: {$e->refusal}\n";
    // You can still inspect the raw $e->response if needed
} catch (OpenaiWrongSchemaException $e) {
    // Expected schema/tool call wasn't found or failed deserialization
    echo "Schema/Tool Error: Model response did not conform.\n";
    // Inspect $e->response for details (e.g., raw content)
} catch (OpenaiNoChoicesException $e) {
    // Valid response, but no choices provided
    echo "No choices returned by the API.\n";
} catch (OpenaiException $e) {
    // Catch other SDK-specific issues
    echo "SDK Error: {$e->getMessage()}\n";
} catch (\Throwable $e) {
    // Catch potential transport errors or other library issues
    echo "General Error: {$e->getMessage()}\n";
}
```

## Dependencies

*   [amphp/http-client](https://github.com/amphp/http-client): For asynchronous HTTP requests.
*   [crell/serde](https://github.com/crell/serde): For robust serialization and deserialization between PHP objects and JSON.
*   [symfony/serializer](https://symfony.com/doc/current/components/serializer.html): Used alongside `crell/serde` for normalization, particularly handling enums, snake_case, and custom normalizers.
*   [spiral/json-schema-generator](https://github.com/spiral/json-schema-generator): **(Recommended)** Used internally and via attributes (`#[Field]`) to generate detailed JSON Schema definitions from PHP classes for Tool Calling and JSON Schema mode.

## Contributing

Contributions are welcome! Please follow these general steps:

1.  Fork the repository.
2.  Create a new branch for your feature or bug fix (`git checkout -b feature/my-new-feature`).
3.  Make your changes.
4.  Add tests for your changes.
5.  Ensure tests pass (`vendor/bin/phpunit`).
6.  Ensure code style compliance (e.g., using PHP CS Fixer or Rector with provided config, if any).
7.  Commit your changes (`git commit -am 'Add some feature'`).
8.  Push to the branch (`git push origin feature/my-new-feature`).
9.  Create a new Pull Request.

## License

This project is licensed under the MIT License