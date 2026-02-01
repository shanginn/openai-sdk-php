<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Shanginn\Openai\ChatCompletion\CompletionRequest;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormat;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ResponseFormatEnum;
use Shanginn\Openai\ChatCompletion\CompletionRequest\Role;
use Shanginn\Openai\ChatCompletion\CompletionRequest\ToolChoice;
use Shanginn\Openai\ChatCompletion\ToolChoice\ToolChoiceType;
use Shanginn\Openai\ChatCompletion\CompletionResponse;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Choice;
use Shanginn\Openai\ChatCompletion\CompletionResponse\Usage;
use Shanginn\Openai\ChatCompletion\Message\AssistantMessage;
use Shanginn\Openai\ChatCompletion\Message\Assistant\KnownFunctionCall;
use Shanginn\Openai\ChatCompletion\Message\Assistant\ToolCallTypeEnum;
use Shanginn\Openai\ChatCompletion\Message\SystemMessage;
use Shanginn\Openai\ChatCompletion\Message\ToolMessage;
use Shanginn\Openai\ChatCompletion\Message\UserMessage;
use Shanginn\Openai\ChatCompletion\Message\User\ImageContentPart;
use Shanginn\Openai\ChatCompletion\Message\User\ImageDetailLevelEnum;
use Shanginn\Openai\ChatCompletion\Message\User\TextContentPart;
use Shanginn\Openai\ChatCompletion\Tool\AbstractTool;
use Shanginn\Openai\Openai\OpenaiSerializer;
use Spiral\JsonSchemaGenerator\Attribute\Field;

#[\Shanginn\Openai\ChatCompletion\Tool\OpenaiToolSchema(name: 'round_trip_tool', description: 'A test tool for round trip')]
class RoundTripTool extends AbstractTool
{
    public function __construct(
        #[Field(title: 'Message')]
        public string $message,
        #[Field(title: 'Count')]
        public int $count = 1,
    ) {}
}

