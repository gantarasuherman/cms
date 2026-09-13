<?php

/**
 * Language-model assistance for the chatbot.
 *
 * Off by default, and never load-bearing: every place the assistant is used
 * falls back to the deterministic behaviour it augments. A complaint line that
 * stops working because a model is rate-limited is worse than one with a
 * clunkier menu.
 *
 * Credentials here are fallbacks — the admin panel stores them encrypted and
 * takes precedence, the same way the channel keys work.
 */
return [

    'enabled' => (bool) env('AI_ENABLED', false),

    // Groq, OpenAI, Together, OpenRouter and a local Ollama all speak the same
    // /chat/completions shape, so one driver covers them by base URL rather
    // than a class per vendor.
    'driver' => env('AI_DRIVER', 'openai_compatible'),

    /**
     * The addresses and models an administrator chooses between.
     *
     * Kept here rather than typed into a form: an API address and a model name
     * are exact strings a person has no way to check, and one wrong character
     * produces a bot that silently stops helping. The panel asks only for the
     * key, which is the one thing the software cannot know.
     *
     * `needs_key` is false for a model running on the same server — there is
     * nobody to authenticate to.
     */
    'providers' => [
        'groq' => [
            'label' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'needs_key' => true,
            'docs' => 'https://console.groq.com/keys',
            'models' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B — paling mampu',
                'llama-3.1-8b-instant' => 'Llama 3.1 8B — paling cepat',
                'gemma2-9b-it' => 'Gemma 2 9B',
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'needs_key' => true,
            'docs' => 'https://platform.openai.com/api-keys',
            'models' => [
                'gpt-4o-mini' => 'GPT-4o mini — hemat',
                'gpt-4o' => 'GPT-4o — paling mampu',
            ],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'needs_key' => true,
            'docs' => 'https://openrouter.ai/keys',
            'models' => [
                'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
                'google/gemini-flash-1.5' => 'Gemini Flash 1.5',
            ],
        ],
        'ollama' => [
            'label' => 'Ollama — berjalan di server sendiri',
            'base_url' => env('AI_OLLAMA_URL', 'http://localhost:11434/v1'),
            'needs_key' => false,
            'docs' => 'https://ollama.com',
            'models' => [
                'llama3.1' => 'Llama 3.1',
                'qwen2.5' => 'Qwen 2.5',
            ],
        ],
    ],

    'provider' => env('AI_PROVIDER', 'groq'),
    'api_key' => env('AI_API_KEY'),

    // Escape hatches for a deployment using something not on the list above.
    // Not exposed in the panel: they are exact strings with no way for an
    // administrator to check them, and a typo produces a bot that quietly
    // stops helping.
    'base_url' => env('AI_BASE_URL'),
    'model' => env('AI_MODEL'),

    // Short on purpose. A person waiting in a chat notices three seconds; the
    // fallback costs them nothing and answers immediately.
    'timeout' => (int) env('AI_TIMEOUT', 8),

    // What the assistant is allowed to do. Each is independent so an
    // administrator can take one back without losing the others.
    'features' => [
        'intent' => (bool) env('AI_INTENT', true),
        'answers' => (bool) env('AI_ANSWERS', true),
    ],

    'max_input_chars' => 1200,

];
