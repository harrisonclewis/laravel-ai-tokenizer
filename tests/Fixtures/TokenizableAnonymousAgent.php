<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Tests\Fixtures;

use Harlew\Ai\Tokenizer\Concerns\Tokenizable;
use Harlew\Ai\Tokenizer\Contracts\HasTokenization;
use Laravel\Ai\AnonymousAgent;

class TokenizableAnonymousAgent extends AnonymousAgent implements HasTokenization
{
    use Tokenizable;
}

