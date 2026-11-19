<?php

namespace App\Service;

/**
 * Masque dans un nom affiché sur la page publique ce qui renseigne sur l'infrastructure :
 * adresses, noms d'hôte, ports, chemins et numéros de version.
 */
final class PublicLabel
{
    public const MASK = '…';

    /**
     * Ordre important : une URL ou un chemin se masque d'un bloc avant que ses morceaux ne soient reconnus.
     */
    private const PATTERNS = [
        '#\b[a-z][a-z0-9+.-]*://[^\s)\]]*#i',
        '#(?<![^\s(\[])(?:~|\.{1,2})?/[^\s)\]]*#',
        '#\b[a-z]:\\\\[^\s)\]]*#i',
        '#\b\d{1,3}(?:\.\d{1,3}){3}(?::\d+)?\b#',
        '#(?<![\w:])[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){2,7}(?![\w:])#i',
        '#\b(?:[a-z0-9-]+\.)+[a-z]{2,}(?::\d+)?\b#i',
        '#\bv?\d+(?:\.\d+)+\b#i',
        '#(?<![^\s(\[]):\d{2,5}\b#',
    ];

    public static function mask(string $pLabel): string
    {
        $vMasked = preg_replace(self::PATTERNS, self::MASK, $pLabel) ?? $pLabel;
        $rLabel = trim(preg_replace('/\s+/u', ' ', $vMasked) ?? $vMasked);

        return $rLabel;
    }
}
