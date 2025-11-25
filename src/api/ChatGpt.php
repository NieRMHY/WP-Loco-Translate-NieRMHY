<?php
/**
 * Bundled from external repo:
 * @see https://github.com/loco/wp-gpt-translator/
 */
abstract class Loco_api_ChatGpt extends Loco_api_OpenAiCompatible{


    protected static function getProviderName():string {
        return 'OpenAI';
    }


    protected static function getDefaultModel():string {
        return 'gpt-4o-mini';
    }


    protected static function getPromptFilters():array {
        return ['loco_gpt_prompt'];
    }

}
