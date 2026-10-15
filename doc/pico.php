<?php
// Numele fișierului CSV
$nume_fisier_csv = 'date_meteo.csv';

// Numărul de valori implicit pentru grafic
$numar_valori_grafic_implicit = 30;

// Verifică dacă s-a trimis un număr de înregistrări prin GET
if (isset($_GET['nr_inregistrari']) && is_numeric($_GET['nr_inregistrari'])) {
    $numar_valori_grafic = intval($_GET['nr_inregistrari']);
    // Asigură-te că valoarea este în intervalul dorit (opțional)
    $valori_permise = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
    if (!in_array($numar_valori_grafic, $valori_permise)) {
        $numar_valori_grafic = $numar_valori_grafic_implicit; // Folosește valoarea implicită dacă nu este validă
    }
} else {
    $numar_valori_grafic = $numar_valori_grafic_implicit; // Folosește valoarea implicită dacă nu este setat
}

// Răspuns HTML pentru cererile GET
header('Content-Type: text/html; charset=utf-8');

// Citeste datele din fisierul csv
$date_csv = @file_get_contents($nume_fisier_csv);

if ($date_csv === false) {
    echo "<h1 class='titlu-meteo'>Date Meteo</h1>";
    echo "<p>Date CSV incorecte sau indisponibile.</p>";
} else {
    // Parsează datele CSV
    $linii = explode("\n", $date_csv);
    $date_meteo = [];
    foreach ($linii as $linie) {
        $valori = explode(",", $linie);
        if (count($valori) === 6) {
            $date_meteo[] = [
                'data_ora' => trim($valori[0]),
                'temperatura' => floatval(trim($valori[1])),
                'umiditate' => floatval(trim($valori[2])),
                'presiune' => floatval(trim($valori[3])),
                'intensitate_uv' => floatval(trim($valori[4])),
                'viteza_vant' => floatval(trim($valori[5])),
            ];
        }
    }

    // Inversează array-ul pentru a avea cele mai recente valori la început
    $date_meteo = array_reverse($date_meteo);

    // Afișează ultima înregistrare din fișierul CSV
    if (!empty($date_meteo)) {
        $ultima_inregistrare = $date_meteo[0];
        echo "<h1 class='titlu-meteo'>Ultima înregistrare meteo</h1>";
        echo "<table class='tabel-meteo'>";
        echo "<tr class='antet-tabel'>";
        echo "<th>Data/Ora</th>";
        echo "<th>Temperatură (°C)</th>";
        echo "<th>Umiditate (%)</th>";
        echo "<th>Presiune (hPa)</th>";
        echo "<th>Intensitate UV (mW/cm2)</th>";
        echo "<th>Viteza vantului (Km/h)</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<td>" . $ultima_inregistrare['data_ora'] . "</td>";
        echo "<td>" . $ultima_inregistrare['temperatura'] . "</td>";
        echo "<td>" . $ultima_inregistrare['umiditate'] . "</td>";
        echo "<td>" . $ultima_inregistrare['presiune'] . "</td>";
        echo "<td>" . $ultima_inregistrare['intensitate_uv'] . "</td>";
        echo "<td>" . $ultima_inregistrare['viteza_vant'] . "</td>";
        echo "</tr>";
        echo "</table>";
    } else {
        echo "<h1 class='titlu-meteo'>Date Meteo</h1>";
        echo "<p>Nu există înregistrări în fișierul CSV.</p>";
    }

    // Extrage ultimele n valori pentru grafice
    $ultimele_valori = array_slice($date_meteo, 0, $numar_valori_grafic);

    // Inversează din nou pentru a avea ordinea cronologică corectă pentru grafice
    $ultimele_valori = array_reverse($ultimele_valori);

    // Prepare data for charts
    $labels = array_column($ultimele_valori, 'data_ora');
    $temperaturi = array_column($ultimele_valori, 'temperatura');
    $umiditati = array_column($ultimele_valori, 'umiditate');
    $presiuni = array_column($ultimele_valori, 'presiune');
    $uv_intensitati = array_column($ultimele_valori, 'intensitate_uv');
    $viteze_vant = array_column($ultimele_valori, 'viteza_vant');

    echo "<h2 class='titlu-grafice'>Grafice meteo, pentru ultimile ";
    echo "<select onchange='schimbaNrInregistrari(this)'>";
    $valori_combo = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
    foreach ($valori_combo as $valoare) {
        $selected = ($valoare == $numar_valori_grafic) ? 'selected' : '';
        echo "<option value='" . $valoare . "' " . $selected . ">" . $valoare . "</option>";
    }
    echo "</select>";
    echo " înregistrări</h2>";
    echo "<div class='container-grafice'>";

    // Grafic Temperatură
    echo "<div class='grafic'>";
    echo "<h3>Temperatură (°C)</h3>";
    echo "<canvas id='graficTemperatura'></canvas>";
    echo "</div>";

    // Grafic Umiditate
    echo "<div class='grafic'>";
    echo "<h3>Umiditate (%)</h3>";
    echo "<canvas id='graficUmiditate'></canvas>";
    echo "</div>";

    // Grafic Presiune
    echo "<div class='grafic'>";
    echo "<h3>Presiune (hPa)</h3>";
    echo "<canvas id='graficPresiune'></canvas>";
    echo "</div>";

    // Grafic Intensitate UV
    echo "<div class='grafic'>";
    echo "<h3>Intensitate UV (mW/cm2)</h3>";
    echo "<canvas id='graficUV'></canvas>";
    echo "</div>";

    // Grafic Viteza Vântului
    echo "<div class='grafic'>";
    echo "<h3>Viteza Vântului (Km/h)</h3>";
    echo "<canvas id='graficVant'></canvas>";
    echo "</div>";

    echo "</div>";
}
?>

