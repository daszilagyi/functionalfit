<?php
/**
 * FunctionalFit Calendar - Web-based Installer
 *
 * HASZNÁLAT:
 * 1. Töltsd fel ezt a fájlt a public_html/api/ mappába
 * 2. Nyisd meg böngészőben: https://te-domain.hu/api/web-installer.php
 * 3. A telepítés után TÖRÖLD ezt a fájlt!
 *
 * @version 0.1.0-beta
 */

// Biztonsági ellenőrzés - töröld ezt a sort, ha szeretnéd futtatni
// die('Biztonsági okokból a telepítő le van tiltva. Szerkeszd a fájlt és töröld ezt a sort.');

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// Laravel alkalmazás betöltése
$basePath = dirname(__DIR__) . '/backend';

// Ellenőrzés
if (!file_exists($basePath . '/vendor/autoload.php')) {
    die('<h1>Hiba</h1><p>A vendor mappa nem található. Futtasd a <code>composer install</code> parancsot lokálisan, majd töltsd fel a teljes vendor mappát.</p>');
}

if (!file_exists($basePath . '/.env')) {
    die('<h1>Hiba</h1><p>A .env fájl nem található. Hozd létre a backend/.env fájlt a .env.example alapján.</p>');
}

require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$action = $_GET['action'] ?? 'status';
$output = [];
$success = true;

