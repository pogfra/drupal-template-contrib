<?php

declare(strict_types=1);

namespace Local\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids blank lines inside an assignment, an array or a condition.
 *
 * Errors:
 * - AfterOperator: blank line after "=", "+=", "=>"...
 * - ArrayOpenerOnNewLine: array assigned with its opener on the next line.
 * - AfterArrayOpener: blank line after "[" or "array(".
 * - BeforeArrayCloser: blank line before the closing bracket.
 * - BetweenArrayElements: blank line between two array elements.
 * - InCondition: blank line inside the condition of a control structure.
 *
 * All errors are fixable with phpcbf.
 */
class BlankLineInExpressionSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [
      ...Tokens::ASSIGNMENT_TOKENS,
      T_DOUBLE_ARROW,
      T_OPEN_SHORT_ARRAY,
      T_ARRAY,
      T_IF,
      T_ELSEIF,
      T_WHILE,
      T_FOR,
      T_FOREACH,
      T_SWITCH,
      T_MATCH,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    $tokens = $phpcsFile->getTokens();

    if ($tokens[$stackPtr]['code'] === T_OPEN_SHORT_ARRAY) {
      $this->processArray($phpcsFile, $stackPtr, $tokens[$stackPtr]['bracket_closer'] ?? NULL);
    }
    elseif ($tokens[$stackPtr]['code'] === T_ARRAY) {
      if (isset($tokens[$stackPtr]['parenthesis_opener'])) {
        $this->processArray($phpcsFile, $tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer'] ?? NULL);
      }
    }
    elseif (isset(Tokens::ASSIGNMENT_TOKENS[$tokens[$stackPtr]['code']]) || $tokens[$stackPtr]['code'] === T_DOUBLE_ARROW) {
      $this->processOperator($phpcsFile, $stackPtr);
    }
    elseif (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer'])) {
      $this->processCondition($phpcsFile, $tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']);
    }
  }

  /**
   * Checks the blank lines inside the condition of a control structure.
   *
   * @param \PHP_CodeSniffer\Files\File $phpcsFile
   *   The file being scanned.
   * @param int $opener
   *   The position of the opening parenthesis.
   * @param int $closer
   *   The position of the closing parenthesis.
   */
  private function processCondition(File $phpcsFile, int $opener, int $closer): void {
    $tokens = $phpcsFile->getTokens();
    $previous = $opener;
    while (($next = $phpcsFile->findNext(T_WHITESPACE, $previous + 1, $closer + 1, TRUE)) !== FALSE) {
      if ($tokens[$next]['line'] > $tokens[$previous]['line'] + 1) {
        $fix = $phpcsFile->addFixableError('Blank line found inside the condition', $next, 'InCondition');
        if ($fix) {
          $this->removeBlankLines($phpcsFile, $previous, $next);
        }
      }
      // Closures have their own body: do not check it.
      $previous = $tokens[$next]['scope_closer'] ?? $next;
    }
  }

  /**
   * Checks the line breaks after an assignment operator or a double arrow.
   *
   * @param \PHP_CodeSniffer\Files\File $phpcsFile
   *   The file being scanned.
   * @param int $operator
   *   The position of the operator.
   */
  private function processOperator(File $phpcsFile, int $operator): void {
    $tokens = $phpcsFile->getTokens();
    $next = $phpcsFile->findNext(T_WHITESPACE, $operator + 1, NULL, TRUE);
    if ($next === FALSE || $tokens[$next]['line'] === $tokens[$operator]['line']) {
      return;
    }

    if (in_array($tokens[$next]['code'], [T_OPEN_SHORT_ARRAY, T_ARRAY], TRUE)) {
      $fix = $phpcsFile->addFixableError('The array opener must be on the same line as "%s"', $next, 'ArrayOpenerOnNewLine', [$tokens[$operator]['content']]);
      if ($fix) {
        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($operator + 1, ' ');
        for ($i = $operator + 2; $i < $next; $i++) {
          $phpcsFile->fixer->replaceToken($i, '');
        }
        $phpcsFile->fixer->endChangeset();
      }
      return;
    }

    if ($tokens[$next]['line'] > $tokens[$operator]['line'] + 1) {
      $fix = $phpcsFile->addFixableError('Blank line found after "%s"', $operator, 'AfterOperator', [$tokens[$operator]['content']]);
      if ($fix) {
        $this->removeBlankLines($phpcsFile, $operator, $next);
      }
    }
  }

  /**
   * Checks the blank lines inside an array, at its own nesting level.
   *
   * Nested arrays are checked on their own, nested closures and function
   * calls are skipped.
   *
   * @param \PHP_CodeSniffer\Files\File $phpcsFile
   *   The file being scanned.
   * @param int $opener
   *   The position of the opening bracket or parenthesis.
   * @param int|null $closer
   *   The position of the closing bracket or parenthesis.
   */
  private function processArray(File $phpcsFile, int $opener, ?int $closer): void {
    if ($closer === NULL) {
      return;
    }
    $tokens = $phpcsFile->getTokens();
    $previous = $opener;
    while (($next = $phpcsFile->findNext(T_WHITESPACE, $previous + 1, $closer + 1, TRUE)) !== FALSE) {
      if ($tokens[$next]['line'] > $tokens[$previous]['line'] + 1) {
        [$message, $code] = match (TRUE) {
          $previous === $opener => ['Blank line found after the array opener', 'AfterArrayOpener'],
          $next === $closer => ['Blank line found before the array closer', 'BeforeArrayCloser'],
          default => ['Blank line found between array elements', 'BetweenArrayElements'],
        };
        $fix = $phpcsFile->addFixableError($message, $next, $code);
        if ($fix) {
          $this->removeBlankLines($phpcsFile, $previous, $next);
        }
      }
      if ($next === $closer) {
        break;
      }
      $previous = $this->findNestedEnd($tokens, $next);
    }
  }

  /**
   * Returns the end of a nested structure, or the token itself.
   *
   * @param array<int, array<string, mixed>> $tokens
   *   The tokens of the file.
   * @param int $ptr
   *   The position of the token.
   *
   * @return int
   *   The position of the closer of the structure opened by the token.
   */
  private function findNestedEnd(array $tokens, int $ptr): int {
    return match ($tokens[$ptr]['code']) {
      T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET => $tokens[$ptr]['bracket_closer'] ?? $ptr,
      T_ARRAY, T_OPEN_PARENTHESIS => $tokens[$ptr]['parenthesis_closer'] ?? $ptr,
      default => $tokens[$ptr]['scope_closer'] ?? $ptr,
    };
  }

  /**
   * Removes the whitespace lines between two tokens.
   *
   * @param \PHP_CodeSniffer\Files\File $phpcsFile
   *   The file being scanned.
   * @param int $start
   *   The position of the token before the blank lines.
   * @param int $end
   *   The position of the token after the blank lines.
   */
  private function removeBlankLines(File $phpcsFile, int $start, int $end): void {
    $tokens = $phpcsFile->getTokens();
    $phpcsFile->fixer->beginChangeset();
    for ($i = $start + 1; $i < $end; $i++) {
      if ($tokens[$i]['line'] > $tokens[$start]['line'] && $tokens[$i]['line'] < $tokens[$end]['line']) {
        $phpcsFile->fixer->replaceToken($i, '');
      }
    }
    $phpcsFile->fixer->endChangeset();
  }

}