<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labelsTemperatura = <?php echo json_encode($labels); ?>;
    const dataTemperatura = {
        labels: labelsTemperatura,
        datasets: [{
            label: 'Temperatură (°C)',
            data: <?php echo json_encode($temperaturi); ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1
        }]
    };
    const configTemperatura = {
        type: 'line',
        data: dataTemperatura,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    };
    const graficTemperatura = new Chart(
        document.getElementById('graficTemperatura'),
        configTemperatura
    );

    const labelsUmiditate = <?php echo json_encode($labels); ?>;
    const dataUmiditate = {
        labels: labelsUmiditate,
        datasets: [{
            label: 'Umiditate (%)',
            data: <?php echo json_encode($umiditati); ?>,
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            tension: 0.1
        }]
    };
    const configUmiditate = {
        type: 'line',
        data: dataUmiditate,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    };
    const graficUmiditate = new Chart(
        document.getElementById('graficUmiditate'),
        configUmiditate
    );

    const labelsPresiune = <?php echo json_encode($labels); ?>;
    const dataPresiune = {
        labels: labelsPresiune,
        datasets: [{
            label: 'Presiune (hPa)',
            data: <?php echo json_encode($presiuni); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    };
    const configPresiune = {
        type: 'line',
        data: dataPresiune,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    };
    const graficPresiune = new Chart(
        document.getElementById('graficPresiune'),
        configPresiune
    );

    const labelsUV = <?php echo json_encode($labels); ?>;
    const dataUV = {
        labels: labelsUV,
        datasets: [{
            label: 'Intensitate UV (mW/cm2)',
            data: <?php echo json_encode($uv_intensitati); ?>,
            borderColor: 'rgb(255, 205, 86)',
            backgroundColor: 'rgba(255, 205, 86, 0.2)',
            tension: 0.1
        }]
    };
    const configUV = {
        type: 'line',
        data: dataUV,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    };
    const graficUV = new Chart(
        document.getElementById('graficUV'),
        configUV
    );

    const labelsVant = <?php echo json_encode($labels); ?>;
    const dataVant = {
        labels: labelsVant,
        datasets: [{
            label: 'Viteza Vântului (Km/h)',
            data: <?php echo json_encode($viteze_vant); ?>,
            borderColor: 'rgb(153, 102, 255)',
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            tension: 0.1
        }]
    };
    const configVant = {
        type: 'line',
        data: dataVant,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    };
    const graficVant = new Chart(
        document.getElementById('graficVant'),
        configVant
    );

    function schimbaNrInregistrari(selectObject) {
        let selectedValue = selectObject.value;
        window.location.href = window.location.pathname + '?nr_inregistrari=' + selectedValue;
    }

    // Reîncarcă pagina la fiecare 5 secunde
    setInterval(function() {
        location.reload();
    }, 5000);
</script>
