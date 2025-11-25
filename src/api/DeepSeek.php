<?php
/**
 * DeepSeek chat completion implementation
 */
abstract class Loco_api_DeepSeek extends Loco_api_OpenAiCompatible{


    protected static function getProviderName():string {
        return 'DeepSeek';
    }


    protected static function getDefaultModel():string {
        return 'deepseek-chat';
    }


    protected static function getDefaultEndpoint():string {
        return 'https://api.deepseek.com/v1/chat/completions';
    }


    protected static function getPromptFilters():array {
        return ['loco_deepseek_prompt','loco_gpt_prompt'];
    }


    protected static function getResponseFormat( array $items ):array {
        return [
            'type' => 'json_object',
        ];
    }


    protected static function buildPrompt( Loco_Locale $locale, array $config ):string {
        $prompt = parent::buildPrompt($locale, $config);
        $prompt .= '. Output must be valid JSON matching {"result":[{"id":0,"text":"translation"}]} and contain only JSON';
        return $prompt;
    }


    protected static function tunePayload( array $payload, array $config, array $items, Loco_Locale $locale ):array {
        $maxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : 8192;
        if( $maxTokens > 0 ){
            $payload['max_tokens'] = $maxTokens;
        }
        return $payload;
    }

}


