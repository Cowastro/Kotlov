<?php

namespace Tests\Unit;

use App\Console\Commands\LinkOneCByParametersCommand;
use PHPUnit\Framework\TestCase;

class LinkOneCByParametersCommandTest extends TestCase
{
    public function test_names_of_the_same_element_share_a_signature_and_other_sizes_do_not(): void
    {
        $sig = fn (string $name) => LinkOneCByParametersCommand::signature($name);

        $this->assertSame($sig('Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250 с хомутом'), $sig('Труба термо L-1000 ТТ-Р 304-0,8/304 D150/250 с хомутом'));
        $this->assertNotSame($sig('Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250 с хомутом'), $sig('Труба Термо L-1000 ТТ-Р 304-0.8/304 D120/200 с хомутом'));
        $this->assertNotSame($sig('Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250 с хомутом'), $sig('Труба Термо L-1000 ТТ-Р 304-0.8/304 D150/250'));
        $this->assertNotSame($sig('Труба L1000 ТМ-Р 430-0.5 D150'), $sig('Труба L1000 ТМ-Р 430-0.8 D150'));
        $this->assertSame('', $sig('Каменка Емеля'));
    }
}
