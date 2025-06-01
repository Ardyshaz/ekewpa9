<?php
// cetak.php
include_once 'config/database.php';

$application = null;
$assets = [];
$signatures = [];
$error_message = '';

if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null) {
        $error_message = 'ID permohonan tidak sah diberikan.';
    } else {
        try {
            // Fetch application data
            $stmtApp = $pdo->prepare("SELECT application_id, application_number, applicant_name, applicant_position, department, issuer_name, purpose, location_of_use, status, created_at, updated_at FROM applications WHERE application_id = :id");
            $stmtApp->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtApp->execute();
            $application = $stmtApp->fetch();

            if (!$application) {
                $error_message = 'Permohonan tidak ditemui.';
            } else {
                // Fetch associated assets
                $stmtAssets = $pdo->prepare("SELECT * FROM assets WHERE application_id = :id ORDER BY asset_id ASC");
                $stmtAssets->bindParam(':id', $id, PDO::PARAM_INT);
                $stmtAssets->execute();
                $assets = $stmtAssets->fetchAll();

                // Fetch associated signatures
                $stmtSignatures = $pdo->prepare("SELECT * FROM signatures WHERE application_id = :id ORDER BY signature_id ASC");
                $stmtSignatures->bindParam(':id', $id, PDO::PARAM_INT);
                $stmtSignatures->execute();
                $signatures_raw = $stmtSignatures->fetchAll();

                // Organize signatures by role for easier access
                foreach ($signatures_raw as $sig) {
                    $signatures[$sig['role']] = $sig;
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Ralat pangkalan data: ' . $e->getMessage();
        }
    }
} else {
    $error_message = 'Tiada ID permohonan dinyatakan.';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Borang KEW.PA-9</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Print Styles */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
            font-size: 12px; /* Base font size for print */
        }
        .kewpa9-container {
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            margin: 0 auto;
            padding: 15mm 20mm; /* Adjust as needed for margins */
            box-sizing: border-box;
            background: #fff;
        }

        /* Print Specific Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .kewpa9-container {
                margin: 0;
                padding: 0; /* Remove padding for print to let browser handle margins */
                width: 100%;
                min-height: auto;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
            /* Ensure borders are visible */
            table, th, td {
                border-color: #000 !important;
            }
            /* Adjust font size for print */
            body { font-size: 10pt; }
            .header-section .kewpa9-label { font-size: 20pt; }
            .header-section .app-no { font-size: 10pt; }
            .form-title { font-size: 14pt; }
            .info-grid .label, .info-grid .value { font-size: 10pt; }
            .asset-table th, .asset-table td { font-size: 8pt; }
            .signature-block p, .signature-block span { font-size: 8pt; }
            .signature-block .name { font-size: 9pt; font-weight: bold; }
            .signature-block .position { font-size: 7pt; }
            .signature-block .date { font-size: 7pt; }
        }

        /* KEW.PA-9 Specific Layout */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        .header-section .left-info {
            text-align: left;
            font-size: 10px;
        }
        .header-section .right-info {
            text-align: right;
            font-size: 10px;
        }
        .header-section .kewpa9-label {
            font-size: 28px;
            font-weight: bold;
            line-height: 1;
        }
        .header-section .app-no {
            font-weight: bold;
            margin-top: 5px;
        }
        .form-title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns */
            gap: 5px 15px; /* Row gap, column gap */
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            align-items: baseline;
            line-height: 1.5;
            flex-wrap: nowrap; /* Prevent wrapping of label/value */
        }
        .info-item .label {
            font-weight: bold;
            flex-shrink: 0; /* Prevent label from shrinking */
            padding-right: 5px;
            white-space: nowrap; /* Keep label on one line */
        }
        .info-item .value {
            flex-grow: 1;
            border-bottom: 1px dashed #000; /* For blank lines */
            padding-left: 5px;
            min-width: 50px; /* Ensure some space even if value is empty */
        }
        .asset-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 30px;
            font-size: 10px;
        }
        .asset-table th, .asset-table td {
            border: 1px solid #000;
            padding: 4px 3px; /* Smaller padding */
            text-align: left;
            vertical-align: top;
        }
        .asset-table th {
            font-weight: bold;
            text-align: center;
            white-space: nowrap; /* Prevent header text from wrapping too much */
        }
        .asset-table td {
            height: 20px; /* Ensure minimum row height */
        }
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        .signature-block {
            border: 1px solid #000;
            padding: 10px;
            min-height: 120px; /* Adjust height to match PDF */
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            text-align: center;
            font-size: 12px;
            position: relative;
        }
        .signature-block .signature-label {
            position: absolute;
            top: 5px;
            left: 5px;
            font-weight: bold;
            font-size: 11px;
        }
        .signature-block img {
            max-width: 100px;
            max-height: 50px;
            object-fit: contain;
            margin-bottom: 5px;
            margin-top: auto; /* Push signature to bottom */
        }
        .signature-block .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin-top: 5px; /* Closer to signature */
            margin-bottom: 2px;
        }
        .signature-block .name-label, .signature-block .position-label, .signature-block .date-label {
            font-size: 9px;
            text-align: left;
            width: 80%;
            margin-bottom: 1px;
        }
        .signature-block .name-label span, .signature-block .position-label span, .signature-block .date-label span {
            font-weight: normal;
            border-bottom: 1px dashed #000;
            display: inline-block;
            min-width: 50px; /* Ensure line for empty values */
            padding-left: 3px;
        }
        .signature-block .name-label {
            font-weight: bold;
            margin-top: 5px;
        }
        .signature-block .position-label {
            margin-top: 2px;
        }
        .signature-block .date-label {
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <?php if ($error_message): ?>
        <div class="kewpa9-container text-red-700 text-center font-bold">
            <p><?php echo $error_message; ?></p>
            <button onclick="window.close()" class="no-print mt-4 bg-blue-500 text-white py-2 px-4 rounded">Tutup Halaman</button>
        </div>
    <?php elseif ($application): ?>
    <div class="kewpa9-container">
        <div class="header-section">
            <div class="left-info">
                Pekeliling Perbendaharaan Malaysia<br>
                AM 2.4 Lampiran A
            </div>
            <div class="right-info">
                <span class="kewpa9-label">KEW.PA-9</span><br>
                No. Permohonan: <span class="app-no"><?php echo htmlspecialchars($application['application_number']); ?></span>
            </div>
        </div>

        <h1 class="form-title">BORANG PERMOHONAN PERGERAKAN/ PINJAMAN ASET ALIH</h1>

        <div class="info-grid">
            <div class="info-item">
                <span class="label">Nama Pemohon:</span>
                <span class="value"><?php echo htmlspecialchars($application['applicant_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Tujuan :</span>
                <span class="value"><?php echo htmlspecialchars($application['purpose']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Jawatan:</span>
                <span class="value"><?php echo htmlspecialchars($application['applicant_position']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Tempat Digunakan:</span>
                <span class="value"><?php echo htmlspecialchars($application['location_of_use']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Bahagian:</span>
                <span class="value"><?php echo htmlspecialchars($application['department']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Nama Pengeluar:</span>
                <span class="value"><?php echo htmlspecialchars($application['issuer_name'] ?: '-'); ?></span>
            </div>
        </div>

        <table class="asset-table">
            <thead>
                <tr>
                    <th style="width: 5%;">Bil.</th>
                    <th style="width: 15%;">No. Siri<br>Pendaftaran</th>
                    <th style="width: 25%;">Keterangan<br>Aset</th>
                    <th style="width: 10%;">Tarikh<br>Dipinjam</th>
                    <th style="width: 10%;">Dijangka<br>Pulang</th>
                    <th style="width: 10%;">(Lulus/<br>Tidak<br>Lulus)</th>
                    <th style="width: 10%;">Tarikh<br>Dipulangkan</th>
                    <th style="width: 5%;">Diterima</th>
                    <th style="width: 10%;">Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assets)): ?>
                    <?php $bil = 1; foreach ($assets as $asset): ?>
                        <tr>
                            <td><?php echo $bil++; ?>.</td>
                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                            <td><?php echo htmlspecialchars($asset['description']); ?></td>
                            <td><?php echo htmlspecialchars($asset['loan_date']); ?></td>
                            <td><?php echo htmlspecialchars($asset['expected_return_date']); ?></td>
                            <td>
                                <?php
                                // Map asset status to "Lulus" / "Tidak Lulus" for the printout
                                switch ($asset['status']) {
                                    case 'approved': echo 'Lulus'; break;
                                    case 'rejected': echo 'Tidak Lulus'; break;
                                    case 'pending': echo 'Menunggu'; break; // Or just blank/dash if not applicable
                                    case 'returned': echo 'Lulus'; break; // Assuming returned means it was approved
                                    default: echo '-'; break;
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($asset['actual_return_date'] ?: '-'); ?></td>
                            <td><?php echo ($asset['actual_return_date'] ? 'Ya' : '-'); ?></td> <td><?php echo htmlspecialchars($asset['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Tiada butiran aset untuk permohonan ini.</td>
                    </tr>
                <?php endif; ?>
                <?php
                // Add empty rows if less than a certain number (e.g., 5) to match PDF structure
                $min_rows = 5; // Adjust based on how many empty rows you want to ensure
                if (count($assets) < $min_rows) {
                    for ($i = count($assets); $i < $min_rows; $i++) {
                        echo '<tr>';
                        echo '<td>' . ($i + 1) . '.</td>';
                        echo '<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>

        <div class="signature-grid">
            <div class="signature-block">
                <p class="signature-label">(Tandatangan Peminjam)</p>
                <?php if (isset($signatures['applicant']) && $signatures['applicant']['signature_data']): ?>
                    <img src="<?php echo htmlspecialchars($signatures['applicant']['signature_data']); ?>" alt="Tandatangan Peminjam">
                <?php else: ?>
                    <div style="height: 50px; width: 100%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">Tiada Tandatangan</div>
                <?php endif; ?>
                <div class="signature-line"></div>
                <p class="name-label">Nama: <span><?php echo htmlspecialchars($signatures['applicant']['name'] ?? ''); ?></span></p>
                <p class="position-label">Jawatan: <span><?php echo htmlspecialchars($signatures['applicant']['position'] ?? ''); ?></span></p>
                <p class="date-label">Tarikh: <span><?php echo htmlspecialchars($signatures['applicant']['signature_date'] ?? ''); ?></span></p>
            </div>

            <div class="signature-block">
                <p class="signature-label">(Tandatangan Pelulus)</p>
                <?php if (isset($signatures['approver']) && $signatures['approver']['signature_data']): ?>
                    <img src="<?php echo htmlspecialchars($signatures['approver']['signature_data']); ?>" alt="Tandatangan Pelulus">
                <?php else: ?>
                    <div style="height: 50px; width: 100%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">Tiada Tandatangan</div>
                <?php endif; ?>
                <div class="signature-line"></div>
                <p class="name-label">Nama: <span><?php echo htmlspecialchars($signatures['approver']['name'] ?? ''); ?></span></p>
                <p class="position-label">Jawatan: <span><?php echo htmlspecialchars($signatures['approver']['position'] ?? ''); ?></span></p>
                <p class="date-label">Tarikh: <span><?php echo htmlspecialchars($signatures['approver']['signature_date'] ?? ''); ?></span></p>
            </div>

            <div class="signature-block">
                <p class="signature-label">(Tandatangan Pemulang)</p>
                <?php if (isset($signatures['returner']) && $signatures['returner']['signature_data']): ?>
                    <img src="<?php echo htmlspecialchars($signatures['returner']['signature_data']); ?>" alt="Tandatangan Pemulang">
                <?php else: ?>
                    <div style="height: 50px; width: 100%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">Tiada Tandatangan</div>
                <?php endif; ?>
                <div class="signature-line"></div>
                <p class="name-label">Nama: <span><?php echo htmlspecialchars($signatures['returner']['name'] ?? ''); ?></span></p>
                <p class="position-label">Jawatan: <span><?php echo htmlspecialchars($signatures['returner']['position'] ?? ''); ?></span></p>
                <p class="date-label">Tarikh: <span><?php echo htmlspecialchars($signatures['returner']['signature_date'] ?? ''); ?></span></p>
            </div>

            <div class="signature-block">
                <p class="signature-label">(Tandatangan Penerima)</p>
                <?php if (isset($signatures['receiver']) && $signatures['receiver']['signature_data']): ?>
                    <img src="<?php echo htmlspecialchars($signatures['receiver']['signature_data']); ?>" alt="Tandatangan Penerima">
                <?php else: ?>
                    <div style="height: 50px; width: 100%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666;">Tiada Tandatangan</div>
                <?php endif; ?>
                <div class="signature-line"></div>
                <p class="name-label">Nama: <span><?php echo htmlspecialchars($signatures['receiver']['name'] ?? ''); ?></span></p>
                <p class="position-label">Jawatan: <span><?php echo htmlspecialchars($signatures['receiver']['position'] ?? ''); ?></span></p>
                <p class="date-label">Tarikh: <span><?php echo htmlspecialchars($signatures['receiver']['signature_date'] ?? ''); ?></span></p>
            </div>
        </div>

        <div class="no-print mt-8 text-center">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                Cetak Borang
            </button>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-gray-300 ml-4">
                Tutup
            </button>
        </div>

    </div>
    <?php endif; ?>
</body>
</html>