// HTML fejléc
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FunctionalFit Calendar - Telepítő</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        button, .btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        button:hover, .btn:hover { background: #45a049; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .check { color: #28a745; }
        .cross { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1>🗓️ FunctionalFit Calendar - Telepítő</h1>

    <?php
    // Adatbázis kapcsolat ellenőrzése
    try {
        DB::connection()->getPdo();
        $dbConnected = true;
    } catch (\Exception $e) {
        $dbConnected = false;
        $dbError = $e->getMessage();
    }

    switch ($action) {
        case 'status':
            ?>
            <h2>Rendszer állapot</h2>
            <table>
                <tr>
                    <th>Ellenőrzés</th>
                    <th>Állapot</th>
                </tr>
                <tr>
                    <td>PHP verzió (8.1+ szükséges)</td>
                    <td><?php echo version_compare(PHP_VERSION, '8.1.0', '>=') ? '<span class="check">✓</span> ' . PHP_VERSION : '<span class="cross">✗</span> ' . PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td>Adatbázis kapcsolat</td>
                    <td><?php echo $dbConnected ? '<span class="check">✓</span> Kapcsolódva' : '<span class="cross">✗</span> ' . $dbError; ?></td>
                </tr>
                <tr>
                    <td>Storage mappa írható</td>
                    <td><?php echo is_writable($basePath . '/storage') ? '<span class="check">✓</span> Írható' : '<span class="cross">✗</span> Nem írható'; ?></td>
                </tr>
                <tr>
                    <td>Cache mappa írható</td>
                    <td><?php echo is_writable($basePath . '/bootstrap/cache') ? '<span class="check">✓</span> Írható' : '<span class="cross">✗</span> Nem írható'; ?></td>
                </tr>
                <tr>
                    <td>.env fájl</td>
                    <td><?php echo file_exists($basePath . '/.env') ? '<span class="check">✓</span> Létezik' : '<span class="cross">✗</span> Hiányzik'; ?></td>
                </tr>
            </table>

            <?php if ($dbConnected): ?>
                <h2>Telepítési műveletek</h2>
                <p>
                    <a href="?action=migrate" class="btn">1. Migrációk futtatása</a>
                    <a href="?action=seed" class="btn">2. Adatok betöltése</a>
                    <a href="?action=fresh" class="btn btn-danger" onclick="return confirm('FIGYELEM! Ez törli az összes adatot és újraépíti az adatbázist. Biztos vagy benne?');">⚠️ Teljes újratelepítés</a>
                </p>

                <h2>Egyéb műveletek</h2>
                <p>
                    <a href="?action=optimize" class="btn btn-warning">Cache optimalizálás</a>
                    <a href="?action=clear" class="btn btn-warning">Cache törlése</a>
                </p>
            <?php else: ?>
                <div class="status error">
                    <strong>Adatbázis hiba!</strong><br>
                    Ellenőrizd a backend/.env fájlban az adatbázis beállításokat.
                </div>
            <?php endif; ?>
            <?php
            break;

        case 'migrate':
            echo '<h2>Migrációk futtatása...</h2>';
            try {
                Artisan::call('migrate', ['--force' => true]);
                echo '<div class="status success">✓ Migrációk sikeresen lefutottak!</div>';
                echo '<pre>' . Artisan::output() . '</pre>';
            } catch (\Exception $e) {
                echo '<div class="status error">✗ Hiba: ' . $e->getMessage() . '</div>';
            }
            echo '<p><a href="?action=status" class="btn">← Vissza</a></p>';
            break;

        case 'seed':
            echo '<h2>Kezdeti adatok betöltése...</h2>';
            try {
                Artisan::call('db:seed', ['--force' => true]);
                echo '<div class="status success">✓ Adatok sikeresen betöltve!</div>';
                echo '<pre>' . Artisan::output() . '</pre>';
                echo '<div class="status info">';
                echo '<strong>Alapértelmezett bejelentkezési adatok:</strong><br>';
                echo 'Admin: admin@functionalfit.hu / password<br>';
                echo 'Staff: staff@functionalfit.hu / password<br>';
                echo 'Client: client@functionalfit.hu / password';
                echo '</div>';
            } catch (\Exception $e) {
                echo '<div class="status error">✗ Hiba: ' . $e->getMessage() . '</div>';
            }
            echo '<p><a href="?action=status" class="btn">← Vissza</a></p>';
            break;

        case 'fresh':
            echo '<h2>Teljes újratelepítés...</h2>';
            try {
                Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
                echo '<div class="status success">✓ Adatbázis újraépítve és feltöltve!</div>';
                echo '<pre>' . Artisan::output() . '</pre>';
                echo '<div class="status info">';
                echo '<strong>Alapértelmezett bejelentkezési adatok:</strong><br>';
                echo 'Admin: admin@functionalfit.hu / password<br>';
                echo 'Staff: staff@functionalfit.hu / password<br>';
                echo 'Client: client@functionalfit.hu / password';
                echo '</div>';
            } catch (\Exception $e) {
                echo '<div class="status error">✗ Hiba: ' . $e->getMessage() . '</div>';
            }
            echo '<p><a href="?action=status" class="btn">← Vissza</a></p>';
            break;

        case 'optimize':
            echo '<h2>Cache optimalizálás...</h2>';
            try {
                Artisan::call('config:cache');
                echo '<div class="status success">✓ Config cache létrehozva</div>';
                Artisan::call('route:cache');
                echo '<div class="status success">✓ Route cache létrehozva</div>';
                Artisan::call('view:cache');
                echo '<div class="status success">✓ View cache létrehozva</div>';
            } catch (\Exception $e) {
                echo '<div class="status error">✗ Hiba: ' . $e->getMessage() . '</div>';
            }
            echo '<p><a href="?action=status" class="btn">← Vissza</a></p>';
            break;

        case 'clear':
            echo '<h2>Cache törlése...</h2>';
            try {
                Artisan::call('cache:clear');
                echo '<div class="status success">✓ Application cache törölve</div>';
                Artisan::call('config:clear');
                echo '<div class="status success">✓ Config cache törölve</div>';
                Artisan::call('route:clear');
                echo '<div class="status success">✓ Route cache törölve</div>';
                Artisan::call('view:clear');
                echo '<div class="status success">✓ View cache törölve</div>';
            } catch (\Exception $e) {
                echo '<div class="status error">✗ Hiba: ' . $e->getMessage() . '</div>';
            }
            echo '<p><a href="?action=status" class="btn">← Vissza</a></p>';
            break;
    }
    ?>

    <hr style="margin-top: 30px;">
    <p style="color: #666; font-size: 14px;">
        <strong>⚠️ FONTOS:</strong> A telepítés befejezése után <strong>töröld ezt a fájlt</strong> biztonsági okokból!
    </p>
</div>
</body>
</html>
