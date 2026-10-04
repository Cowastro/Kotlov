<?php

namespace Tests\Unit;

use App\Console\Commands\ApplyOfficialStoveTechnicalSpecsCommand;
use ReflectionMethod;
use Tests\TestCase;

class ApplyOfficialStoveTechnicalSpecsCommandTest extends TestCase
{
    public function test_spec_key_synonyms_are_normalized_to_one_canonical_field(): void
    {
        $command = new ApplyOfficialStoveTechnicalSpecsCommand;
        $normalize = new ReflectionMethod($command, 'normalizeKey');

        $this->assertSame('масса', $normalize->invoke($command, 'Вес, кг'));
        $this->assertSame('масса', $normalize->invoke($command, 'Масса'));
        $this->assertSame('габариты', $normalize->invoke($command, 'Размеры печи, мм'));
        $this->assertSame('габариты', $normalize->invoke($command, 'Габариты (Ш×Г×В)'));
        $this->assertSame('размерытопки', $normalize->invoke($command, 'Размеры топочной камеры'));
        $this->assertSame('размерытопки', $normalize->invoke($command, 'Размеры топки (Ш×Г×В), мм'));
        $this->assertSame('мощностьводяногоконтура', $normalize->invoke($command, 'Мощность, переданная воде'));
        $this->assertSame('объёмводяногоконтура', $normalize->invoke($command, 'Объем котла, л'));
        $this->assertSame('кпд', $normalize->invoke($command, 'Эффективность, %'));
        $this->assertSame('диаметрдымохода', $normalize->invoke($command, 'Диаметр дымоходного патрубка'));
        $this->assertSame('максимальнаядлинаполена', $normalize->invoke($command, 'Максимальная длина дров'));
        $this->assertSame('материал', $normalize->invoke($command, 'Материал корпуса'));
        $this->assertSame('материал', $normalize->invoke($command, 'Материал топки'));
        $this->assertSame('материал', $normalize->invoke($command, 'Облицовочный материал'));
        $this->assertSame('подключениедымохода', $normalize->invoke($command, 'Выход дымохода'));
        $this->assertSame('гарантия', $normalize->invoke($command, 'Гарантийный срок'));
    }

    public function test_verified_specs_replace_synonyms_but_preserve_unrelated_fields(): void
    {
        $command = new ApplyOfficialStoveTechnicalSpecsCommand;
        $merge = new ReflectionMethod($command, 'mergeSpecs');
        $verified = [
            ['key' => 'Габариты (Ш×Г×В)', 'value' => '495×440×965', 'unit' => 'мм'],
            ['key' => 'Масса', 'value' => '79', 'unit' => 'кг'],
        ];

        $result = $merge->invoke($command, [
            ['key' => 'Размеры печи', 'value' => 'старое значение', 'unit' => 'мм'],
            ['key' => 'Вес', 'value' => 'старое значение', 'unit' => 'кг'],
            ['key' => 'Материал', 'value' => 'Сталь', 'unit' => ''],
        ], $verified);

        $this->assertCount(3, $result);
        $this->assertSame('Материал', $result[0]['key']);
        $this->assertSame($verified, array_slice($result, 1));
    }
}
