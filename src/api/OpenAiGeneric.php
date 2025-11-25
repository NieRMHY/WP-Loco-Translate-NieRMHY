<?php
/**
 * Generic OpenAI compatible client with configurable endpoint
 */
abstract class Loco_api_OpenAiGeneric extends Loco_api_OpenAiCompatible{


    protected static function getProviderName():string {
        return 'OpenAI Compatible';
    }


    protected static function getDefaultModel():string {
        return '';
    }


    protected static function getDefaultEndpoint():string {
        return '';
    }


    protected static function getPromptFilters():array {
        return ['loco_openai_compat_prompt','loco_gpt_prompt'];
    }

}


