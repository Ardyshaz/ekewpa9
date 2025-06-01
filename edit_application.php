<?php
// session_start() is already in config/database.php, which is included below.
require_once 'config/database.php'; // This correctly includes session_start() and $pdo object
// require_once 'config/functions.php'; // REMOVE or COMMENT OUT THIS LINE if you don't have this file

// Pastikan hanya admin yang boleh mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$application = null;
$assets = [];
$error_message = '';
$success_message = '';

// Dapatkan application_id dari URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $application_id = $_GET['id'];

    try {
        // Ambil data permohonan
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = :application_id");
        $stmt->bindParam(":application_id", $application_id, PDO::PARAM_INT);
        $stmt->execute();
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            $error_message = "Permohonan tidak ditemui.";
        } else {
            // Ambil data aset yang berkaitan
            $stmt = $pdo->prepare("SELECT * FROM assets WHERE application_id = :application_id");
            $stmt->bindParam(":application_id", $application_id, PDO::PARAM_INT);
            $stmt->execute();
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "Ralat pangkalan data: " . $e->getMessage();
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- Handle POST request untuk mengemas kini permohonan ----------
    $application_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $application_number = filter_input(INPUT_POST, 'application_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $applicant_name = filter_input(INPUT_POST, 'applicant_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $applicant_position = filter_input(INPUT_POST, 'applicant_position', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $issuer_name = filter_input(INPUT_POST, 'issuer_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $location_of_use = filter_input(INPUT_POST, 'location_of_use', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Mulakan transaksi untuk memastikan semua kemas kini berjaya atau tiada
    $pdo->beginTransaction();
    try {
        // Kemas kini data permohonan
        $stmt = $pdo->prepare("UPDATE applications SET
            application_number = :application_number, applicant_name = :applicant_name, applicant_position = :applicant_position, department = :department,
            issuer_name = :issuer_name, purpose = :purpose, location_of_use = :location_of_use, updated_at = CURRENT_TIMESTAMP
            WHERE application_id = :application_id");
        $stmt->bindParam(':application_number', $application_number);
        $stmt->bindParam(':applicant_name', $applicant_name);
        $stmt->bindParam(':applicant_position', $applicant_position);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':issuer_name', $issuer_name);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':location_of_use', $location_of_use);
        $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
        $stmt->execute();

        // Kemas kini aset: Cara paling mudah adalah padam semua yang sedia ada, kemudian masukkan semula
        $stmt = $pdo->prepare("DELETE FROM assets WHERE application_id = :application_id");
        $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
        $stmt->execute();

        // Masukkan aset yang baru diserahkan
        if (isset($_POST['assets']) && is_array($_POST['assets'])) {
            $stmt = $pdo->prepare("INSERT INTO assets (application_id, serial_number, description, loan_date, expected_return_date, status) VALUES (:application_id, :serial_number, :description, :loan_date, :expected_return_date, 'pending')");
            foreach ($_POST['assets'] as $asset) {
                // Ensure the keys match the input names from the form
                $serial_number = filter_var($asset['serial_number'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $description = filter_var($asset['description'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $loan_date = filter_var($asset['loan_date'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $expected_return_date = filter_var($asset['expected_return_date'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                // Status is set to 'pending' as default for new/updated assets here,
                // actual asset status will be updated on approval or return from view_application.php

                // Only insert if essential fields are not empty
                if (!empty($serial_number) && !empty($description) && !empty($loan_date) && !empty($expected_return_date)) {
                    $stmt->bindParam(':application_id', $application_id, PDO::PARAM_INT);
                    $stmt->bindParam(':serial_number', $serial_number);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':loan_date', $loan_date);
                    $stmt->bindParam(':expected_return_date', $expected_return_date);
                    $stmt->execute();
                }
            }
        }

        $pdo->commit();
        $success_message = "Permohonan dan aset berjaya dikemas kini.";

        // Muat semula data selepas kemas kini untuk memaparkan perubahan
        // This block is for displaying the updated data on the same page after POST
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE application_id = :application_id");
        $stmt->bindParam(":application_id", $application_id, PDO::PARAM_INT);
        $stmt->execute();
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM assets WHERE application_id = :application_id");
        $stmt->bindParam(":application_id", $application_id, PDO::PARAM_INT);
        $stmt->execute();
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Ralat semasa mengemas kini permohonan: " . $e->getMessage();
    }
} else {
    // If no ID is provided on initial GET request
    if (!isset($_GET['id'])) {
        $error_message = "ID permohonan tidak dinyatakan.";
    }
}

// Format data aset ke JSON untuk JavaScript
$assets_json = json_encode($assets);

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Permohonan KEW.PA-9</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
            padding: 2rem 0;
        }
        .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .rounded-xl {
            border-radius: 1rem;
        }
        .transition-all {
            transition-property: all;
            transition-duration: 300ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover\:scale-\[1\.01\]:hover {
            transform: scale(1.01);
        }
        .bg-clip-text {
            -webkit-background-clip: text;
            background-clip: text;
        }
        .text-transparent {
            color: transparent;
        }
        /* Custom styles for form layout */
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem; /* sm */
            font-weight: 600; /* semibold */
            color: #4a5568; /* gray-700 */
            margin-bottom: 0.5rem;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e0; /* gray-300 */
            border-radius: 0.5rem; /* rounded-lg */
            font-size: 1rem; /* base */
            color: #2d3748; /* gray-900 */
            transition: all 0.2s ease-in-out;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4299e1; /* blue-500 */
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5); /* ring-3 blue-500 */
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary {
            background-color: #4c51bf; /* indigo-700 */
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #5a67d8; /* indigo-600 */
        }
        .btn-secondary {
            background-color: #a0aec0; /* gray-500 */
            color: #fff;
        }
        .btn-secondary:hover {
            background-color: #718096; /* gray-600 */
        }
        .error-message {
            background-color: #fee2e2; /* red-100 */
            color: #991b1b; /* red-800 */
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #ef4444; /* red-500 */
        }
        .success-message {
            background-color: #d1fae5; /* green-100 */
            color: #065f46; /* green-800 */
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #34d399; /* green-500 */
        }
        .assets-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .assets-table th, .assets-table td {
            border: 1px solid #e2e8f0; /* gray-200 */
            padding: 0.75rem;
            text-align: left;
        }
        .assets-table th {
            background-color: #f7fafc; /* gray-50 */
            font-weight: 600;
        }
        .remove-asset-button {
            background-color: #ef4444; /* red-500 */
            color: #fff;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .remove-asset-button:hover {
            background-color: #dc2626; /* red-600 */
        }
    </style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <main>
    <div class="max-w-4xl mx-auto bg-white p-10 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01]">
        <h2 class="text-4xl font-bold text-gray-900 mb-8 text-center bg-clip-text text-transparent bg-gradient-to-r from-green-600 to-teal-700">Edit Permohonan KEW.PA-9</h2>

        <?php if ($error_message): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-message">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($application): ?>
            <form id="editApplicationForm" method="POST" action="">
                <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['application_id']); ?>">

                <div class="form-group">
                    <label for="application_number">No. Permohonan:</label>
                    <input type="text" id="application_number" name="application_number" value="<?php echo htmlspecialchars($application['application_number']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
                </div>

                <div class="form-group">
                    <label for="applicant_name">Nama Pemohon:</label>
                    <input type="text" id="applicant_name" name="applicant_name" value="<?php echo htmlspecialchars($application['applicant_name']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
                </div>

                <div class="form-group">
                    <label for="applicant_position">Jawatan Pemohon:</label>
                    <input type="text" id="applicant_position" name="applicant_position" value="<?php echo htmlspecialchars($application['applicant_position']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
                </div>

                <div class="form-group">
                    <label for="department">Bahagian:</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($application['department']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
                </div>

                <div class="form-group">
                    <label for="issuer_name">Nama Pengeluar:</label>
                    <input type="text" id="issuer_name" name="issuer_name" value="<?php echo htmlspecialchars($application['issuer_name']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
                </div>

                <div class="form-group">
                    <label for="purpose">Tujuan:</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm"><?php echo htmlspecialchars($application['purpose']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="location_of_use">Tempat Digunakan:</label>
                    <textarea id="location_of_use" name="location_of_use" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm"><?php echo htmlspecialchars($application['location_of_use']); ?></textarea>
                </div>

                <h3 class="text-xl font-bold text-gray-800 mb-4 mt-6">Butiran Aset</h3>
                <div class="overflow-x-auto mb-4">
                    <table class="assets-table min-w-full bg-white border border-gray-300 rounded-lg overflow-hidden">
                        <thead>
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bil.</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Siri Pendaftaran</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Keterangan Aset</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tarikh Dipinjam</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Dijangka Pulang</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="assetsTableBody" class="divide-y divide-gray-200">
                        </tbody>
                    </table>
                </div>
                <button type="button" id="addAsset" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-blue-300">Tambah Aset</button>
                <br><br>
                <div class="mt-8 text-center flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-green-300">Kemaskini Permohonan</button>
                    <a href="view_application.php?id=<?php echo htmlspecialchars($application['application_id']); ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-gray-300">Kembali</a>
                </div>
            </form>
        <?php elseif (!$error_message): ?>
            <p class="text-center text-gray-600 text-lg">Sila berikan ID permohonan yang sah di URL (contoh: `edit_application.php?id=1`).</p>
        <?php endif; ?>
    </div>
    </main>
    <?php include_once 'includes/footer.php'; ?>
    <script>
        // The script from form-dynamic-assets.js needs to be adapted for edit_application.php
        // It needs to populate the table with existing assets first.
        document.addEventListener('DOMContentLoaded', function() {
            const addAssetButton = document.getElementById('addAsset');
            const assetsTableBody = document.getElementById('assetsTableBody');
            const editApplicationForm = document.getElementById('editApplicationForm');
            // No messageArea needed as messages are handled by PHP on initial load for edit.php

            // Function to create a new asset row
            function createAssetRow(asset = {}) {
                if (!assetsTableBody) {
                    console.error("assetsTableBody is null inside createAssetRow. Cannot add row.");
                    return;
                }
                const rowCount = assetsTableBody.querySelectorAll('tr').length + 1;
                const newRow = document.createElement('tr');
                newRow.className = 'border-b border-gray-200';
                newRow.innerHTML = `
                    <td class="py-2 px-4 text-sm text-gray-700">${rowCount}.</td>
                    <td class="py-2 px-4">
                        <input type="text" name="assets[${rowCount - 1}][serial_number]" value="${asset.serial_number || ''}" placeholder="No. Siri Pendaftaran" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </td>
                    <td class="py-2 px-4">
                        <input type="text" name="assets[${rowCount - 1}][description]" value="${asset.description || ''}" placeholder="Keterangan Aset" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </td>
                    <td class="py-2 px-4">
                        <input type="date" name="assets[${rowCount - 1}][loan_date]" value="${asset.loan_date || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </td>
                    <td class="py-2 px-4">
                        <input type="date" name="assets[${rowCount - 1}][expected_return_date]" value="${asset.expected_return_date || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </td>
                    <td class="py-2 px-4 text-center">
                        <button type="button" class="remove-asset-button bg-red-500 hover:bg-red-600 text-white text-xs py-1 px-2 rounded-md transition-colors">Buang</button>
                    </td>
                `;
                assetsTableBody.appendChild(newRow);
                setupRemoveButton(newRow);
            }

            // Function to set up remove button listener for a given row
            function setupRemoveButton(row) {
                const removeButton = row.querySelector('.remove-asset-button');
                if (removeButton) {
                    removeButton.addEventListener('click', function() {
                        row.remove();
                        updateRowNumbers(); // Re-number rows after removal
                    });
                }
            }

            // Function to update row numbers after an asset is removed
            function updateRowNumbers() {
                const rows = assetsTableBody.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    row.querySelector('td:first-child').textContent = `${index + 1}.`;
                    // Also update the name attributes for correct indexing
                    row.querySelectorAll('input').forEach(input => {
                        const nameAttr = input.getAttribute('name');
                        if (nameAttr) {
                            input.setAttribute('name', nameAttr.replace(/assets\[\d+\]/, `assets[${index}]`));
                        }
                    });
                });
            }

            // Add event listener for the "Tambah Aset" button
            if (addAssetButton) {
                addAssetButton.addEventListener('click', function() {
                    createAssetRow();
                });
            }

            // Populate table with existing assets on page load
            const existingAssets = <?php echo $assets_json; ?>;
            if (existingAssets && existingAssets.length > 0) {
                existingAssets.forEach(asset => createAssetRow(asset));
            } else {
                // If no existing assets, add one empty row
                createAssetRow();
            }

            // Handle form submission for edit_application.php
            if (editApplicationForm) {
                editApplicationForm.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const formData = new FormData(editApplicationForm);
                    // Append dynamic asset data
                    const assetsData = [];
                    if (assetsTableBody) {
                        assetsTableBody.querySelectorAll('tr').forEach(row => {
                            const asset = {};
                            row.querySelectorAll('input').forEach(input => {
                                const name = input.name.match(/\[(\w+)\]$/);
                                if (name && name[1]) {
                                    asset[name[1]] = input.value;
                                }
                            });
                            // Only add assets that have at least a serial number, assuming it's required
                            if (asset.serial_number && asset.serial_number.trim() !== '') {
                                assetsData.push(asset);
                            }
                        });
                    }
                    formData.append('assets_json', JSON.stringify(assetsData)); // Send assets as JSON string

                    // Disable submit button and show loading state
                    const submitButton = editApplicationForm.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.textContent = 'Mengemas kini...';
                    submitButton.classList.add('opacity-75', 'cursor-not-allowed');

                    // No specific messageArea here, PHP handles messages on full page reload
                    // You could add an AJAX response here if you wanted partial page updates.
                    // For now, let's submit normally and let PHP handle the redirect/display.

                    // Since this is an edit form, letting it submit normally back to itself
                    // with POST to show success/error message from PHP is simpler.
                    // If you want AJAX submission for edit_application.php as well, you'd
                    // implement similar fetch logic as in process.php's form-dynamic-assets.js.

                    // For now, let's just re-enable the button and allow the form to submit.
                    this.submit(); // Allows the form to submit normally after collecting assets_json

                    submitButton.disabled = false;
                    submitButton.textContent = 'Kemaskini Permohonan';
                    submitButton.classList.remove('opacity-75', 'cursor-not-allowed');
                });
            }
        });
    </script>
</body>
</html>