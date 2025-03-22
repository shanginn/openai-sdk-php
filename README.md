# OpenAI SDK PHP

A PHP SDK for the OpenAI API with a simple, fluent interface and strong type support.

## Features

- Type-safe client for OpenAI API
- Support for ChatCompletion API with streaming
- Function calling/tools support with JSON Schema validation
- Simple interface for basic usage

## Installation

```bash
composer require shanginn/openai-sdk-php
```

## Requirements

- PHP 8.2+
- Composer

## Usage

### Basic Usage

```php
use App\Openai\Openai;
use App\Openai\Openai\OpenaiClient;
use App\Openai\OpenaiSimple;

// Create the client
$client = new OpenaiClient('your-api-key');
$openai = new Openai($client, 'gpt-4o-mini'); // Specify model
$simple = new OpenaiSimple($openai);

// Simple completion
$result = $simple->generate(
    system: "You are a helpful assistant.",
    userMessage: "Write a short poem about programming.",
    temperature: 0.7,
    maxTokens: 1024
);

echo $result; // String response
```

### Using Schemas

You can use schemas to get structured responses from the API:

```php
use App\Openai\ChatCompletion\CompletionRequest\JsonSchema\AbstractJsonSchema;
use App\Openai\ChatCompletion\CompletionRequest\JsonSchema\OpenaiSchema;
use App\Openai\Openai;
use App\Openai\Openai\OpenaiClient;
use App\Openai\OpenaiSimple;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiSchema(
    name: 'product_recommendation_schema',
    description: 'Generate a product recommendation based on customer preferences.'
)]
class ProductRecommendationSchema extends AbstractJsonSchema
{
    public function __construct(
        #[Field(
            title: 'Product Category',
            description: <<<'TEXT'
                The specific product category that best suits the customer's needs.
                
                Should be specific enough to narrow down the choices but broad enough to include a range of options.
                Example categories include: "wireless headphones", "gaming laptops", "ergonomic office chairs".
                TEXT,
        )]
        public string $category,

        #[Field(
            title: 'Product Name',
            description: 'The name of the recommended product'
        )]
        public string $name,

        #[Field(
            title: 'Key Features',
            description: 'The most important features of the product that make it suitable for the customer'
        )]
        public string $features,

        #[Field(
            title: 'Price Range',
            description: 'The approximate price range of the product'
        )]
        public string $priceRange,
    ) {}
}

$client = new OpenaiClient('your-api-key');
$openai = new Openai($client);
$simple = new OpenaiSimple($openai);

$recommendation = $simple->generate(
    system: "You are an expert product advisor.",
    userMessage: "I need a pair of headphones for running that are sweat-resistant and have good battery life.",
    temperature: 0.7,
    maxTokens: 1024,
    schema: ProductRecommendationSchema::class, // Type-safe structured output
    seed: random_int(0, 2 ** 32 - 1),
);

// Now you have a typed object
echo $recommendation->name;
echo $recommendation->category;
echo $recommendation->features;
echo $recommendation->priceRange;
```

### Working with Tools

The SDK supports both OpenAI and Anthropic tools. You can define a tool that works with both services:

```php
use App\Anthropic\Tool\AnthropicToolSchema;
use App\Openai\ChatCompletion\Tool\AbstractTool;
use App\Openai\ChatCompletion\Tool\OpenaiToolSchema;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiToolSchema(
    name: 'analyze_customer_feedback',
    description: 'Analyze customer feedback and provide structured insights'
)]
class CustomerFeedbackAnalysisSchema extends AbstractTool
{
    public function __construct(
        #[Field(
            title: 'Summary',
            description: 'A brief summary of the overall customer feedback'
        )]
        public string $summary,
        #[Field(
            title: 'Positive Points',
            description: 'The main positive aspects mentioned in the feedback'
        )]
        public string $positivePoints,
        #[Field(
            title: 'Areas for Improvement',
            description: 'The main areas that need improvement according to the feedback'
        )]
        public string $areasForImprovement,
        #[Field(
            title: 'Customer Sentiment',
            description: 'The overall customer sentiment (Positive, Neutral, Negative)'
        )]
        public string $sentiment,
        #[Field(
            title: 'Recommendations',
            description: 'Actionable recommendations based on the feedback analysis'
        )]
        public string $recommendations,
    ) {}
}
```

### Using Tools with OpenAI

```php
use App\Openai\Openai;
use App\Openai\Openai\OpenaiClient;
use App\Openai\ChatCompletion\CompletionRequest\ToolChoice;

$client = new OpenaiClient('your-api-key');
$openai = new Openai($client);

$customerFeedback = "The product is well-designed and the materials feel premium. However, I found the setup process confusing and the user manual wasn't helpful. Customer support was friendly but couldn't resolve my issue completely.";

$messages = [/* Previous conversation messages */];
$messages[] = new UserMessage($customerFeedback);

$response = $openai->completion(
    system: "You are a customer feedback analyst.",
    messages: $messages,
    tools: [CustomerFeedbackAnalysisSchema::class],
    toolChoice: ToolChoice::useTool(CustomerFeedbackAnalysisSchema::class),
);

// Process the response
$choice = current($response->choices);
$toolCalls = $choice->message?->toolCalls ?? [];
$toolCall = current($toolCalls);

if ($toolCall->arguments instanceof CustomerFeedbackAnalysisSchema) {
    $summary = $toolCall->arguments->summary;
    $positivePoints = $toolCall->arguments->positivePoints;
    $areasForImprovement = $toolCall->arguments->areasForImprovement;
    $sentiment = $toolCall->arguments->sentiment;
    $recommendations = $toolCall->arguments->recommendations;
}
```

### Using the Simple Interface with Tools

```php
use App\Openai\Openai;
use App\Openai\Openai\OpenaiClient;
use App\Openai\OpenaiSimple;

$client = new OpenaiClient('your-api-key');
$openai = new Openai($client);
$simple = new OpenaiSimple($openai);

$customerFeedback = "The product is well-designed and the materials feel premium. However, I found the setup process confusing and the user manual wasn't helpful. Customer support was friendly but couldn't resolve my issue completely.";

$result = $simple->callTool(
    system: "You are a customer feedback analyst.",
    text: $customerFeedback,
    tool: CustomerFeedbackAnalysisSchema::class,
    temperature: 0.7
);

echo $result->summary;
echo $result->positivePoints;
echo $result->recommendations;
```