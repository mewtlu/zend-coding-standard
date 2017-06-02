<?php
namespace ZendCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\PEAR\Sniffs\Commenting\FunctionCommentSniff as PEARFunctionCommentSniff;
use ZendCodingStandard\CodingStandard;

class FunctionCommentSniff extends PEARFunctionCommentSniff
{
    /**
     * The current PHP version.
     *
     * @var int
     */
    private $phpVersion;

    /**
     * If function comment contains @inheritDoc tag.
     *
     * @var int[]
     */
    private $hasInheritDoc = [];

    /**
     * Process the return comment of this function comment.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                      in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     * @return void
     */
    protected function processReturn(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip constructor and destructor.
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        $isSpecialMethod = $methodName === '__construct' || $methodName === '__destruct';

        $return = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                if ($return !== null) {
                    $error = 'Only 1 @return tag is allowed in a function comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateReturn');
                    return;
                }

                $return = $tag;
            }
        }

        if ($isSpecialMethod === true) {
            return;
        }

        if ($return !== null) {
            $content = $tokens[$return + 2]['content'];
            if (empty($content) || $tokens[$return + 2]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Return type missing for @return tag in function comment';
                $phpcsFile->addError($error, $return, 'MissingReturnType');
            } else {
                // Check return type (can be multiple, separated by '|').
                $typeNames = explode('|', $content);
                $suggestedNames = [];
                foreach ($typeNames as $i => $typeName) {
                    $suggestedName = CodingStandard::suggestType($typeName);
                    if (! in_array($suggestedName, $suggestedNames)) {
                        $suggestedNames[] = $suggestedName;
                    }
                }

                $suggestedType = implode('|', $suggestedNames);
                if ($content !== $suggestedType) {
                    $error = 'Expected "%s" but found "%s" for function return type';
                    $data = [
                        $suggestedType,
                        $content,
                    ];
                    $fix = $phpcsFile->addFixableError($error, $return, 'InvalidReturn', $data);

                    if ($fix) {
                        $phpcsFile->fixer->replaceToken($return + 2, $suggestedType);
                    }
                }

                // Support both a return type and a description. The return type
                // is anything up to the first space.
                $returnParts = explode(' ', $content, 2);
                $returnType = $returnParts[0];

                // If the return type is void, make sure there is
                // no return statement in the function.
                if ($returnType === 'void') {
                    if (isset($tokens[$stackPtr]['scope_closer'])) {
                        $endToken = $tokens[$stackPtr]['scope_closer'];
                        for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
                            if ($tokens[$returnToken]['code'] === T_CLOSURE) {
                                $returnToken = $tokens[$returnToken]['scope_closer'];
                                continue;
                            }

                            if ($tokens[$returnToken]['code'] === T_RETURN
                                || $tokens[$returnToken]['code'] === T_YIELD
                            ) {
                                break;
                            }
                        }

                        if ($returnToken !== $endToken) {
                            // If the function is not returning anything, just
                            // exiting, then there is no problem.
                            $semicolon = $phpcsFile->findNext(T_WHITESPACE, $returnToken + 1, null, true);
                            if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
                                $error = 'Function return type is void, but function contains return statement';
                                $phpcsFile->addError($error, $return, 'InvalidReturnVoid');
                            }
                        }
                    }
                } elseif ($returnType !== 'mixed') {
                    $returnTypes = explode('|', $returnType);

                    // If return type is not void, there needs to be a return statement
                    // somewhere in the function that returns something.

                    // If "void" is not also in one of the return types.
                    if (! in_array('void', $returnTypes)
                        && isset($tokens[$stackPtr]['scope_closer'])
                    ) {
                        $endToken = $tokens[$stackPtr]['scope_closer'];
                        $returnToken = $phpcsFile->findNext([T_RETURN, T_YIELD], $stackPtr, $endToken);
                        if ($returnToken === false) {
                            $error = 'Function return type is not void, but function has no return statement';
                            $phpcsFile->addError($error, $return, 'InvalidNoReturn');
                        } else {
                            $semicolon = $phpcsFile->findNext(T_WHITESPACE, $returnToken + 1, null, true);
                            if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
                                $error = 'Function return type is not void, but function is returning void here';
                                $phpcsFile->addError($error, $returnToken, 'InvalidReturnNotVoid');
                            }
                        }
                    }
                }
            }
        } elseif (! $this->hasInheritDocTag($phpcsFile, $stackPtr, $commentStart)) {
            $error = 'Missing @return tag in function comment';
            $phpcsFile->addError($error, $tokens[$commentStart]['comment_closer'], 'MissingReturn');
        }
    }

    /**
     * Process any throw tags that this function comment has.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                      in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     * @return void
     */
    protected function processThrows(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $throws = [];
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }

            $exception = null;
            $comment = null;
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[$tag + 2]['content'], $matches);
                $exception = $matches[1];
                if (isset($matches[2]) && trim($matches[2]) !== '') {
                    $comment = $matches[2];
                }
            }

            if ($exception === null) {
                $error = 'Exception type and comment missing for @throws tag in function comment';
                $phpcsFile->addError($error, $tag, 'InvalidThrows');
            } elseif ($comment === null) {
                $error = 'Comment missing for @throws tag in function comment';
                $phpcsFile->addError($error, $tag, 'EmptyThrows');
            } else {
                // Any strings until the next tag belong to this comment.
                if (isset($tokens[$commentStart]['comment_tags'][$pos + 1])) {
                    $end = $tokens[$commentStart]['comment_tags'][$pos + 1];
                } else {
                    $end = $tokens[$commentStart]['comment_closer'];
                }

                for ($i = $tag + 3; $i < $end; $i++) {
                    if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                        $comment .= ' ' . $tokens[$i]['content'];
                    }
                }

                // Starts with a capital letter and ends with a fullstop.
                $firstChar = $comment{0};
                if (strtoupper($firstChar) !== $firstChar) {
                    $error = '@throws tag comment must start with a capital letter';
                    $phpcsFile->addError($error, $tag + 2, 'ThrowsNotCapital');
                }

                $lastChar = substr($comment, -1);
                if ($lastChar !== '.') {
                    $error = '@throws tag comment must end with a full stop';
                    $phpcsFile->addError($error, $tag + 2, 'ThrowsNoFullStop');
                }
            }
        }
    }

    /**
     * Checks if function comment contains @inheritDoc tag.
     * Methods should run only once.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                      in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     * @return bool
     */
    protected function hasInheritDocTag(File $phpcsFile, $stackPtr, $commentStart)
    {
        if (isset($this->hasInheritDoc[$stackPtr])) {
            return $this->hasInheritDoc[$stackPtr];
        }

        $tokens = $phpcsFile->getTokens();

        $commentEnd = $tokens[$commentStart]['comment_closer'];
        $commentContent = $phpcsFile->getTokensAsString($commentStart + 1, $commentEnd - $commentStart - 1);

        if (preg_match('/\*.*\{(@inheritDoc)\}/i', $commentContent, $m)) {
            if ($m[1] !== '@inheritDoc') {
                $data = ['@inheritDoc', $m[1]];
                $error = 'Expected {%s}, found {%s}.';
                $fix = $phpcsFile->addFixableError($error, $commentStart, 'InheritDocCase', $data);

                if ($fix) {
                    $newCommentContent = str_replace('{' . $m[1] . '}', '{@inheritDoc}', $commentContent);
                    $phpcsFile->fixer->beginChangeset();
                    for ($i = $commentStart + 1; $i < $commentEnd; ++$i) {
                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                    $phpcsFile->fixer->addContent($commentStart + 1, $newCommentContent);
                    $phpcsFile->fixer->endChangeset();
                }
            }

            $this->hasInheritDoc[$stackPtr] = true;
            return $this->hasInheritDoc[$stackPtr];
        }

        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if (strtolower($tokens[$tag]['content']) === '@inheritdoc') {
                if ($tokens[$tag]['content'] !== '@inheritDoc') {
                    $data = ['@inheritDoc', $tokens[$tag]['content']];
                    $error = 'Expected %s, found %s.';
                    $fix = $phpcsFile->addFixableError($error, $tag, 'InheritDocCase', $data);

                    if ($fix) {
                        $phpcsFile->fixer->replaceToken($tag, '@inheritDoc');
                    }
                }

                $this->hasInheritDoc[$stackPtr] = true;
                return $this->hasInheritDoc[$stackPtr];
            }
        }

        $this->hasInheritDoc[$stackPtr] = false;
        return $this->hasInheritDoc[$stackPtr];
    }

    /**
     * Process the function parameter comments.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                      in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     * @return void
     */
    protected function processParams(File $phpcsFile, $stackPtr, $commentStart)
    {
        if (! $this->phpVersion) {
            $this->phpVersion = Config::getConfigData('php_version');
            if (! $this->phpVersion) {
                $this->phpVersion = PHP_VERSION_ID;
            }
        }

        $tokens = $phpcsFile->getTokens();

        $params = [];
        $maxType = 0;
        $maxVar = 0;
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@param') {
                continue;
            }

            $type = '';
            $typeSpace = 0;
            $var = '';
            $varSpace = 0;
            $comment = '';
            $commentLines = [];
            if ($tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING) {
                $matches = [];
                preg_match(
                    '/([^$&.]+)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/',
                    $tokens[$tag + 2]['content'],
                    $matches
                );

                if (! empty($matches)) {
                    $typeLen = strlen($matches[1]);
                    $type = trim($matches[1]);
                    $typeSpace = $typeLen - strlen($type);
                    $typeLen = strlen($type);
                    if ($typeLen > $maxType) {
                        $maxType = $typeLen;
                    }
                }

                if (isset($matches[2])) {
                    $var = $matches[2];
                    $varLen = strlen($var);
                    if ($varLen > $maxVar) {
                        $maxVar = $varLen;
                    }

                    if (isset($matches[4])) {
                        $varSpace = strlen($matches[3]);
                        $comment = $matches[4];
                        $commentLines[] = [
                            'comment' => $comment,
                            'token' => $tag + 2,
                            'indent' => $varSpace,
                        ];

                        // Any strings until the next tag belong to this comment.
                        if (isset($tokens[$commentStart]['comment_tags'][$pos + 1])) {
                            $end = $tokens[$commentStart]['comment_tags'][$pos + 1];
                        } else {
                            $end = $tokens[$commentStart]['comment_closer'];
                        }

                        for ($i = $tag + 3; $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                                $indent = 0;
                                if ($tokens[$i - 1]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                    $indent = strlen($tokens[$i - 1]['content']);
                                }

                                $comment .= ' ' . $tokens[$i]['content'];
                                $commentLines[] = [
                                    'comment' => $tokens[$i]['content'],
                                    'token' => $i,
                                    'indent' => $indent,
                                ];
                            }
                        }
                    } else {
                        $error = 'Missing parameter comment';
                        $phpcsFile->addError($error, $tag, 'MissingParamComment');
                        $commentLines[] = ['comment' => ''];
                    }
                } else {
                    $error = 'Missing parameter name';
                    $phpcsFile->addError($error, $tag, 'MissingParamName');
                }
            } else {
                $error = 'Missing parameter type';
                $phpcsFile->addError($error, $tag, 'MissingParamType');
            }

            $params[] = [
                'tag' => $tag,
                'type' => $type,
                'var' => $var,
                'comment' => $comment,
                'commentLines' => $commentLines,
                'type_space' => $typeSpace,
                'var_space' => $varSpace,
            ];
        }

        $realParams = $phpcsFile->getMethodParameters($stackPtr);
        $foundParams = [];

        // We want to use ... for all variable length arguments, so added
        // this prefix to the variable name so comparisons are easier.
        foreach ($realParams as $pos => $param) {
            if ($param['variable_length'] === true) {
                $realParams[$pos]['name'] = '...' . $realParams[$pos]['name'];
            }
        }

        foreach ($params as $pos => $param) {
            // If the type is empty, the whole line is empty.
            if ($param['type'] === '') {
                continue;
            }

            // Check the param type value.
            $typeNames = explode('|', $param['type']);
            foreach ($typeNames as $typeName) {
                $suggestedName = CodingStandard::suggestType($typeName);
                if ($typeName !== $suggestedName) {
                    $error = 'Expected "%s" but found "%s" for parameter type';
                    $data = [
                        $suggestedName,
                        $typeName,
                    ];
                    $fix = $phpcsFile->addFixableError($error, $param['tag'], 'IncorrectParamVarName', $data);

                    if ($fix) {
                        $content = $suggestedName;
                        $content .= str_repeat(' ', $param['type_space']);
                        $content .= $param['var'];
                        $content .= str_repeat(' ', $param['var_space']);
                        if (isset($param['commentLines'][0])) {
                            $content .= $param['commentLines'][0]['comment'];
                        }

                        $phpcsFile->fixer->replaceToken($param['tag'] + 2, $content);
                    }
                } elseif (count($typeNames) === 1) {
                    // Check type hint for array and custom type.
                    $suggestedTypeHint = '';
                    if (strpos($suggestedName, 'array') !== false || substr($suggestedName, -2) === '[]') {
                        $suggestedTypeHint = 'array';
                    } elseif (strpos($suggestedName, 'callable') !== false) {
                        $suggestedTypeHint = 'callable';
                    } elseif (strpos($suggestedName, 'callback') !== false) {
                        $suggestedTypeHint = 'callable';
                    } elseif (! in_array($typeName, CodingStandard::$allowedTypes)) {
                        $suggestedTypeHint = $suggestedName;
                    } elseif ($this->phpVersion >= 70000) {
                        if ($typeName === 'string') {
                            $suggestedTypeHint = 'string';
                        } elseif ($typeName === 'int' || $typeName === 'integer') {
                            $suggestedTypeHint = 'int';
                        } elseif ($typeName === 'float') {
                            $suggestedTypeHint = 'float';
                        } elseif ($typeName === 'bool' || $typeName === 'boolean') {
                            $suggestedTypeHint = 'bool';
                        }
                    }

                    if ($suggestedTypeHint !== '' && isset($realParams[$pos])) {
                        $typeHint = $realParams[$pos]['type_hint'];
                        if ($typeHint === '') {
                            $error = 'Type hint "%s" missing for %s';
                            $data = [
                                $suggestedTypeHint,
                                $param['var'],
                            ];

                            $errorCode = 'TypeHintMissing';
                            if ($suggestedTypeHint === 'string'
                                || $suggestedTypeHint === 'int'
                                || $suggestedTypeHint === 'float'
                                || $suggestedTypeHint === 'bool'
                            ) {
                                $errorCode = 'Scalar' . $errorCode;
                            }

                            $phpcsFile->addError($error, $stackPtr, $errorCode, $data);
                        } elseif ($typeHint !== substr($suggestedTypeHint, (strlen($typeHint) * -1))) {
                            $error = 'Expected type hint "%s"; found "%s" for %s';
                            $data = [
                                $suggestedTypeHint,
                                $typeHint,
                                $param['var'],
                            ];
                            $phpcsFile->addError($error, $stackPtr, 'IncorrectTypeHint', $data);
                        }
                    } elseif ($suggestedTypeHint === '' && isset($realParams[$pos])) {
                        $typeHint = $realParams[$pos]['type_hint'];
                        if ($typeHint !== '') {
                            $error = 'Unknown type hint "%s" found for %s';
                            $data = [
                                $typeHint,
                                $param['var'],
                            ];
                            $phpcsFile->addError($error, $stackPtr, 'InvalidTypeHint', $data);
                        }
                    }
                }
            }

            if ($param['var'] === '') {
                continue;
            }

            $foundParams[] = $param['var'];

            // Check number of spaces after the type.
            $spaces = $maxType - strlen($param['type']) + 1;
            if ($param['type_space'] !== $spaces) {
                $error = 'Expected %s spaces after parameter type; %s found';
                $data = [
                    $spaces,
                    $param['type_space'],
                ];
                $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamType', $data);

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();

                    $content = $param['type'];
                    $content .= str_repeat(' ', $spaces);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $param['var_space']);
                    $content .= $param['commentLines'][0]['comment'];
                    $phpcsFile->fixer->replaceToken($param['tag'] + 2, $content);

                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $lineNum => $line) {
                        if ($lineNum === 0
                            || $param['commentLines'][$lineNum]['indent'] === 0
                        ) {
                            continue;
                        }

                        $newIndent = ($param['commentLines'][$lineNum]['indent'] + $spaces - $param['type_space']);
                        $phpcsFile->fixer->replaceToken(
                            $param['commentLines'][$lineNum]['token'] - 1,
                            str_repeat(' ', $newIndent)
                        );
                    }

                    $phpcsFile->fixer->endChangeset();
                }
            }

            // Make sure the param name is correct.
            if (isset($realParams[$pos])) {
                $realName = $realParams[$pos]['name'];
                if ($realName !== $param['var']) {
                    $code = 'ParamNameNoMatch';
                    $data = [
                        $param['var'],
                        $realName,
                    ];

                    $error = 'Doc comment for parameter %s does not match ';
                    if (strtolower($param['var']) === strtolower($realName)) {
                        $error .= 'case of ';
                        $code = 'ParamNameNoCaseMatch';
                    }

                    $error .= 'actual variable name %s';

                    $phpcsFile->addError($error, $param['tag'], $code, $data);
                }
            } elseif (substr($param['var'], -4) !== ',...') {
                // We must have an extra parameter comment.
                $error = 'Superfluous parameter comment';
                $phpcsFile->addError($error, $param['tag'], 'ExtraParamComment');
            }

            if ($param['comment'] === '') {
                continue;
            }

            // Check number of spaces after the var name.
            $spaces = $maxVar - strlen($param['var']) + 1;
            if ($param['var_space'] !== $spaces) {
                $error = 'Expected %s spaces after parameter name; %s found';
                $data = [
                    $spaces,
                    $param['var_space'],
                ];
                $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamName', $data);

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();

                    $content = $param['type'];
                    $content .= str_repeat(' ', $param['type_space']);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $spaces);
                    $content .= $param['commentLines'][0]['comment'];
                    $phpcsFile->fixer->replaceToken($param['tag'] + 2, $content);

                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $lineNum => $line) {
                        if ($lineNum === 0
                            || $param['commentLines'][$lineNum]['indent'] === 0
                        ) {
                            continue;
                        }

                        $newIndent = ($param['commentLines'][$lineNum]['indent'] + $spaces - $param['var_space']);
                        $phpcsFile->fixer->replaceToken(
                            $param['commentLines'][$lineNum]['token'] - 1,
                            str_repeat(' ', $newIndent)
                        );
                    }

                    $phpcsFile->fixer->endChangeset();
                }
            }

            // Param comments must start with a capital letter and end with the full stop.
            if (preg_match('/^(\p{Ll}|\P{L})/u', $param['comment']) === 1) {
                $error = 'Parameter comment must start with a capital letter';
                $phpcsFile->addError($error, $param['tag'], 'ParamCommentNotCapital');
            }

            $lastChar = substr($param['comment'], -1);
            if ($lastChar !== '.') {
                $error = 'Parameter comment must end with a full stop';
                $phpcsFile->addError($error, $param['tag'], 'ParamCommentFullStop');
            }
        }

        if ($this->hasInheritDocTag($phpcsFile, $stackPtr, $commentStart)) {
            return;
        }

        $realNames = [];
        foreach ($realParams as $realParam) {
            $realNames[] = $realParam['name'];
        }

        // Report missing comments.
        $diff = array_diff($realNames, $foundParams);
        foreach ($diff as $neededParam) {
            $error = 'Doc comment for parameter "%s" missing';
            $data = [$neededParam];
            $phpcsFile->addError($error, $commentStart, 'MissingParamTag', $data);
        }
    }
}