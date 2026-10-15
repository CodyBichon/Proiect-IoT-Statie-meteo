// pico.php

<?php
// Numele fișierului CSV
$nume_fisier_csv = 'date_meteo.csv';

// Verifică dacă există date JSON în cererea POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // Obține datele JSON din cerere
    $date_json = file_get_contents('php://input');
    $date_meteo = json_decode($date_json, true);

    // Verifică dacă datele JSON sunt valide
    if ($date_meteo && is_array($date_meteo) && isset($date_meteo['temperatura']) && isset($date_meteo['umiditate']) && isset($date_meteo['presiune']) && isset($date_meteo['intensitate_uv']) && isset($date_meteo['viteza_vant'])) {
        // Adaugă data și ora curente
        $data_ora = date('Y-m-d H:i:s');

        // Formatează datele pentru fișierul CSV
        $linie_csv = implode(',', [
            $data_ora,
            $date_meteo['temperatura'],
            $date_meteo['umiditate'],
            $date_meteo['presiune'],
            $date_meteo['intensitate_uv'],
            $date_meteo['viteza_vant'],
        ]) . "\n";

        // Scrie datele în fișierul CSV
        file_put_contents($nume_fisier_csv, $linie_csv, FILE_APPEND);

        // Răspuns JSON către Pico W
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Date primite și procesate cu succes.']);
        exit;
    } else {
        // Răspuns JSON de eroare pentru Pico W
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Date JSON incorecte sau lipsesc.']);
        exit;
    }
} else {
    // Răspuns JSON de eroare pentru Pico W
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Cerere incorectă.']);
    exit;
}
?>
