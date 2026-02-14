# Bonus PDF Rapportgenerator

## Översikt

Systemet för att generera PDF-rapporter för operatörers månadsbonus innehåller:

1. **BonusPDFReport.php** - Huvudklass för PDF-generering
2. **bonus_pdf_api.php** - API endpoint för rapportgenerering
3. **bonus_pdf_generator.html** - Webbaserat gränssnitt

## Installation

### 1. Installera FPDF via Composer

```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
composer require setasign/fpdf
```

### 2. Skapa reports-katalog

```bash
mkdir -p /home/clawd/clawd/mauserdb/noreko-plcbackend/reports
chmod 755 /home/clawd/clawd/mauserdb/noreko-plcbackend/reports
```

## Användning

### Via Webgränssnitt

1. Öppna `bonus_pdf_generator.html` i webbläsaren
2. Ange operatör ID
3. Välj period (YYYY-MM)
4. Klicka "Generera PDF-rapport"
5. Ladda ner den färdiga PDF:en

### Via API

#### Generera rapport

```bash
curl -X POST "http://localhost/noreko-plcbackend/bonus_pdf_api.php" \
  -d "operator_id=123" \
  -d "period=2026-02"
```

**Response:**
```json
{
  "success": true,
  "message": "PDF report generated",
  "filename": "bonus_report_123_2026-02.pdf",
  "download_url": "?download=bonus_report_123_2026-02.pdf",
  "timestamp": "2026-02-13 10:30:00"
}
```

#### Ladda ner rapport

```bash
curl -O "http://localhost/noreko-plcbackend/bonus_pdf_api.php?download=bonus_report_123_2026-02.pdf"
```

### Via PHP

```php
<?php
require_once 'BonusPDFReport.php';

$report = new BonusPDFReport($pdo);
$filepath = $report->generateOperatorMonthlyReport(123, '2026-02');

echo "PDF saved to: $filepath\n";
```

## Rapportinnehåll

PDF-rapporten innehåller följande sektioner:

### 1. Header
- Operatör ID
- Period
- Genererad tidstämpel

### 2. Sammanfattning
- **Total bonuspoäng** - Stor, framträdande siffra
- Produktionsstatistik:
  - Antal cykler
  - IBC OK/Ej OK
  - Bur Ej OK
  - Total arbetstid
- KPI genomsnitt:
  - Effektivitet
  - Produktivitet
  - Kvalitet
  - Snittbonus per cykel

### 3. KPI Breakdown
- **Progress bars** för varje KPI:
  - Effektivitet (mål: 95%)
  - Produktivitet (normaliserad)
  - Kvalitet (mål: 98%)
- Färgkodning:
  - Grön: Över target
  - Gul: 80-100% av target
  - Röd: Under 80% av target

### 4. Dagliga Prestationer
- Tabell med daglig breakdown:
  - Datum
  - Produkt
  - IBC OK
  - Genomsnittlig effektivitet
  - Genomsnittlig produktivitet
  - Genomsnittlig kvalitet
  - Total bonuspoäng

### 5. Prestationstrend
- Veckovis trendanalys
- Jämförelse första vs sista veckan
- Trend-indikator (📈 uppåt, 📉 nedåt, ➡️ stabil)

## Anpassning

### Ändra färger

I `BonusPDFReport.php`:

```php
private const COLOR_PRIMARY = [44, 62, 80];      // Header färg
private const COLOR_SUCCESS = [39, 174, 96];     // Framgång
private const COLOR_WARNING = [243, 156, 18];    // Varning
private const COLOR_DANGER = [231, 76, 60];      // Fara
```

### Lägg till fler KPI:er

I metoden `renderKPIBreakdown()`:

```php
$kpis = [
    ['label' => 'Din KPI', 'value' => $värde, 'target' => $målvärde],
    // ... fler KPI:er
];
```

### Ändra rapportlayout

Modifiera metoderna:
- `renderHeader()` - Sidhuvud
- `renderSummary()` - Sammanfattning
- `renderKPIBreakdown()` - KPI visualiseringar
- `renderDailyDetails()` - Daglig tabell
- `renderTrend()` - Trendanalys

## Säkerhet

- **Filnamnsvalidering**: Endast tillåtna tecken i filnamn
- **Path traversal-skydd**: Använder `basename()` för filhämtning
- **Input validation**: Validerar operator_id och period format
- **Directory isolation**: PDF:er sparas endast i `/reports/` katalogen

## Felsökning

### "No data found for operator"

- Kontrollera att operatör ID finns i databasen
- Verifiera att det finns data för den valda perioden
- Kolla att `bonus_poang` är beräknad (inte NULL)

### "FPDF class not found"

```bash
composer require setasign/fpdf
```

### "Permission denied" när PDF skapas

```bash
chmod 755 /path/to/reports
chmod 644 /path/to/reports/*.pdf
```

### Tomma eller trasiga PDF:er

- Kontrollera PHP error log
- Verifiera databasanslutning
- Testa med olika operatörer/perioder

## Exempel på användning

### Batch-generering för alla operatörer

```php
<?php
require_once 'BonusPDFReport.php';

// Hämta alla unika operatörer för perioden
$period = '2026-02';
$stmt = $pdo->prepare("
    SELECT DISTINCT COALESCE(op1, op2, op3) as operator_id
    FROM rebotling_ibc
    WHERE DATE_FORMAT(datum, '%Y-%m') = :period
    AND bonus_poang IS NOT NULL
");
$stmt->execute(['period' => $period]);
$operators = $stmt->fetchAll(PDO::FETCH_COLUMN);

$report = new BonusPDFReport($pdo);

foreach ($operators as $operator_id) {
    try {
        $filepath = $report->generateOperatorMonthlyReport($operator_id, $period);
        echo "✅ Generated: $filepath\n";
    } catch (Exception $e) {
        echo "❌ Failed for operator $operator_id: " . $e->getMessage() . "\n";
    }
}
```

### E-post med PDF-bilaga

```php
<?php
require_once 'BonusPDFReport.php';

use PHPMailer\PHPMailer\PHPMailer;

$report = new BonusPDFReport($pdo);
$filepath = $report->generateOperatorMonthlyReport(123, '2026-02');

$mail = new PHPMailer();
$mail->addAttachment($filepath);
$mail->Subject = "Din bonusrapport för 2026-02";
$mail->Body = "Se bifogad PDF för detaljerad bonusrapport.";
$mail->send();
```

## Prestanda

- **Generering**: ~2-5 sekunder för en månads data (100-200 cykler)
- **Filstorlek**: ~50-200 KB beroende på datamängd
- **Minneskrav**: ~10-20 MB PHP memory

## Framtida förbättringar

- [ ] Lägg till diagram/grafer (Chart.js → FPDF konvertering)
- [ ] Jämför flera operatörer i samma rapport
- [ ] Exportera till Excel-format
- [ ] Schemalägg automatisk rapportgenerering
- [ ] E-posta rapporter direkt till operatörer
- [ ] Lagra rapporter i databas med metadata
- [ ] Lägg till digital signatur för godkännande
