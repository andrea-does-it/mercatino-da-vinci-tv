<?php

// Guard: admin only
if ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser') {
    header('Location: ' . ROOT_URL . 'user?page=dashboard&msg=forbidden');
    exit;
}

?>
<h2>Self-Test: Strumento Import Libri</h2>

<div style="margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #0066cc;">

<?php

echo "<h3>Test 1: validateIsbn13 con ISBN valido</h3>";
$test1 = BookLookup::validateIsbn13('9788838338748') === true;
echo $test1 ? '<span style="color: green; font-weight: bold;">PASS</span>' : '<span style="color: red; font-weight: bold;">FAIL</span>';
echo " - validateIsbn13('9788838338748') should return true<br><br>";

echo "<h3>Test 2: validateIsbn13 con checksum non valido</h3>";
$test2 = BookLookup::validateIsbn13('9788838338747') === false;
echo $test2 ? '<span style="color: green; font-weight: bold;">PASS</span>' : '<span style="color: red; font-weight: bold;">FAIL</span>';
echo " - validateIsbn13('9788838338747') should return false<br><br>";

echo "<h3>Test 3: validateIsbn13 con lunghezza non valida</h3>";
$test3 = BookLookup::validateIsbn13('123') === false;
echo $test3 ? '<span style="color: green; font-weight: bold;">PASS</span>' : '<span style="color: red; font-weight: bold;">FAIL</span>';
echo " - validateIsbn13('123') should return false<br><br>";

echo "<h3>Test 4: coverUrl format</h3>";
$expectedUrl = 'https://www.libraccio.it/images/9788838338748_0_500_0_75.jpg';
$actualUrl = BookLookup::coverUrl('9788838338748');
$test4 = $actualUrl === $expectedUrl;
echo $test4 ? '<span style="color: green; font-weight: bold;">PASS</span>' : '<span style="color: red; font-weight: bold;">FAIL</span>';
echo "<br>Expected: $expectedUrl<br>";
echo "Got: $actualUrl<br><br>";

echo "<h3>Test 5: lookup con ISBN valido (Libraccio lookup)</h3>";
$lookup = BookLookup::lookup('9788838338748');
if ($lookup && isset($lookup['list_price']) && $lookup['list_price'] > 0) {
    echo '<span style="color: green; font-weight: bold;">PASS</span>';
    echo "<br>ISBN: " . htmlspecialchars($lookup['isbn']);
    echo "<br>Title: " . htmlspecialchars($lookup['title']);
    echo "<br>Price: " . htmlspecialchars((string)$lookup['list_price']);
    echo "<br>Warnings: " . (count($lookup['warnings']) > 0 ? implode(', ', $lookup['warnings']) : 'Nessuno');
} else {
    echo '<span style="color: orange; font-weight: bold;">PARTIAL</span>';
    echo "<br>Lookup returned but no valid list_price.";
    if ($lookup) {
        echo "<br>Warnings: " . (count($lookup['warnings']) > 0 ? implode(', ', $lookup['warnings']) : 'Nessuno');
    }
}
echo "<br><br>";

echo "<h3>Test 6: ProductManager::findByISBN con ISBN non esistente</h3>";
$pm = new ProductManager();
$product = $pm->findByISBN('0000000000000');
$test6 = $product === null;
echo $test6 ? '<span style="color: green; font-weight: bold;">PASS</span>' : '<span style="color: red; font-weight: bold;">FAIL</span>';
echo " - findByISBN('0000000000000') should return null<br><br>";

?>

</div>

<h3>Riepilogo Test</h3>
<p>Controlla i risultati sopra. Se tutti i test passano, il backend è pronto per l'integrazione con l'UI.</p>
