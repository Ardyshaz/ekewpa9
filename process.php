<?php
// process.php
include_once 'config/database.php'; // Includes database connection and session_start()

$error_message = '';
$success_message = '';

// Check if it's an AJAX request (from form-dynamic-assets.js submission)
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate main application data
    $applicant_name = filter_input(INPUT_POST, 'applicant_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $applicant_position = filter_input(INPUT_POST, 'applicant_position', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $issuer_name = filter_input(INPUT_POST, 'issuer_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $purpose = filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $location_of_use = filter_input(INPUT_POST, 'location_of_use', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Get assets data as JSON string and decode it
    $assets_json = $_POST['assets_json'] ?? '[]';
    $assets_data = json_decode($assets_json, true);

    // Get signature data
    $applicant_signature_data = $_POST['applicant_signature_data'] ?? null;

    // Basic server-side validation
    if (empty($applicant_name) || empty($applicant_position) || empty($department) || empty($purpose) || empty($location_of_use) || empty($assets_data) || empty($applicant_signature_data)) {
        if ($is_ajax_request) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Sila lengkapkan semua medan yang diperlukan, termasuk butiran aset dan tandatangan.']);
            exit();
        }
        $error_message = 'Sila lengkapkan semua medan yang diperlukan, termasuk butiran aset dan tandatangan.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Generate unique application number (e.g., PA-YYYY-XXXX)
            $year = date('Y');
            $stmtCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE YEAR(created_at) = '$year'");
            $count = $stmtCount->fetchColumn();
            $application_number = 'PA-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            // 2. Insert into applications table
            $stmtApp = $pdo->prepare("INSERT INTO applications (application_number, applicant_name, applicant_position, department, issuer_name, purpose, location_of_use, status) VALUES (:application_number, :applicant_name, :applicant_position, :department, :issuer_name, :purpose, :location_of_use, 'submitted')");
            $stmtApp->bindParam(':application_number', $application_number);
            $stmtApp->bindParam(':applicant_name', $applicant_name);
            $stmtApp->bindParam(':applicant_position', $applicant_position);
            $stmtApp->bindParam(':department', $department);
            $stmtApp->bindParam(':issuer_name', $issuer_name);
            $stmtApp->bindParam(':purpose', $purpose);
            $stmtApp->bindParam(':location_of_use', $location_of_use);
            $stmtApp->execute();
            $application_id = $pdo->lastInsertId();

            // 3. Insert into assets table
            $stmtAsset = $pdo->prepare("INSERT INTO assets (application_id, serial_number, description, loan_date, expected_return_date, status) VALUES (:application_id, :serial_number, :description, :loan_date, :expected_return_date, 'pending')");
            foreach ($assets_data as $asset) {
                if (!empty($asset['serial_number']) && !empty($asset['description']) && !empty($asset['loan_date']) && !empty($asset['expected_return_date'])) {
                    $stmtAsset->bindParam(':application_id', $application_id, PDO::PARAM_INT);
                    $stmtAsset->bindParam(':serial_number', $asset['serial_number']);
                    $stmtAsset->bindParam(':description', $asset['description']);
                    $stmtAsset->bindParam(':loan_date', $asset['loan_date']);
                    $stmtAsset->bindParam(':expected_return_date', $asset['expected_return_date']);
                    $stmtAsset->execute();
                }
            }

            // 4. Insert applicant signature
            $stmtSig = $pdo->prepare("INSERT INTO signatures (application_id, role, name, position, signature_date, signature_data) VALUES (:application_id, 'applicant', :name, :position, :signature_date, :signature_data)");
            $stmtSig->bindParam(':application_id', $application_id, PDO::PARAM_INT);
            $stmtSig->bindParam(':name', $applicant_name);
            $stmtSig->bindParam(':position', $applicant_position);
            $stmtSig->bindValue(':signature_date', date('Y-m-d'));
            $stmtSig->bindParam(':signature_data', $applicant_signature_data);
            $stmtSig->execute();

            $pdo->commit();

            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Permohonan anda berjaya dihantar!', 'application_id' => $application_id]);
                exit();
            }
            $success_message = 'Permohonan anda berjaya dihantar! No. Permohonan: ' . $application_number;
            // Redirect to view page after successful submission
            header('Location: view_application.php?id=' . $application_id);
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Ralat pangkalan data: ' . $e->getMessage()]);
                exit();
            }
            $error_message = 'Ralat pangkalan data: ' . $e->getMessage();
        }
    }
}

// Only include header/footer if it's not an AJAX request
if (!$is_ajax_request) {
    include_once 'includes/header.php';
}
?>

