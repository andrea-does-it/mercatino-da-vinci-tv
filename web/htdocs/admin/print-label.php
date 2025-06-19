<?php
// File: web/htdocs/admin/print-label.php

// Prevent from direct access
if (!defined('ROOT_URL')) {
    // If accessed directly, define ROOT_URL for standalone access
    define('ROOT_URL', '../');
}

// Include the barcode generator autoloader
require_once '../vendor/picqer/php-barcode-generator/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;

// Check if pratica number is provided
if (!isset($_GET['pratica']) || empty($_GET['pratica'])) {
    die('Numero pratica non fornito');
}

$pratica = htmlspecialchars($_GET['pratica']);

// Get current date for printing
$currentDate = date('d/m/Y');

// Create barcode generator instance
$generator = new BarcodeGeneratorHTML();

// Generate EAN128 (Code128) barcode
try {
    // Use the original pratica number without padding (no leading zeros)
    $barcode = $generator->getBarcode($pratica, $generator::TYPE_CODE_128, 2, 60);
} catch (Exception $e) {
    die('Errore nella generazione del barcode: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etichetta Pratica <?php echo $pratica; ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .label { page-break-after: always; }
            .barcode-container div {
                background-color: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .barcode-container div > div {
                background-color: black !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin-top: 3mm;
            margin-left: 20px;
            margin-right: 20px;
            margin-bottom: 1mm;
        }
        
        .label {
            width: 50mm;
            height: 20mm;
            padding: 1mm;
            margin: 0 auto;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-text {
            font-size: 7px;
            font-weight: bold;
            margin: 0;
            text-align: center;
            line-height: 1;
        }
        
        .date-text {
            font-size: 7px;
            font-weight: normal;
            margin: 0;
            text-align: center;
            line-height: 1;
        }
        
        .barcode-container {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-height: 12mm;
            background: white;
        }
        
        .barcode-container > div {
            max-width: 100%;
            max-height: 100%;
            height: auto;
            background: white !important;
        }
        
        .barcode-container div div {
            background-color: black !important;
            border: none !important;
        }
        
        .pratica-number {
            font-size: 7px;
            font-weight: bold;
            margin: 0;
            text-align: center;
        }
        
        .controls {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="controls no-print">
        <button onclick="window.print()" class="btn">Stampa Etichetta</button>
        <a href="javascript:history.back()" class="btn btn-secondary">Torna Indietro</a>
    </div>
    
    <div class="label">
        <div class="header-text">Mercatino Liceo Da Vinci Treviso: num. pratica <?php echo $pratica; ?></div>
        
        <div class="date-text">Data: <?php echo $currentDate; ?></div>
        
        <div class="barcode-container">
            <?php echo $barcode; ?>
        </div>
    </div>
    
    <script>
        // Auto-print when opened in new window
        window.addEventListener('load', function() {
            if (window.location.search.includes('autoprint=1')) {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        });
    </script>
</body>
</html>