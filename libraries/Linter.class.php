<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Analyzes a query and gives user feedback.
 *
 * @package PhpMyAdmin
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

/**
 * The linter itself.
 *
 * @package PhpMyAdmin
 */
class PMA_Linter
{

    /**
     * Gets the starting position of each line.
     *
     * @param string $str String to be analyzed.
     *
     * @return array
     */
    public static function getLines($str)
    {
        $lines = array(0);

        // The reason for using the '8bit' parameter is that the length
        // required is the length in bytes, not characters.
        //
        // Given the following string: `????+`, where `?` represents a
        // multi-byte character (lets assume that every `?` is a 2-byte
        // character) and `+` is a newline, the first value of `$i` is `0` and
        // the last one is `4` (because there are 5 characters). Bytes `$str[0]`
        // and `$str[1]` are the first character, `$str[2]` and `$str[3]` are
        // the second one and `$str[4]` is going to be the first byte of the
        // third character. The fourth and the last one (which is actually a new
        // line) aren't going to be processed at all.
        for ($i = 0, $len = /*overload*/mb_strlen($str, '8bit'); $i < $len; ++$i) {
            if ($str[$i] === "\n") {
                $lines[] = $i + 1;
            }
        }
        return $lines;
    }

    /**
     * Computes the number of the line and column given an absolute position.
     *
     * @param array $lines The starting position of each line.
     * @param int   $pos   The absolute position
     *
     * @return void
     */
    public static function findLineNumberAndColumn($lines, $pos)
    {
        $line = 0;
        foreach ($lines as $lineNo => $lineStart) {
            if ($lineStart > $pos) {
                break;
            }
            $line = $lineNo;
        }
        return array($line, $pos - $lines[$line]);
    }

    /**
     * Runs the linting process.
     *
     * @param string $query The query to be checked.
     *
     * @return void
     */
    public static function lint($query)
    {
        // Disabling lint for huge queries to save some resources.
        if (/*overload*/mb_strlen($query) > 10000) {
            return array(
                array(
                    'message' => __('Linting is disabled for this query because it exceeds the maximum length.'),
                    'fromLine' => 0,
                    'fromColumn' => 0,
                    'toLine' => 0,
                    'toColumn' => 0,
                    'severity' => 'warning',
                )
            );
        }

        /**
         * Lexer used for tokenizing the query.
         *
         * @var SqlParser\Lexer
         */
        $lexer = new SqlParser\Lexer($query);

        /**
         * Parsed used for analysing the query.
         *
         * @var SqlParser\Parser
         */
        $parser = new SqlParser\Parser($lexer->list);

        /**
         * Array containing all errors.
         *
         * @var array
         */
        $errors = SqlParser\Utils\Error::get(array($lexer, $parser));

        /**
         * The response containing of all errors.
         *
         * @var array
         */
        $response = array();

        /**
         * The starting position for each line.
         *
         * CodeMirror requires relative position to line, but the parser stores
         * only the absolute position of the character in string.
         *
         * @var array
         */
        $lines = static::getLines($query);

        // Building the response.
        foreach ($errors as $idx => $error) {

            // Starting position of the string that caused the error.
            list($fromLine, $fromColumn) = static::findLineNumberAndColumn(
                $lines, $error[3]
            );

            // Ending position of the string that caused the error.
            list($toLine, $toColumn) = static::findLineNumberAndColumn(
                $lines, $error[3] + /*overload*/mb_strlen($error[2])
            );

            // Building the response.
            $response[] = array(
                'message' => sprintf(
                    __('%1$s (near <code>%2$s</code>)'),
                    $error[0], $error[2]
                ),
                'fromLine' => $fromLine,
                'fromColumn' => $fromColumn,
                'toLine' => $toLine,
                'toColumn' => $toColumn,
                'severity' => 'error',
            );
        }

        // Sending back the answer.
        return $response;
    }

}
