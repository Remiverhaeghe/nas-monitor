<?php

namespace App\Tests\Service;

use App\Service\PublicLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLabels(): iterable
    {
        yield 'nom ordinaire' => ['Site familial', 'Site familial'];
        yield 'nom avec chiffres' => ['Photos 2026 et NAS 2', 'Photos 2026 et NAS 2'];
        yield 'URL' => ['Nextcloud (https://cloud.exemple.fr/index.php)', 'Nextcloud (…)'];
        yield 'adresse IPv4 et port' => ['NAS 192.168.1.10:5001', 'NAS …'];
        yield 'adresse IPv6' => ['Routeur fd00::1', 'Routeur …'];
        yield 'nom d\'hôte' => ['Partage nas.exemple.lan', 'Partage …'];
        yield 'port seul' => ['Jellyfin :8096', 'Jellyfin …'];
        yield 'chemin Unix' => ['Sauvegarde /volume1/photos', 'Sauvegarde …'];
        yield 'chemin relatif et personnel' => ['Copie ~/docs puis ./export', 'Copie … puis …'];
        yield 'chemin Windows' => ['Copie D:\\Sauvegardes\\NAS', 'Copie …'];
        yield 'versions' => ['Gitea 1.22.3 sur PHP v8.5', 'Gitea … sur PHP …'];
        yield 'heure gardée' => ['Sauvegarde de 12:30', 'Sauvegarde de 12:30'];
        yield 'chemin entre parenthèses' => ['Disque (/volume2) [nas.lan:443]', 'Disque (…) […]'];
        yield 'espaces' => ["  Site\t familial  ", 'Site familial'];
    }

    #[DataProvider('provideLabels')]
    public function testSensitivePartsAreMasked(string $pLabel, string $pExpected): void
    {
        self::assertSame($pExpected, PublicLabel::mask($pLabel));
    }
}
