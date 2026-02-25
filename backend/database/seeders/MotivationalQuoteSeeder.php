<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivationalQuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if table is empty
        if (DB::table('motivational_quotes')->count() > 0) {
            return;
        }

        $now = now();

        DB::table('motivational_quotes')->insert([
            ['text' => 'A siker nem a célban van, hanem az úton, amelyen eljutsz oda.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Minden nap egy új lehetőség arra, hogy jobb legyél, mint tegnap voltál.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Az erő nem az izmaidban lakik, hanem az elméd kitartásában.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A nehéz edzések teszik lehetővé a könnyű versenyeket.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Nem az számít, milyen gyorsan érsz a célba, hanem hogy el ne add, amíg oda nem érsz.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A testünk mindig képes többre – az agyunknak kell meggyőzni.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Inspirálj másokat azzal, amit ma teszel!', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A változás kényelmetlenséggel jár – de a fejlődés megéri.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Légy te a legjobb edző, akit valaha is kaphatnak.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Egy jó edző nemcsak testet, hanem jellemet is formál.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A motiváció beindítja a motort, a szokás pedig hajtja tovább.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Az edzés egy befektetés a jövőd egészségébe.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Ne számold a napokat – hadd számolják a napok az eredményeidet!', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Amit ma elvetsz fáradtsággal, holnap erőként aratod le.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A kitartás legyőzi a tehettséget.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Minden egyes edzéssel közelebb kerülsz ahhoz, akivé válni szeretnél.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Az igazán nagy edzők az embereket is látják, nemcsak az izomcsoportokat.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Egy mosoly és egy bátorító szó néha többet ér, mint a legjobb program.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'Ma is lehetőséged van valakinek az életét jobbá tenni.', 'created_at' => $now, 'updated_at' => $now],
            ['text' => 'A fájdalom ideiglenes, de az eredmény örök.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
