<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Calculator - Interactive Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        .result-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .bonus-number {
            font-size: 64px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .tier-badge {
            font-size: 24px;
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            margin: 10px 0;
        }
        .kpi-card {
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateX(5px);
        }
        .slider-container {
            margin: 20px 0;
        }
        .slider-value {
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <h1 class="display-4">
                    <i class="fas fa-calculator text-primary"></i>
                    Bonus Calculator Tool
                </h1>
                <p class="lead">Interaktiv kalkylator för att testa bonusformler och scenarier</p>
                <hr>
            </div>
        </div>

        <div class="row">
            <!-- Input Panel -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-sliders-h"></i> Input Parameters</h5>
                    </div>
                    <div class="card-body">
                        <form id="calculatorForm">
                            <!-- IBC OK -->
                            <div class="slider-container">
                                <label class="form-label">
                                    IBC OK (Godkända): <span class="slider-value" id="ibc_ok_value">95</span>
                                </label>
                                <input type="range" class="form-range" id="ibc_ok" min="0" max="200" value="95" step="1">
                            </div>

                            <!-- IBC Ej OK -->
                            <div class="slider-container">
                                <label class="form-label">
                                    IBC Ej OK (Kasserade): <span class="slider-value" id="ibc_ej_ok_value">5</span>
                                </label>
                                <input type="range" class="form-range" id="ibc_ej_ok" min="0" max="50" value="5" step="1">
                            </div>

                            <!-- Bur Ej OK -->
                            <div class="slider-container">
                                <label class="form-label">
                                    Bur Ej OK (Defekta burar): <span class="slider-value" id="bur_ej_ok_value">2</span>
                                </label>
                                <input type="range" class="form-range" id="bur_ej_ok" min="0" max="20" value="2" step="1">
                            </div>

                            <!-- Runtime -->
                            <div class="slider-container">
                                <label class="form-label">
                                    Runtime (minuter): <span class="slider-value" id="runtime_value">120</span>
                                </label>
                                <input type="range" class="form-range" id="runtime" min="30" max="480" value="120" step="10">
                            </div>

                            <!-- Produkt -->
                            <div class="mb-3">
                                <label class="form-label">Produkt</label>
                                <select class="form-select" id="produkt">
                                    <option value="1">FoodGrade (viktning: 30/30/40)</option>
                                    <option value="4">NonUN (viktning: 35/45/20)</option>
                                    <option value="5">Tvättade IBC (viktning: 40/35/25)</option>
                                </select>
                            </div>

                            <!-- Extra Options -->
                            <div class="mb-3">
                                <label class="form-label">Team Multiplier</label>
                                <input type="number" class="form-control" id="team_multiplier" value="1.0" step="0.05" min="0.5" max="2.0">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Safety Factor</label>
                                <input type="number" class="form-control" id="safety_factor" value="1.0" step="0.05" min="0.5" max="1.2">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mentorship Bonus (poäng)</label>
                                <input type="number" class="form-control" id="mentorship_bonus" value="0" step="2.5" min="0" max="20">
                            </div>

                            <button type="button" class="btn btn-primary btn-lg w-100" onclick="calculate()">
                                <i class="fas fa-calculator"></i> Beräkna Bonus
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Comparison Mode -->
                <div class="card mt-3">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-balance-scale"></i> Jämför Formler</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-success w-100" onclick="compareFormulas()">
                            <i class="fas fa-chart-line"></i> Jämför Gammal vs Ny Formel
                        </button>
                        <div id="comparisonResult" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <!-- Results Panel -->
            <div class="col-md-6">
                <div id="resultsPanel">
                    <!-- Results will be dynamically inserted here -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Fyll i parametrar och klicka "Beräkna Bonus" för att se resultat
                    </div>
                </div>

                <!-- Chart -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-chart-pie"></i> KPI Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="kpiChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update slider values in real-time
        ['ibc_ok', 'ibc_ej_ok', 'bur_ej_ok', 'runtime'].forEach(id => {
            document.getElementById(id).addEventListener('input', function() {
                document.getElementById(id + '_value').textContent = this.value;
            });
        });

        let kpiChart = null;

        function calculate() {
            const data = {
                ibc_ok: parseInt(document.getElementById('ibc_ok').value),
                ibc_ej_ok: parseInt(document.getElementById('ibc_ej_ok').value),
                bur_ej_ok: parseInt(document.getElementById('bur_ej_ok').value),
                runtime_plc: parseInt(document.getElementById('runtime').value),
                produkt: parseInt(document.getElementById('produkt').value),
                team_multiplier: parseFloat(document.getElementById('team_multiplier').value),
                safety_factor: parseFloat(document.getElementById('safety_factor').value),
                mentorship_bonus: parseFloat(document.getElementById('mentorship_bonus').value)
            };

            fetch('bonus_calculator_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                displayResults(result);
                updateChart(result);
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Fel vid beräkning: ' + err.message);
            });
        }

        function displayResults(result) {
            const tierColors = {
                'Outstanding': 'bg-success',
                'Excellent': 'bg-primary',
                'God prestanda': 'bg-info',
                'Basbonus': 'bg-warning',
                'Under förväntan': 'bg-danger'
            };

            const tierColor = tierColors[result.bonus_tier_name] || 'bg-secondary';

            const html = `
                <div class="result-box">
                    <div class="text-center">
                        <div class="bonus-number">${result.bonus_poang}</div>
                        <p class="fs-4">Bonuspoäng</p>
                        <div class="tier-badge ${tierColor}">
                            ${result.bonus_tier_name} (×${result.bonus_tier_multiplier})
                        </div>
                    </div>
                </div>

                <div class="card kpi-card mb-2">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-check-circle text-success"></i> Effektivitet
                        </h6>
                        <div class="d-flex justify-content-between">
                            <span>${result.effektivitet}%</span>
                            <span class="text-muted">×${result.weights_used.eff} = ${result.breakdown.effektivitet_weighted}</span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" style="width: ${result.effektivitet}%"></div>
                        </div>
                    </div>
                </div>

                <div class="card kpi-card mb-2">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-rocket text-primary"></i> Produktivitet
                        </h6>
                        <div class="d-flex justify-content-between">
                            <span>${result.produktivitet} IBC/h</span>
                            <span class="text-muted">Mål: ${result.produktivitet_target} IBC/h</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Normaliserad: ${result.produktivitet_normalized}%</span>
                            <span class="text-muted">×${result.weights_used.prod} = ${result.breakdown.produktivitet_weighted}</span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-primary" style="width: ${Math.min(result.produktivitet_normalized, 100)}%"></div>
                        </div>
                    </div>
                </div>

                <div class="card kpi-card mb-2">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-star text-warning"></i> Kvalitet
                        </h6>
                        <div class="d-flex justify-content-between">
                            <span>${result.kvalitet}%</span>
                            <span class="text-muted">×${result.weights_used.qual} = ${result.breakdown.kvalitet_weighted}</span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-warning" style="width: ${result.kvalitet}%"></div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body bg-light">
                        <h6>Bonusberäkning:</h6>
                        <ul class="mb-0">
                            <li>Basbonus: ${result.bonus_base}</li>
                            <li>Efter Tier (×${result.bonus_tier_multiplier}): ${result.bonus_after_tier}</li>
                            <li>Team Multiplier (×${result.bonus_team_multiplier}): ${(result.bonus_after_tier * result.bonus_team_multiplier).toFixed(2)}</li>
                            <li>Safety Factor (×${result.bonus_safety_factor}): Applied</li>
                            <li>Mentorship: +${result.bonus_mentorship}</li>
                            <li class="fw-bold">Final Bonus: ${result.bonus_poang} poäng</li>
                        </ul>
                    </div>
                </div>
            `;

            document.getElementById('resultsPanel').innerHTML = html;
        }

        function updateChart(result) {
            const ctx = document.getElementById('kpiChart').getContext('2d');

            if (kpiChart) {
                kpiChart.destroy();
            }

            kpiChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
                    datasets: [{
                        data: [
                            result.breakdown.effektivitet_weighted,
                            result.breakdown.produktivitet_weighted,
                            result.breakdown.kvalitet_weighted
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Bonus Contribution Breakdown'
                        }
                    }
                }
            });
        }

        function compareFormulas() {
            const data = {
                ibc_ok: parseInt(document.getElementById('ibc_ok').value),
                ibc_ej_ok: parseInt(document.getElementById('ibc_ej_ok').value),
                bur_ej_ok: parseInt(document.getElementById('bur_ej_ok').value),
                runtime_plc: parseInt(document.getElementById('runtime').value),
                produkt: parseInt(document.getElementById('produkt').value),
                compare: true
            };

            fetch('bonus_calculator_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                const diff = result.difference;
                const improvement = result.improvement;
                const diffColor = diff >= 0 ? 'success' : 'danger';
                const diffIcon = diff >= 0 ? 'arrow-up' : 'arrow-down';

                const html = `
                    <div class="alert alert-${diffColor}">
                        <h6><i class="fas fa-${diffIcon}"></i> Skillnad: ${diff} poäng (${improvement}%)</h6>
                        <hr>
                        <p class="mb-1"><strong>Gammal formel:</strong> ${result.old.bonus_poang} poäng</p>
                        <p class="mb-0"><strong>Ny formel:</strong> ${result.new.bonus_poang} poäng</p>
                    </div>
                `;

                document.getElementById('comparisonResult').innerHTML = html;
            });
        }
    </script>
</body>
</html>
