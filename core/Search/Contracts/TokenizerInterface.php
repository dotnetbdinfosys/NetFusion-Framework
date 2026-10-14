<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Search\Contracts;

interface TokenizerInterface
{
    /**
     * Tokenize text into an array of string tokens with positions.
     *
     * @param string $text
     * @return array<int, string>
     */
    public function tokenize(string $text): array;

    /**
     * Tokenize text and return tokens along with their exact positional offsets.
     *
     * @param string $text
     * @return array<int, array{token: string, position: int, start_offset: int, end_offset: int}>
     */
    public function tokenizeWithPositions(string $text): array;
}
