<?php
/**
 * Shared helper for OpenAI compatible chat completion APIs
 */
abstract class Loco_api_OpenAiCompatible extends Loco_api_Client{


    /**
     * Translate batch of strings using an OpenAI compatible chat completion API
     * @param string[][] $items
     * @param Loco_Locale $locale
     * @param array $config
     * @return string[]
     * @throws Loco_error_Exception
     */
    public static function process( array $items, Loco_Locale $locale, array $config ):array {
        $targets = [];

        $model = static::resolveModel($config);
        $endpoint = static::resolveEndpoint($config);
        $apiKey = static::resolveApiKey($config);

        $sourceLang = 'English';
        $targetLang = static::wordyLanguage($locale);

        $tag = Loco_mvc_PostParams::get()['source'] ?? null;
        if( is_string($tag) && '' !== $tag ){
            $sourceLocale = Loco_Locale::parse($tag);
            if( $sourceLocale->isValid() ){
                $sourceLang = static::wordyLanguage($sourceLocale);
            }
        }

        Loco_data_CompiledData::flush();

        $prompt = static::buildPrompt($locale, $config);

        $messages = static::buildMessages($sourceLang, $targetLang, $prompt, $items, $config);

        $responseFormat = static::getResponseFormat($items);

        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => $messages,
            'response_format' => $responseFormat,
        ];
        $payload = static::tunePayload($payload, $config, $items, $locale);

        $timeout = static::resolveTimeout( $config, $items, $locale );

        $request = wp_remote_request( $endpoint, static::initRequestArguments( $apiKey, $config, $payload, $timeout ) );

        $data = static::decode_response($request);
        foreach( $data['choices'] as $choice ){
            $blob = $choice['message'] ?? ['role' => 'null'];
            if( isset($blob['refusal']) ){
                Loco_error_Debug::trace('Refusal: %s', $blob['refusal'] );
                continue;
            }
            if( 'assistant' !== $blob['role'] ){
                Loco_error_Debug::trace('Ignoring %s role message', $blob['role'] );
                continue;
            }
            $content = json_decode( trim($blob['content']), true );
            if( ! is_array($content) || ! array_key_exists('result',$content) ){
                Loco_error_Debug::trace("Content doesn't conform to our schema");
                continue;
            }
            $result = $content['result'];
            if( ! is_array($result) || count($result) !== count($items) ){
                Loco_error_Debug::trace("Result array doesn't match our input array");
                continue;
            }
            $i = -1;
            foreach( $result as $r ){
                $item = $items[++$i];
                $translation = $r['text'];
                $gptId = (int) $r['id'];
                $ourId = (int) $item['id'];
                if( $ourId !== $gptId ){
                    Loco_error_Debug::trace('Bad id field at [%u] expected %s, got %s', $i, $ourId, $gptId );
                    $translation = '';
                }
                $targets[$i] = $translation;
            }
        }

