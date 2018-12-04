<?php

namespace Phloppy;

class RespUtilsTest extends \PHPUnit_Framework_TestCase {

    public function testReadString()
    {
        $mock = $this->getMock('\Phloppy\Stream\StreamInterface');
        $mock->expects($this->any())->method('readLine')->willReturn('+FOO');
        $this->assertEquals('FOO', RespUtils::deserialize($mock));
    }

    public function testReadInt()
    {
        $mock = $this->getMock('\Phloppy\Stream\StreamInterface');
        $mock->expects($this->any())->method('readLine')->willReturn(':42');
        $this->assertEquals(42, RespUtils::deserialize($mock));
    }

    /**
     * @expectedException \Phloppy\Exception\CommandException
     * @expectedExceptionMessage ERR Foo
     */
    public function testErrResponseThrowsCommandException()
    {
        $mock = $this->getMock('\Phloppy\Stream\StreamInterface');
        $mock->expects($this->any())->method('readLine')->willReturn("-ERR Foo");
        RespUtils::deserialize($mock);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage unhandled protocol response: /FOO
     */
    public function testInvalidReponseThrowsRuntimeException()
    {
        $mock = $this->getMock('\Phloppy\Stream\StreamInterface');
        $mock->expects($this->any())->method('readLine')->willReturn("/FOO");
        RespUtils::deserialize($mock);
    }

    public function testEmptyBulkStringReponse()
    {
        $mock = $this->getMock('\Phloppy\Stream\StreamInterface');
        $mock->expects($this->any())->method('readLine')->willReturn("$-1");
        $this->assertNull(RespUtils::deserialize($mock));
    }
}
