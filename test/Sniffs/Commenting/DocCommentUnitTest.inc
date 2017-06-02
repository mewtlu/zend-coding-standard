<?php
/**
* @license wrong indents in docblocks.
 */

namespace MyApp\MyNamespace;

/** Class foo
 * @package MyApp\MyNamespace
 * Description of the class
   *   - OK
   * Indents
*/
class Foo
{
   /**
     *@param bool $x
     * @param int $y
     */
    protected function test1($x, $y)
    {
    }

    /**
    * @return string
    */
    protected function test2()
    {
        /**  @var int $x */
        $x = 10;

      /** @var string $y */
        $y = 'foo';

       /**
        * @var float $z
        */
        $z = 10.1;
    }

    /**
     * @return float*/
    protected function test3()
    {
        /**  */
        $var = 'empty docblock';

        /**
         *
         */
        $foo = 'empty';

        /** @var int $bar   */$bar = 1;

        /** @var int $baz */   $baz = 2;

        /**
         * @var int $a
         */  $a = 0;

        /**
         *
         *
         * @var float $b
         *
         */
        $b = 1.1;

        /**
         *   This is something.
         *
        * @var string $c
        */$c = 'xyz';

        $d = 'foo'; /**
     *
     */

        $e = 'bar'; /**
     * @var string $f
     */
        $f = 'baz';

        /**  @var int $g
         */
        $g = 11;

        /**
         *    @var int $h
         * */
        $h = 12;

        /** @var int $i */

        $i = 13;
        /** @var int $j */
        $j = 14;

        /** @var int $k
         */
        $k = 15;

        /** */
        /** * */
    }

    /**
     * This is function.
     *
     *
     *
     * @return void
     */
    public function test4()
    {
        /**
         * This is description of the variable.
         */
        $a = 1;

        /** This is another description */
        $b = 1;
    }
}