<?php if (!$is_ajax_request): // Only render HTML if not an AJAX request ?>
<div class="max-w-3xl mx-auto bg-white p-10 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01]">
    <h2 class="text-4xl font-bold text-gray-900 mb-8 text-center bg-clip-text text-transparent bg-gradient-to-r from-green-600 to-teal-700">Borang Permohonan KEW.PA-9</h2>
    <p class="text-center text-sm text-gray-500 mb-6">Sila lengkapkan borang di bawah untuk memohon pergerakan/pinjaman aset alih.</p>

    <div id="message-area">
        <?php if ($error_message): ?>
            <div class="p-4 mb-6 rounded-lg bg-red-100 text-red-700 border border-red-400 shadow-md flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-medium"><?php echo $error_message; ?></p>
            </div>
        <?php elseif ($success_message): ?>
            <div class="p-4 mb-6 rounded-lg bg-green-100 text-green-700 border border-green-400 shadow-md flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="font-medium"><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <form id="applicationForm" method="POST" action="process.php" class="space-y-6">
        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Maklumat Pemohon</h3>
            <div>
                <label for="applicant_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Pemohon <span class="text-red-500">*</span></label>
                <input type="text" id="applicant_name" name="applicant_name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
            </div>
            <div class="mt-4">
                <label for="applicant_position" class="block text-gray-700 text-sm font-semibold mb-2">Jawatan <span class="text-red-500">*</span></label>
                <input type="text" id="applicant_position" name="applicant_position" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
            </div>
            <div class="mt-4">
                <label for="department" class="block text-gray-700 text-sm font-semibold mb-2">Bahagian <span class="text-red-500">*</span></label>
                <input type="text" id="department" name="department" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
            </div>
            <div class="mt-4">
                <label for="issuer_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Pengeluar (Pilihan)</label>
                <input type="text" id="issuer_name" name="issuer_name"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
            </div>
        </div>

        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Tujuan & Tempat Penggunaan</h3>
            <div>
                <label for="purpose" class="block text-gray-700 text-sm font-semibold mb-2">Tujuan <span class="text-red-500">*</span></label>
                <textarea id="purpose" name="purpose" rows="3" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm"></textarea>
            </div>
            <div class="mt-4">
                <label for="location_of_use" class="block text-gray-700 text-sm font-semibold mb-2">Tempat Digunakan <span class="text-red-500">*</span></label>
                <textarea id="location_of_use" name="location_of_use" rows="3" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm"></textarea>
            </div>
        </div>

        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Butiran Aset</h3>
            <div class="overflow-x-auto mb-4">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg overflow-hidden">
                    <thead class="bg-gray-100">
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
            <button type="button" id="addAsset" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-blue-300">
                Tambah Aset
            </button>
        </div>

        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Tandatangan Peminjam <span class="text-red-500">*</span></h3>
            <canvas id="applicantSignatureCanvas" class="border border-gray-400 rounded-lg w-full bg-white cursor-crosshair" width="400" height="150"></canvas>
            <input type="hidden" id="applicant_signature_data" name="applicant_signature_data">
            <div class="mt-4 flex justify-end">
                <button type="button" id="clearApplicantSignature" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-red-300">
                    Padam Tandatangan
                </button>
            </div>
        </div>

        <div class="mt-8 text-center">
            <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-600 hover:from-green-600 hover:to-blue-700 text-white font-bold py-3 px-12 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-green-300">
                Hantar Permohonan
            </button>
        </div>
    </form>

    <div class="mt-8 text-center">
        <a href="index.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-gray-300">
            Kembali ke Halaman Utama
        </a>
    </div>
</div>

<?php
include_once 'includes/footer.php';
?>

<script>
// This script handles the applicant's signature pad directly on process.php
// It is separate from admin-actions.js to keep concerns separated.
document.addEventListener('DOMContentLoaded', function() {
    const applicantSignatureCanvas = document.getElementById('applicantSignatureCanvas');
    const clearApplicantSignatureBtn = document.getElementById('clearApplicantSignature');
    const applicantSignatureDataInput = document.getElementById('applicant_signature_data');
    let applicantCtx;
    let drawing = false;

    // Helper function to resize canvas
    function resizeCanvas(canvas, context) {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#000';
    }

    // Helper function to add drawing listeners
    function addDrawingListeners(canvas, context) {
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('mousemove', draw);

        // For touch devices
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);
        canvas.addEventListener('touchmove', draw);

        function startDrawing(e) {
            drawing = true;
            context.beginPath();
            const pos = getMousePos(canvas, e);
            context.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function stopDrawing() {
            drawing = false;
            saveSignatureData(); // Save signature data when drawing stops
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getMousePos(canvas, e);
            context.lineTo(pos.x, pos.y);
            context.stroke();
            e.preventDefault();
        }

        function getMousePos(canvas, event) {
            const rect = canvas.getBoundingClientRect();
            let clientX, clientY;

            if (event.touches) {
                clientX = event.touches[0].clientX;
                clientY = event.touches[0].clientY;
            } else {
                clientX = event.clientX;
                clientY = event.clientY;
            }

            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }
    }

    // Helper function to clear signature
    function clearSignature(canvas, context) {
        context.clearRect(0, 0, canvas.width, canvas.height);
        applicantSignatureDataInput.value = ''; // Clear hidden input
    }

    // Function to save signature data to hidden input
    function saveSignatureData() {
        applicantSignatureDataInput.value = applicantSignatureCanvas.toDataURL();
    }

    if (applicantSignatureCanvas) {
        applicantCtx = applicantSignatureCanvas.getContext('2d');
        resizeCanvas(applicantSignatureCanvas, applicantCtx);
        addDrawingListeners(applicantSignatureCanvas, applicantCtx);

        clearApplicantSignatureBtn.addEventListener('click', function() {
            clearSignature(applicantSignatureCanvas, applicantCtx);
        });
    }

    // Adjust canvas size on window resize
    window.addEventListener('resize', function() {
        if (applicantSignatureCanvas) resizeCanvas(applicantSignatureCanvas, applicantCtx);
    });
});
</script>

<?php endif; // This endif closes the main if (!$is_ajax_request): block ?>
