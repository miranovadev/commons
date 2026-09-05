<?php

namespace App\Enums;

enum ConnectionProvider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
}
