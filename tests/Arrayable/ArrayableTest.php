<?php

namespace Arrayable;

use Pionia\Pionia\Collections\Arrayable;
use Pionia\Pionia\TestSuite\PioniaTestCase;

class ArrayableTest extends PioniaTestCase
{
    public ?Arrayable $arrayable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arrayable = new Arrayable();
    }

    protected function tearDown(): void
    {
        parent::tearDown(); // TODO: Change the autogenerated stub
        $this->arrayable = null;
    }

    public function testArrayableCreation()
    {
        $this->assertNotNull($this->arrayable);
    }

    public function testArrayableIsArray()
    {
        $this->assertIsArray($this->arrayable->toArray());
    }

    public function testMerging()
    {
        $this->arrayable->merge(['first' => 'John Doe']);
        self::assertTrue($this->arrayable->has('first'));
    }

    public function testArrayableIsCountable()
    {
        $this->assertIsInt($this->arrayable->size());
    }

    public function testArrayableIsIterable()
    {
        $this->assertIsIterable($this->arrayable->toArray());
    }

    public function testArrayableIsJsonSerializable()
    {
        $this->assertIsString($this->arrayable->toJson());
    }

    public function testArrayableIsStringable()
    {
        $this->assertIsString($this->arrayable->__toString());
    }

    public function testArrayableFirst()
    {
        $this->arrayable->add('first', 'John Doe');
        $first = $this->arrayable->first();
        self::assertEquals('John Doe', $first);
    }

    public function testArrayableLast()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $last = $this->arrayable->last();
        self::assertEquals('Jane Doe', $last);
    }

    public function testArrayableSlice()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->add('middle', 'Doe');
        $this->arrayable->slice(1, 1);
        self::assertCount(1, $this->arrayable->toArray());
    }

    public function testArrayableChunk()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->add('middle', 'Doe');
        $this->arrayable->chunk(2);
        self::assertCount(2, $this->arrayable->all());
    }

    public function testArrayableKeysToLowerCase()
    {
        $this->arrayable->add('FIRST', 'John Doe');
        $this->arrayable->add('LAST', 'Jane Doe');
        $this->arrayable->add('MIDDLE', 'Doe');
        $this->arrayable->keysToLowerCase();
        self::assertArrayNotHasKey('FIRST', $this->arrayable->all());
    }

    public function testArrayableKeysToUpperCase()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->add('middle', 'Doe');
        $this->arrayable->keysToUpperCase();
        self::assertArrayNotHasKey('first', $this->arrayable->all());
    }

    public function testArrayableValuesToLowerCase()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->add('middle', 'Doe');
        $this->arrayable->valuesToLowerCase();
        self::assertContains('john doe', $this->arrayable->all());
    }

    public function testArrayableReverse()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->add('middle', 'Doe');
        $this->arrayable->reverse();
        self::assertEquals('Doe', $this->arrayable->first());
    }

    public function testArrayableIsEmpty()
    {
        self::assertTrue($this->arrayable->isEmpty());
    }

    public function testArrayableIsNotEmpty()
    {
        $this->arrayable->add('first', 'John Doe');
        self::assertFalse($this->arrayable->isEmpty());
    }

    public function testArrayableHas()
    {
        $this->arrayable->add('first', 'John Doe');
        self::assertTrue($this->arrayable->has('first'));
    }

    public function testArrayableRemove()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->remove('first');
        self::assertFalse($this->arrayable->has('first'));
    }

    public function testArrayableFlush()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->flush();
        self::assertTrue($this->arrayable->isEmpty());
    }

    public function testArrayableGet()
    {
        $this->arrayable->add('first', 'John Doe');
        self::assertEquals('John Doe', $this->arrayable->get('first'));
    }

    public function testArrayAddBefore()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->addBefore('last', 'middle', 'Doe');
        self::assertEquals('Doe', $this->arrayable->at(1));
    }

    public function testArrayAddAfter()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $this->arrayable->addAfter('first', 'middle', 'Doe');
        self::assertEquals('Doe', $this->arrayable->at(1));
    }

    public function testArrayShift()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $first = $this->arrayable->shift();
        self::assertEquals('John Doe', $first);
    }

    public function testArrayUnshift()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->unshift(['new_first' => 'Doe']);
        self::assertEquals('Doe', $this->arrayable->first());
    }

    public function testArrayPop()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->add('last', 'Jane Doe');
        $last = $this->arrayable->pop();
        self::assertEquals('Jane Doe', $last);
    }

    public function testArrayReplace()
    {
        $this->arrayable->add('first', 'John Doe');
        $this->arrayable->replace('first', 'Jane Doe');
        self::assertEquals('Jane Doe', $this->arrayable->get('first'));
    }

    public function testArrayableToArrayable()
    {
        $arrayable = Arrayable::toArrayable(['first' => 'John Doe']);
        self::assertInstanceOf(Arrayable::class, $arrayable);
    }

    public function testArrayableIsFilled()
    {
        $this->arrayable->add('first', 'John Doe');
        self::assertTrue($this->arrayable->isFilled());
    }

    public function testArrayableOnlyByArray()
    {
        $this->arrayable->merge(['first' => 'John', 'last' => 'Jane', 'middle' => 'Doe', 'age' => 20]);
        $only = $this->arrayable->only(['first', 'last']);
        self::assertArrayHasKey('first', $only->toArray());
    }

    public function testArrayableOnlyByString()
    {
        $this->arrayable->merge(['first' => 'John', 'last' => 'Jane', 'middle' => 'Doe', 'age' => 20]);
        $only = $this->arrayable->only('first', 'last');
        self::assertArrayHasKey('first', $only->toArray());
    }
    public function testArrayableOnlyByNullValues()
    {
        $this->arrayable->merge(['first' => 'John', 'last' => 'Jane', 'middle' => 'Doe', 'age' => 20]);
        $only = $this->arrayable->only();
        self::assertArrayHasKey('age', $only->toArray());
    }

    public function testArrWhere()
    {
        $this->arrayable->merge([
            [
                'first' => 'John',
                'last' => 'Doe',
                'age' => 22
            ],
            [
                'first' => 'Kate',
                'last' => 'Middleton',
                'age' => 30
            ],
            [
                'first' => 'Jane',
                'last' => 'Doe',
                'age' => 20
            ]
        ]);

        $where = $this->arrayable->where(function ($item) {
            return $item['age'] > 20;
        });
        self::assertNotContains([
            'first' => 'Jane',
            'last' => 'Doe',
            'age' => 20
        ], $where->toArray());
    }

    public function testArrMapWithKeys()
    {
        $this->arrayable->merge([
            [
                'first' => 'John',
                'last' => 'Doe',
                'age' => 22
            ],
            [
                'first' => 'Kate',
                'last' => 'Middleton',
                'age' => 30
            ],
            [
                'first' => 'Jane',
                'last' => 'Doe',
                'age' => 20
            ]
        ]);

        $map = $this->arrayable->mapWithKeys(function ($item) {
            return [$item['first'] => $item['age']];
        });
        self::assertArrayHasKey('John', $map->toArray());
    }


    public function testGetOrThrow()
    {
        $this->arrayable->merge([
            'first' => 'John',
            'last' => 'Doe',
            'age' => 22
        ]);

        $this->expectExceptionMessage('Key not found');
        $this->arrayable->getOrThrow('new', new \Exception('Key not found'));
    }

    public function testGetJson()
    {
        $this->arrayable->merge([
            'first' => 'John',
            'last' => 'Doe',
            'age' => 22
        ]);
        $json = $this->arrayable->getJson('age');
        self::assertIsString($json);
    }
}