        return $targets;
    }


    protected static function getProviderName():string {
        return 'OpenAI Compatible';
    }


    protected static function getDefaultModel():string {
        return 'gpt-4o-mini';
    }


    protected static function getDefaultEndpoint():string {
        return 'https://api.openai.com/v1/chat/completions';
    }


    protected static function getPromptFilters():array {
        return ['loco_gpt_prompt'];
    }


    protected static function resolveApiKey( array $config ):string {
        $key = trim( (string) ( $config['key'] ?? '' ) );
        if( '' === $key ){
            throw new Loco_error_Exception( sprintf( __('Missing API key for %s','loco-translate'), static::getProviderName() ) );
        }
        return $key;
    }


    protected static function resolveModel( array $config ):string {
        $model = trim( (string) ( $config['model'] ?? '' ) );
        if( '' === $model ){
            $model = static::getDefaultModel();
        }
        if( '' === $model ){
            throw new Loco_error_Exception( sprintf( __('Missing model for %s','loco-translate'), static::getProviderName() ) );
        }
        return $model;
    }


    protected static function resolveEndpoint( array $config ):string {
        $endpoint = trim( (string) ( $config['endpoint'] ?? '' ) );
        if( '' === $endpoint ){
            $endpoint = static::getDefaultEndpoint();
        }
        if( '' === $endpoint ){
            throw new Loco_error_Exception( sprintf( __('Missing endpoint for %s','loco-translate'), static::getProviderName() ) );
        }
        return $endpoint;
    }


    protected static function buildPrompt( Loco_Locale $locale, array $config ):string {
        $prompt = 'Translate the `source` properties of the following JSON objects, using the `context` and `notes` properties to identify the meaning';
        $tone = $locale->getFormality();
        if( '' !== $tone ){
            $prompt .= '. Use the '.$tone.' style';
        }
        $custom = (string) ( $config['prompt'] ?? '' );
        $custom = static::applyPromptFilters($custom, $locale, $config);
        $custom = trim($custom);
        if( '' !== $custom ){
            $prompt .= '. '.$custom;
        }
        return $prompt;
    }


    protected static function applyPromptFilters( string $custom, Loco_Locale $locale, array $config ):string {
        foreach( static::getPromptFilters() as $hook ){
            if( '' === $hook ){
                continue;
            }
            $custom = apply_filters( $hook, $custom, $locale, $config );
        }
        return $custom;
    }


    protected static function buildMessages( string $sourceLang, string $targetLang, string $prompt, array $items, array $config ):array {
        $messages = [
            [ 'role' => 'system', 'content' => 'You are a helpful assistant that translates from '.$sourceLang.' to '.$targetLang ],
            [ 'role' => 'user', 'content' => rtrim($prompt,':.;, ').':' ],
            [ 'role' => 'user', 'content' => json_encode($items,JSON_UNESCAPED_UNICODE) ],
        ];
        return $messages;
    }


    protected static function getResponseFormat( array $items ):array {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'translations_array',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => [
                                        'type' => 'number',
                                        'description' => 'Corresponding id from the input object'
                                    ],
                                    'text' => [
                                        'type' => 'string',
                                        'description' => 'Translation text of the corresponding input object',
                                    ]
                                ],
                                'required' => ['id','text'],
                                'additionalProperties' => false,
                            ],
                            'description' => 'Translations of the corresponding input array',
                        ],
                    ],
                    'required' => ['result'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }


    /**
     * Allow subclasses to augment request payload (e.g. max_tokens overrides)
     * @param array $payload
     * @param array $config
     * @param array $items
     * @param Loco_Locale $locale
     * @return array
     */
    protected static function tunePayload( array $payload, array $config, array $items, Loco_Locale $locale ):array {
        $maxTokens = static::resolveMaxTokens( $config, $items, $locale );
        if( $maxTokens > 0 ){
            $payload['max_tokens'] = $maxTokens;
        }
        return $payload;
    }


    protected static function resolveTimeout( array $config, array $items, Loco_Locale $locale ):int {
        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : 20;
        if( $timeout < 10 ){
            $timeout = 10;
        }
        $timeout = (int) apply_filters( 'loco_openai_timeout', $timeout, $items, $locale, $config, static::getProviderName() );
        if( $timeout < 10 ){
            $timeout = 10;
        }
        return $timeout;
    }


    protected static function resolveMaxTokens( array $config, array $items, Loco_Locale $locale ):int {
        $maxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : 0;
        if( $maxTokens < 0 ){
            $maxTokens = 0;
        }
        $maxTokens = (int) apply_filters( 'loco_openai_max_tokens', $maxTokens, $items, $locale, $config, static::getProviderName() );
        if( $maxTokens < 0 ){
            $maxTokens = 0;
        }
        return $maxTokens;
    }


    protected static function initRequestArguments( string $apiKey, array $config, array $data, int $timeout ):array {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ];
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim((string) $_SERVER['HTTP_ORIGIN'],'/') : '';
        if( '' !== $origin ){
            $headers['Origin'] = $origin;
            $headers['Referer'] = $origin.'/wp-admin/';
        }
        if( isset($config['headers']) && is_array($config['headers']) ){
            $headers = array_merge($headers, $config['headers']);
        }
        return [
            'method' => 'POST',
            'redirection' => 0,
            'user-agent' => parent::getUserAgent(),
            'reject_unsafe_urls' => false,
            'headers' => $headers,
            'timeout' => $timeout,
            'body' => json_encode($data),
        ];
    }


    protected static function wordyLanguage( Loco_Locale $locale ):string {
        $names = Loco_data_CompiledData::get('languages');
        $name = $names[ $locale->lang ] ?? $locale->lang;
        $tone = $locale->getFormality();
        if( $tone ){
            $name = ucfirst($tone).' '.$name;
        }
        return $name;
    }


    protected static function decode_response( $result ):array {
        $data = parent::decodeResponse($result);
        $status = $result['response']['code'];
        if( 200 !== $status ){
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new Loco_error_Exception( sprintf('%s API returned status %u: %s', static::getProviderName(), $status, $message) );
        }
        if( ! array_key_exists('choices',$data) || ! is_array($data['choices']) ){
            throw new Loco_error_Exception(sprintf('%s API returned unexpected data', static::getProviderName()));
        }
        return $data;
    }

}


