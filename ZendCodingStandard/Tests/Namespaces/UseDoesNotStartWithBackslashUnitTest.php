<?php
namespace ZendCodingStandard\Tests\Namespaces;

use ZendCodingStandard\Tests\TestCase;

class UseDoesNotStartWithBackslashUnitTest extends TestCase
{
    public function getErrorList($testFile = '')
    {
        return [
            4 => 1,
            5 => 1,
            6 => 1,
        ];
    }

    public function getWarningList($testFile = '')
    {
        return [];
    }
}