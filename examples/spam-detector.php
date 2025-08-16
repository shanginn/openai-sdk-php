<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Shanginn\Openai\Openai;
use Shanginn\Openai\OpenaiSimple;
use Shanginn\Openai\Openai\OpenaiClient;
use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\AbstractJsonSchema;
use Shanginn\Openai\ChatCompletion\CompletionRequest\JsonSchema\OpenaiSchema;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\Exceptions\OpenaiErrorResponseException;
use Shanginn\Openai\Exceptions\OpenaiWrongSchemaException;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[OpenaiSchema(
    name: 'simple_spam_analysis_result',
    description: 'Simplified structured result of spam analysis for a message.',
    isStrict: true
)]
class SimpleSpamAnalysisSchema extends AbstractJsonSchema
{
    public function __construct(
        #[Field(
            title: 'Reason',
            description: 'A short explanation why the message is or isn\'t considered spam.'
        )]
        public string $reason,

        #[Field(
            title: 'Is Spam',
            description: 'True if the message is classified as spam, false otherwise.'
        )]
        public bool   $isSpam,
    ) {}
}

// --- Main Analysis Function (Using Simplified Schema and New Prompt) ---

/**
 * Analyzes a message for spam potential using a simplified schema via OpenaiSimple,
 * potentially including an image for analysis.
 *
 * @param OpenaiSimple $openaiSimple Initialized OpenaiSimple SDK instance.
 * @param string $messageText The main text content of the message to analyze.
 * @param ?string $imageUrl Optional URL of an image attached to the message.
 *
 * @return SimpleSpamAnalysisSchema The structured analysis result.
 * @throws OpenaiErrorResponseException If the OpenAI API returns an error.
 * @throws OpenaiWrongSchemaException If the response does not conform to the requested schema.
 * @throws RuntimeException If other unexpected errors occur.
 */
function analyzeMessageForSpam(
    OpenaiSimple $openaiSimple,
    string $messageText,
    ?string $imageUrl = null,
): SimpleSpamAnalysisSchema {
    $systemPromptContent = <<<TEXT
        You are a spam detection assistant for a chat group. Analyze the user message (text and potentially an image) to determine if it constitutes spam.
        Messages about finding travel companions, parcel delivery, cryptocurrency questions (without promotion), selling personal items, jokes, or links to known safe sites are NOT spam.
        If an image is included, consider its content in your analysis.
        TEXT;

    $userMessageContent = [
        new TextContentPart($messageText)
    ];

    if ($imageUrl !== null) {
        $userMessageContent[] = new ImageContentPart($imageUrl);
    }

    try {
        $result = $openaiSimple->generate(
            system: $systemPromptContent,
            userMessage: new UserMessage(content: $userMessageContent),
            schema: SimpleSpamAnalysisSchema::class,
            temperature: 0.1,
            maxTokens: 150
        );

        // 4. Validate the returned type
        if (!$result instanceof SimpleSpamAnalysisSchema) {
            throw new RuntimeException('OpenaiSimple->generate returned an unexpected type.');
        }

        return $result;
    } catch (OpenaiErrorResponseException | OpenaiWrongSchemaException $e) {
        throw $e;
    } catch (\Exception $e) {
        throw new RuntimeException("An unexpected error occurred during OpenAI analysis: " . $e->getMessage(), 0, $e);
    }
}

// --- Example Usage ---

// --- Get API Key (Replace with your secure method) ---
$apiKey = getenv('OPENAI_API_KEY');
if ($apiKey === false) {
    throw new \RuntimeException('Error: OPENAI_API_KEY environment variable not set.');
}

// --- Initialize SDK ---
// Ensure you use a model that supports vision and JSON mode!
$openaiCore = new Openai(
    client: new OpenaiClient($apiKey),
    model: 'gpt-5-mini'
);
$openaiSimple = new OpenaiSimple($openaiCore);

// --- Run Analyses ---

echo "--- Simplified Spam Analysis Examples (Using OpenaiSimple) ---\n\n";

try {
    // Example 1: Spammy Text + Spammy Image
    $sampleMessageTextSpam = "BUY CHEAP WATCHES NOW!!! LIMITED OFFER!!! -> dodgy.link/watches";
    $sampleImageUrlSpam = 'https://dummyimage.com/600x400/000/fff&text=Click+Here+for+Free+Stuff!'; // Example placeholder
    echo "Analyzing: '$sampleMessageTextSpam' with Image URL: $sampleImageUrlSpam\n";
    $result1 = analyzeMessageForSpam(
        $openaiSimple,
        $sampleMessageTextSpam,
        $sampleImageUrlSpam,
    );
    echo "Result 1:\n";
    echo "  Reason: {$result1->reason}\n";
    echo "  Is Spam: " . ($result1->isSpam ? 'Yes' : 'No') . "\n\n"; // Use the new property name

    // Example 2: Normal Text + Normal Image
    $normalMessage = "Anyone up for coffee later today?";
    $normalImageUrl = 'https://dummyimage.com/600x400/000/fff&text=Calendar+Icon'; // Benign image
    echo "Analyzing: '$normalMessage' with Image URL: $normalImageUrl\n";
    $result2 = analyzeMessageForSpam( // Call the updated function
        $openaiSimple,
        $normalMessage,
        "Friends Chat",
        $normalImageUrl
    );
    echo "Result 2:\n";
    echo "  Reason: {$result2->reason}\n";
    echo "  Is Spam: " . ($result2->isSpam ? 'Yes' : 'No') . "\n\n";

    // Example 3: Spammy Text only
    $spammyTextOnly = "!!! MAKE MONEY FAST FROM HOME CLICK HERE !!!";
    echo "Analyzing: '$spammyTextOnly' (Text Only)\n";
    $result3 = analyzeMessageForSpam($openaiSimple, $spammyTextOnly); // Call updated function
    echo "Result 3:\n";
    echo "  Reason: {$result3->reason}\n";
    echo "  Is Spam: " . ($result3->isSpam ? 'Yes' : 'No') . "\n\n";
} catch (OpenaiErrorResponseException $e) {
    echo "!! OpenAI API Error !!\n";
    echo "Type: {$e->response->type}\n";
    echo "Code: {$e->response->code}\n";
    echo "Message: {$e->response->message}\n";
} catch (OpenaiWrongSchemaException $e) {
    echo "!! OpenAI Schema Error !!\n";
    echo "Message: {$e->getMessage()}\n";
    if (isset($e->response->choices[0]->message->content)) {
        echo "Raw Content Received: " . $e->response->choices[0]->message->content . "\n";
    }
} catch (\Exception $e) {
    echo "!! An Error Occurred !!\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
}