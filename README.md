# OpenAI SDK PHP

[![Latest Stable Version](https://poser.pugx.org/shanginn/openai-sdk-php/v)](https://packagist.org/packages/shanginn/openai-sdk-php) <!-- Replace with actual badge URLs -->
[![Total Downloads](https://poser.pugx.org/shanginn/openai-sdk-php/downloads)](https://packagist.org/packages/shanginn/openai-sdk-php)
[![License](https://poser.pugx.org/shanginn/openai-sdk-php/license)](https://packagist.org/packages/shanginn/openai-sdk-php) <!-- Replace with your license -->
[![Build Status](https://github.com/shanginn/openai-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/shanginn/openai-sdk-php/actions/workflows/ci.yml) <!-- Replace with actual repo path -->
[![Coverage Status](https://coveralls.io/repos/github/shanginn/openai-sdk-php/badge.svg?branch=main)](https://coveralls.io/github/shanginn/openai-sdk-php?branch=main) <!-- Replace with actual repo path -->

A strongly typed, truly asynchronous PHP SDK for the OpenAI Responses and Chat Completions APIs. The transport uses native [PHP True Async](https://github.com/true-async) coroutines and async-aware cURL, with compatibility profiles for OpenAI, DeepSeek, Qwen, xAI/Grok, and custom OpenAI-compatible providers.

## Features

*   **Responses API:** Typed requests and forward-compatible response items for `/v1/responses`, the recommended API for new OpenAI and xAI integrations.
*   **Chat Completions:** Backward-compatible, strongly typed access to `/v1/chat/completions`.
*   **Strongly-Typed Objects:** Uses PHP classes for Requests, Responses, Messages, Tools, and Schemas, providing better IDE autocompletion and type safety.
*   **Tool Calling:** Define and use tools (functions) that the OpenAI models can invoke. Includes automatic deserialization of tool arguments into PHP objects based on class definitions with attributes like `Spiral\JsonSchemaGenerator\Attribute\Field`.
*   **JSON Schema Mode:** Force the model to output JSON conforming to a specific structure defined by your PHP classes implementing `JsonSchemaInterface`, utilizing attributes like `Spiral\JsonSchemaGenerator\Attribute\Field` for detailed schema generation.
*   **Multimodal Input:** Supports image content, including the latest `original` image detail level.
*   **True Async:** Returns native `Async\Coroutine` objects, supports cancellation, concurrent requests, and streams SSE data through `Async\Channel`.
*   **Provider Compatibility:** Normalizes documented parameter differences without restricting model IDs to a hard-coded allowlist.
*   **Serialization:** Uses `crell/serde` and `symfony/serializer` for mapping between PHP objects and OpenAI's JSON format.
*   **Simplified Wrapper:** Includes an `OpenaiSimple` class for common use cases like simple text generation, JSON object generation, and tool calling with less boilerplate.
*   **Custom Exceptions:** Provides specific exceptions for different API error conditions (e.g., `OpenaiErrorResponseException`, `OpenaiNoChoicesException`, `OpenaiWrongSchemaException`).

## Installation

PHP 8.6+ with the [True Async runtime](https://true-async.github.io/download/) and cURL extensions is required. Install the SDK via Composer:

```bash
composer require shanginn/openai-sdk
```

The package requires `ext-true_async` 0.8.2+ and includes
`true-async/ide-helper` for IDE and static-analysis support.

## Usage

### Responses API (Recommended)

OpenAI and xAI recommend the Responses API for new integrations. The response
object retains every raw output item, while providing helpers for text,
refusals, function calls, and reasoning items.

```php
<?php

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Responses\ResponseRequest;
use Shanginn\Openai\ChatCompletion\ErrorResponse;

$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new RuntimeException('OPENAI_API_KEY is not set.');
}

$openai = new Openai(new OpenaiClient($apiKey));
$response = $openai->response(new ResponseRequest(
    model: 'gpt-5.6-terra',
    instructions: 'Answer accurately and concisely.',
    input: 'Why is the sky blue?',
    reasoning: ['effort' => 'medium'],
    store: false,
));

if ($response instanceof ErrorResponse) {
    throw new RuntimeException($response->message ?? 'Unknown API error');
}

echo $response->outputText();
```

The request object intentionally accepts `extraBody`, and the response retains
the complete decoded payload in `$response->raw`. This keeps new model and
provider fields usable without waiting for an SDK release.

`ResponseRequest::$reasoning` accepts the current GPT-5.6 controls, including
effort levels through `max`, `mode: pro`, and persisted-reasoning `context`.
Typed prompt-cache, safety identifier, service tier, and continuation fields are
included. Raw tool arrays and output items preserve newer features such as
programmatic tool calling without lossy deserialization.

### True Async Concurrency and Streaming

Every asynchronous request returns a native `Async\Coroutine`. Standard cURL
calls become non-blocking inside the True Async runtime, so several API requests
can progress concurrently without an alternative HTTP stack.

```php
use Shanginn\Openai\Responses\ResponseRequest;
use function Async\await_all_or_fail;
use function Async\timeout;

$requests = [
    $openai->responseAsync(new ResponseRequest('gpt-5.6-terra', 'Summarize PHP fibers.')),
    $openai->responseAsync(new ResponseRequest('gpt-5.6-luna', 'Explain event loops.')),
];

$responses = await_all_or_fail($requests, timeout(30_000));

foreach ($openai->streamResponse(new ResponseRequest(
    model: 'gpt-5.6-terra',
    input: 'Write a short haiku.',
)) as $event) {
    // For example: response.output_text.delta
    var_dump($event);
}
```

Coroutines can be cancelled through their native `cancel()` method. The default
HTTP timeout is one hour to accommodate long-running reasoning models; override
it in the `OpenaiClient` constructor when appropriate.

### Simple Text Generation (`OpenaiSimple`)

`OpenaiSimple` remains the shortest path for Chat Completions:

```php
use Shanginn\Openai\OpenaiSimple;

$openai = OpenaiSimple::create(
    apiKey: $apiKey,
);

echo $openai->generate(
    system: 'Translate English to French.',
    userMessage: 'Hello, world!',
);
```

### Chat Completions

Chat Completions remains supported for existing applications and providers that
do not yet expose the Responses API.

```php
use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\ErrorResponse;

$openai = new Openai(
    client: new OpenaiClient($apiKey),
    model: 'gpt-5.6',
);

$response = $openai->completion(
    messages: [new UserMessage('What is the chemical symbol for water?')],
    maxCompletionTokens: 50,
);

if ($response instanceof ErrorResponse) {
    throw new RuntimeException($response->message ?? 'Unknown API error');
}

echo $response->choices[0]->message->content;
```

For GPT-5.6 tool calls through Chat Completions, the compatibility profile
automatically supplies `reasoning_effort: none` when no effort was provided,
matching the current OpenAI tool-calling constraint. Use a
`DeveloperMessage` when you need the current developer role explicitly.

### Models and Provider Compatibility

Model IDs are plain strings and are never restricted to an SDK allowlist. For
OpenAI, `gpt-5.6` aliases the frontier `gpt-5.6-sol` model,
`gpt-5.6-terra` balances intelligence and cost, and `gpt-5.6-luna` is
optimized for high-volume work. The SDK defaults Chat Completions to the
`gpt-5.6` alias.

Choose a provider profile when constructing `Openai`. It normalizes only known,
documented incompatibilities; `Provider::CUSTOM` sends the body unchanged.

#### DeepSeek

DeepSeek currently exposes `deepseek-v4-pro` through Chat Completions.
`deepseek-v4-flash` can also use the Responses API.

```php
use Shanginn\Openai\Openai;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\Provider\Provider;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;

$deepseek = new Openai(
    client: new OpenaiClient($deepseekApiKey, 'https://api.deepseek.com'),
    model: 'deepseek-v4-pro',
    provider: Provider::DEEPSEEK,
);

$response = $deepseek->completion(
    messages: [new UserMessage('Answer briefly.')],
    reasoningEffort: 'high',
    extraBody: ['thinking' => ['type' => 'enabled']],
);
```

In thinking mode, unsupported sampling and log-probability parameters are
removed automatically. When continuing a DeepSeek tool-call conversation,
preserve the assistant message's `reasoning_content`; the included
`AssistantMessage` type serializes it.

#### Qwen

Pass the exact Model Studio workspace endpoint for your region. Qwen's
Responses API uses standard `reasoning.effort`; for Chat Completions the SDK
translates the generic `thinking` field to `enable_thinking`.

```php
$qwen = new Openai(
    client: new OpenaiClient($qwenApiKey, $qwenWorkspaceUrl),
    model: 'qwen3.7-plus',
    provider: Provider::QWEN,
);

$response = $qwen->response(new ResponseRequest(
    model: 'qwen3.7-plus',
    input: 'Solve this carefully: 17 * 23',
    reasoning: ['effort' => 'high'],
));
```

The profile rejects Qwen Chat requests that combine thinking with structured
output, and rejects the reserved function name `search`, before an invalid
request reaches the API.

#### xAI / Grok

xAI recommends Responses for new work and supports stateful continuation with
`previousResponseId`.

```php
$grok = new Openai(
    client: new OpenaiClient($xaiApiKey, 'https://api.x.ai/v1'),
    provider: Provider::XAI,
);

$response = $grok->response(new ResponseRequest(
    model: 'grok-4.5',
    input: 'Explain this repository architecture.',
    store: true,
));

$continued = $grok->response(new ResponseRequest(
    model: 'grok-4.5',
    input: 'Now identify the main risks.',
    previousResponseId: $response->id,
));
```

For any provider, put new or provider-specific top-level fields in
`ResponseRequest::$extraBody` or the Chat `extraBody` argument. Custom
authentication headers can be supplied to `OpenaiClient`.

The compatibility profiles follow the providers' current documentation:
[OpenAI latest models](https://developers.openai.com/api/docs/guides/latest-model),
[OpenAI Responses migration](https://developers.openai.com/api/docs/guides/migrate-to-responses),
[DeepSeek models and API coverage](https://api-docs.deepseek.com/quick_start/pricing/),
[DeepSeek thinking mode](https://api-docs.deepseek.com/guides/thinking_mode/),
[Qwen Responses compatibility](https://help.aliyun.com/en/model-studio/qwen-api-via-openai-responses),
and [xAI Responses](https://docs.x.ai/developers/model-capabilities/text/generate-text).

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
$openaiCore = new Openai($client, 'gpt-5.6-terra');
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
$openai = new Openai($client, 'gpt-5.6-terra');

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
$openaiCore = new Openai($client, 'gpt-5.6-terra');
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
$openai = new Openai($client, 'gpt-5.6-terra');

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

Provide an array of `ContentPartInterface` objects (`TextContentPart`, `ImageContentPart`) to the `UserMessage` constructor. This requires a model that accepts image input.

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
// GPT-5.6 accepts text and image input.
$openai = new Openai($client, 'gpt-5.6-terra');

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
            detail: ImageDetailLevelEnum::ORIGINAL // Optional: LOW, HIGH, AUTO, or ORIGINAL
        )
        // Add more ImageContentPart for multiple images if needed
        // new ImageContentPart(url: $imageBase64Url)
    ])
];

try {
    $response = $openai->completion(messages: $messages, maxCompletionTokens: 100);

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
*   `OpenaiTransportException`: Thrown when cURL cannot complete an HTTP request.
*   `ProviderCompatibilityException`: Thrown before sending a request containing a known-invalid provider/model parameter combination.
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

*   [PHP True Async](https://github.com/true-async/php-async): Native coroutines, cancellation, channels, and async-aware cURL.
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