class SerializationRoundTripTest extends TestCase
{
    private OpenaiSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new OpenaiSerializer();
    }

    // ========== Message Round-trip Tests ==========

    public function testAssistantMessageRoundTrip(): void
    {
        $original = new AssistantMessage(content: 'Test message', name: 'TestName');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        // Verify structure
        $this->assertEquals('assistant', $decoded['role']);
        $this->assertEquals('Test message', $decoded['content']);
        $this->assertEquals('TestName', $decoded['name']);
        
        // Deserialize and verify
        $deserialized = $this->serializer->serializer->deserialize($json, AssistantMessage::class, 'json');
        $this->assertEquals($original->content, $deserialized->content);
        $this->assertEquals($original->name, $deserialized->name);
        $this->assertEquals($original->role, $deserialized->role);
    }

    public function testUserMessageRoundTrip(): void
    {
        $original = new UserMessage('Hello from user');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('user', $decoded['role']);
        $this->assertEquals('Hello from user', $decoded['content']);
    }

    public function testSystemMessageRoundTrip(): void
    {
        $original = new SystemMessage('You are a helpful assistant');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('system', $decoded['role']);
        $this->assertEquals('You are a helpful assistant', $decoded['content']);
    }

    public function testToolMessageRoundTrip(): void
    {
        $original = new ToolMessage('The result is 42', 'tool_result_123');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('tool', $decoded['role']);
        $this->assertEquals('tool_result_123', $decoded['tool_call_id']);
        $this->assertEquals('The result is 42', $decoded['content']);
    }

    // ========== Content Part Round-trip Tests ==========

    public function testTextContentPartRoundTrip(): void
    {
        $original = new TextContentPart('Test text content');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('text', $decoded['type']);
        $this->assertEquals('Test text content', $decoded['text']);
    }

    public function testImageContentPartRoundTrip(): void
    {
        $original = new ImageContentPart(
            url: 'https://example.com/image.png',
            detail: ImageDetailLevelEnum::HIGH,
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('image_url', $decoded['type']);
        $this->assertEquals('https://example.com/image.png', $decoded['image_url']['url']);
        $this->assertEquals('high', $decoded['image_url']['detail']);
    }

    public function testImageContentPartWithoutDetailRoundTrip(): void
    {
        $original = new ImageContentPart(url: 'https://example.com/image.png');
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('image_url', $decoded['type']);
        $this->assertEquals('https://example.com/image.png', $decoded['image_url']['url']);
        $this->assertArrayNotHasKey('detail', $decoded['image_url']);
    }

    // ========== Tool Call Round-trip Tests ==========

    public function testKnownFunctionCallRoundTrip(): void
    {
        $original = new KnownFunctionCall(
            id: 'call_abc123',
            tool: RoundTripTool::class,
            arguments: new RoundTripTool(message: 'Test', count: 5),
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('call_abc123', $decoded['id']);
        $this->assertEquals('function', $decoded['type']);
        $this->assertEquals('round_trip_tool', $decoded['function']['name']);
        $this->assertJson($decoded['function']['arguments']);
        
        $args = json_decode($decoded['function']['arguments'], true);
        $this->assertEquals('Test', $args['message']);
        $this->assertEquals(5, $args['count']);
    }

    // ========== Completion Request Round-trip Tests ==========

    public function testSimpleCompletionRequestRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [
                new SystemMessage('You are helpful'),
                new UserMessage('Hello'),
            ],
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('gpt-5-mini', $decoded['model']);
        $this->assertCount(2, $decoded['messages']);
        $this->assertEquals('system', $decoded['messages'][0]['role']);
        $this->assertEquals('You are helpful', $decoded['messages'][0]['content']);
        $this->assertEquals('user', $decoded['messages'][1]['role']);
        $this->assertEquals('Hello', $decoded['messages'][1]['content']);
    }

    public function testCompletionRequestWithAllParametersRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5',
            messages: [new UserMessage('Test')],
            temperature: 0.7,
            maxTokens: 100,
            maxCompletionTokens: 150,
            frequencyPenalty: 0.5,
            presencePenalty: -0.5,
            topP: 0.9,
            n: 2,
            stop: ['\n', 'STOP'],
            seed: 42,
            user: 'test_user',
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('gpt-5', $decoded['model']);
        $this->assertEquals(0.7, $decoded['temperature']);
        $this->assertEquals(100, $decoded['max_tokens']);
        $this->assertEquals(150, $decoded['max_completion_tokens']);
        $this->assertEquals(0.5, $decoded['frequency_penalty']);
        $this->assertEquals(-0.5, $decoded['presence_penalty']);
        $this->assertEquals(0.9, $decoded['top_p']);
        $this->assertEquals(2, $decoded['n']);
        $this->assertEquals(['\n', 'STOP'], $decoded['stop']);
        $this->assertEquals(42, $decoded['seed']);
        $this->assertEquals('test_user', $decoded['user']);
    }

    public function testCompletionRequestWithResponseFormatRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [new UserMessage('Test')],
            responseFormat: new ResponseFormat(
                type: ResponseFormatEnum::JSON,
            ),
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('json_object', $decoded['response_format']['type']);
    }

    public function testCompletionRequestWithToolChoiceRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [new UserMessage('Test')],
            tools: [RoundTripTool::class],
            toolChoice: ToolChoice::useTool(RoundTripTool::class),
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('function', $decoded['tool_choice']['type']);
        $this->assertEquals('round_trip_tool', $decoded['tool_choice']['function']['name']);
    }

    public function testCompletionRequestWithAutoToolChoiceRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [new UserMessage('Test')],
            tools: [RoundTripTool::class],
            toolChoice: new ToolChoice(type: ToolChoiceType::AUTO),
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('auto', $decoded['tool_choice']['type']);
    }

    public function testCompletionRequestWithToolsRoundTrip(): void
    {
        $original = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [new UserMessage('Test')],
            tools: [RoundTripTool::class],
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertCount(1, $decoded['tools']);
        $this->assertEquals('function', $decoded['tools'][0]['type']);
        $this->assertEquals('round_trip_tool', $decoded['tools'][0]['function']['name']);
        $this->assertArrayHasKey('parameters', $decoded['tools'][0]['function']);
    }

    // ========== Completion Response Round-trip Tests ==========

    public function testSimpleCompletionResponseRoundTrip(): void
    {
        $original = new CompletionResponse(
            id: 'resp_123',
            choices: [
                new Choice(
                    index: 0,
                    message: new AssistantMessage(content: 'Response content'),
                    finishReason: 'stop',
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 10, promptTokens: 5, totalTokens: 15),
            object: 'chat.completion',
            created: 1234567890,
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('resp_123', $decoded['id']);
        $this->assertEquals('gpt-5-mini', $decoded['model']);
        $this->assertEquals('chat.completion', $decoded['object']);
        $this->assertEquals(1234567890, $decoded['created']);
        $this->assertCount(1, $decoded['choices']);
        $this->assertEquals(0, $decoded['choices'][0]['index']);
        $this->assertEquals('Response content', $decoded['choices'][0]['message']['content']);
        $this->assertEquals('stop', $decoded['choices'][0]['finish_reason']);
    }

    public function testCompletionResponseWithToolCallsRoundTrip(): void
    {
        // Create message with tool calls using withToolCalls which handles validation
        $baseMessage = new AssistantMessage(content: 'I will use a tool');
        $messageWithTools = AssistantMessage::withToolCalls(
            $baseMessage,
            [
                new KnownFunctionCall(
                    id: 'call_123',
                    tool: RoundTripTool::class,
                    arguments: new RoundTripTool(message: 'Test'),
                ),
            ]
        );
        
        $original = new CompletionResponse(
            id: 'resp_tool',
            choices: [
                new Choice(
                    index: 0,
                    message: $messageWithTools,
                    finishReason: 'tool_calls',
                ),
            ],
            model: 'gpt-5-mini',
            usage: new Usage(completionTokens: 20, promptTokens: 30, totalTokens: 50),
            object: 'chat.completion',
            created: 1234567890,
        );
        
        $json = $this->serializer->serialize($original);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('tool_calls', $decoded['choices'][0]['finish_reason']);
        $this->assertEquals('I will use a tool', $decoded['choices'][0]['message']['content']);
        $this->assertCount(1, $decoded['choices'][0]['message']['tool_calls']);
        $this->assertEquals('call_123', $decoded['choices'][0]['message']['tool_calls'][0]['id']);
        $this->assertEquals('round_trip_tool', $decoded['choices'][0]['message']['tool_calls'][0]['function']['name']);
    }

    // ========== Full API Scenario Tests ==========

    public function testFullChatCompletionScenario(): void
    {
        // Simulate a full chat completion request and response cycle
        $request = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [
                new SystemMessage('You are a helpful math assistant.'),
                new UserMessage('What is 2 + 2?'),
            ],
            temperature: 0.7,
            maxTokens: 100,
        );
        
        $requestJson = $this->serializer->serialize($request);
        $requestDecoded = json_decode($requestJson, true);
        
        // Simulate API response
        $responseJson = json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '2 + 2 equals 4.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 7,
                'total_tokens' => 27,
            ],
        ]);
        
        $response = $this->serializer->deserialize($responseJson, CompletionResponse::class);
        
        $this->assertEquals('gpt-5-mini', $requestDecoded['model']);
        $this->assertEquals('gpt-5-mini', $response->model);
        $this->assertEquals('2 + 2 equals 4.', $response->choices[0]->message->content);
        $this->assertEquals(20, $response->usage->promptTokens);
        $this->assertEquals(7, $response->usage->completionTokens);
    }

    public function testFullToolCallingScenario(): void
    {
        // Simulate a tool calling scenario
        $request = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: [new UserMessage('Calculate something')],
            tools: [RoundTripTool::class],
            toolChoice: new ToolChoice(type: ToolChoiceType::AUTO),
        );
        
        $requestJson = $this->serializer->serialize($request);
        $requestDecoded = json_decode($requestJson, true);
        
        $this->assertEquals('auto', $requestDecoded['tool_choice']['type']);
        $this->assertCount(1, $requestDecoded['tools']);
        
        // Simulate tool call response
        $responseJson = json_encode([
            'id' => 'chatcmpl-tool',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-5-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_calc',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'round_trip_tool',
                                    'arguments' => '{"message": "Calculate", "count": 3}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 20,
                'total_tokens' => 35,
            ],
        ]);
        
        $response = $this->serializer->deserialize($responseJson, CompletionResponse::class);
        
        $this->assertEquals('tool_calls', $response->choices[0]->finishReason);
        $this->assertCount(1, $response->choices[0]->message->toolCalls);
        
        $toolCall = $response->choices[0]->message->toolCalls[0];
        $this->assertEquals('call_calc', $toolCall->id);
        $this->assertEquals('round_trip_tool', $toolCall->name);
        
        $args = json_decode($toolCall->arguments, true);
        $this->assertEquals('Calculate', $args['message']);
        $this->assertEquals(3, $args['count']);
    }

    public function testMultiTurnConversationScenario(): void
    {
        // Simulate a multi-turn conversation
        $conversation = [
            new SystemMessage('You are a helpful assistant.'),
            new UserMessage('Hello!'),
            new AssistantMessage(content: 'Hello! How can I help you today?'),
            new UserMessage('What is the weather?'),
        ];
        
        $request = new CompletionRequest(
            model: 'gpt-5-mini',
            messages: $conversation,
        );
        
        $json = $this->serializer->serialize($request);
        $decoded = json_decode($json, true);
        
        $this->assertCount(4, $decoded['messages']);
        $this->assertEquals('system', $decoded['messages'][0]['role']);
        $this->assertEquals('user', $decoded['messages'][1]['role']);
        $this->assertEquals('assistant', $decoded['messages'][2]['role']);
        $this->assertEquals('user', $decoded['messages'][3]['role']);
        $this->assertEquals('Hello!', $decoded['messages'][1]['content']);
        $this->assertEquals('Hello! How can I help you today?', $decoded['messages'][2]['content']);
    }
}
