<?php
// view_application.php
include_once 'config/database.php';

$application = null;
$assets = [];
$signatures = [];
$error_message = '';
$success_message = '';
// Determine if the current user is an admin based on session
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Check if it's an AJAX request for status update or asset return
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null) {
        if ($is_ajax_request) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID permohonan tidak sah diberikan.']);
            exit();
        }
        $error_message = 'ID permohonan tidak sah diberikan.';
    } else {
        try {
            // Handle POST request for application status update (only if admin)
            if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id']) && isset($_POST['status_action'])) {
                $app_id_to_update = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
                $status_action = filter_input(INPUT_POST, 'status_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($app_id_to_update === $id && ($status_action === 'approved' || $status_action === 'rejected')) {
                    $pdo->beginTransaction();
                    try {
                        $stmtUpdate = $pdo->prepare("UPDATE applications SET status = :new_status, updated_at = CURRENT_TIMESTAMP WHERE application_id = :application_id");
                        $stmtUpdate->bindParam(':new_status', $status_action);
                        $stmtUpdate->bindParam(':application_id', $app_id_to_update, PDO::PARAM_INT);
                        $stmtUpdate->execute();

                        // If application is approved, update related assets' status to 'approved' if they are 'pending'
                        if ($status_action === 'approved') {
                            $stmtUpdateAssets = $pdo->prepare("UPDATE assets SET status = 'approved' WHERE application_id = :application_id AND status = 'pending'");
                            $stmtUpdateAssets->bindParam(':application_id', $app_id_to_update, PDO::PARAM_INT);
                            $stmtUpdateAssets->execute();
                        }

                        $pdo->commit();

                        $new_status_text_display = '';
                        $new_status_class = '';
                        switch ($status_action) {
                            case 'approved':
                                $new_status_text_display = 'Diluluskan';
                                $new_status_class = 'bg-green-600 text-white';
                                break;
                            case 'rejected':
                                $new_status_text_display = 'Ditolak';
                                $new_status_class = 'bg-red-600 text-white';
                                break;
                        }

                        if ($is_ajax_request) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'status' => 'success',
                                'message' => 'Status permohonan berjaya dikemas kini kepada ' . $new_status_text_display . '.',
                                'new_status_value' => $status_action,
                                'new_status_text' => $new_status_text_display,
                                'new_status_class' => $new_status_class
                            ]);
                            exit();
                        }
                        $success_message = 'Status permohonan berjaya dikemas kini kepada ' . $new_status_text_display . '.';

                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        if ($is_ajax_request) {
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'error', 'message' => 'Gagal mengemas kini status permohonan: ' . $e->getMessage()]);
                            exit();
                        }
                        $error_message = 'Gagal mengemas kini status permohonan: ' . $e->getMessage();
                    }
                } else {
                    if ($is_ajax_request) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Tindakan status tidak sah atau ID permohonan tidak sepadan.']);
                        exit();
                    }
                    $error_message = 'Tindakan status tidak sah atau ID permohonan tidak sepadan.';
                }
            }
            
            // Handle POST request for asset return (only if admin)
            if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_id_to_return']) && isset($_POST['actual_return_date'])) {
                $asset_id_to_return = filter_input(INPUT_POST, 'asset_id_to_return', FILTER_VALIDATE_INT);
                $actual_return_date = filter_input(INPUT_POST, 'actual_return_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $return_notes = filter_input(INPUT_POST, 'return_notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($asset_id_to_return && $actual_return_date) {
                    try {
                        $pdo->beginTransaction(); // Mulakan transaksi untuk kemas kini aset dan status permohonan

                        $stmtReturnAsset = $pdo->prepare("UPDATE assets SET actual_return_date = :actual_return_date, notes = :notes, status = 'returned' WHERE asset_id = :asset_id AND application_id = :application_id");
                        $stmtReturnAsset->bindParam(':actual_return_date', $actual_return_date);
                        $stmtReturnAsset->bindParam(':notes', $return_notes);
                        $stmtReturnAsset->bindParam(':asset_id', $asset_id_to_return, PDO::PARAM_INT);
                        $stmtReturnAsset->bindParam(':application_id', $id, PDO::PARAM_INT); // Ensure asset belongs to this application

                        if ($stmtReturnAsset->execute()) {
                            // Semak jika semua aset untuk permohonan ini telah dipulangkan
                            $stmtCheckAllReturned = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE application_id = :application_id AND status != 'returned'");
                            $stmtCheckAllReturned->bindParam(':application_id', $id, PDO::PARAM_INT);
                            $stmtCheckAllReturned->execute();
                            $remaining_unreturned_assets = $stmtCheckAllReturned->fetchColumn();

                            $application_updated_to_completed = false;
                            if ($remaining_unreturned_assets == 0) {
                                // Jika semua aset telah dipulangkan, kemas kini status permohonan utama
                                $stmtUpdateAppStatus = $pdo->prepare("UPDATE applications SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE application_id = :application_id AND status != 'completed'");
                                $stmtUpdateAppStatus->bindParam(':application_id', $id, PDO::PARAM_INT);
                                if ($stmtUpdateAppStatus->execute()) {
                                    $application_updated_to_completed = true;
                                }
                            }

                            $pdo->commit(); // Selesaikan transaksi

                            if ($is_ajax_request) {
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'status' => 'success',
                                    'message' => 'Aset berjaya dipulangkan.' . ($application_updated_to_completed ? ' Permohonan telah lengkap.' : ''),
                                    'asset_id' => $asset_id_to_return,
                                    'actual_return_date' => $actual_return_date,
                                    'notes' => $return_notes,
                                    'application_status_changed_to_completed' => $application_updated_to_completed // Hantar status ini ke JS
                                ]);
                                exit();
                            }
                            $success_message = 'Aset berjaya dipulangkan.' . ($application_updated_to_completed ? ' Permohonan telah lengkap.' : '');
                        } else {
                             $pdo->rollBack();
                             if ($is_ajax_request) {
                                header('Content-Type: application/json');
                                echo json_encode(['status' => 'error', 'message' => 'Gagal memulangkan aset.']);
                                exit();
                            }
                            $error_message = 'Gagal memulangkan aset.';
                        }
                    } catch (PDOException $e) {
                         $pdo->rollBack();
                         if ($is_ajax_request) {
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'error', 'message' => 'Ralat pangkalan data semasa memulangkan aset: ' . $e->getMessage()]);
                            exit();
                        }
                        $error_message = 'Ralat pangkalan data semasa memulangkan aset: ' . $e->getMessage();
                    }
                } else {
                    if ($is_ajax_request) {
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'error', 'message' => 'Data pemulangan aset tidak lengkap.']);
                        exit();
                    }
                    $error_message = 'Data pemulangan aset tidak lengkap.';
                }
            }


            // Fetch application data
            $stmtApp = $pdo->prepare("SELECT application_id, application_number, applicant_name, applicant_position, department, issuer_name, purpose, location_of_use, status, created_at, updated_at FROM applications WHERE application_id = :id");
            $stmtApp->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtApp->execute();
            $application = $stmtApp->fetch();

            if (!$application) {
                if ($is_ajax_request) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Permohonan tidak ditemui.']);
                    exit();
                }
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
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Ralat pangkalan data: ' . $e->getMessage()]);
                exit();
            }
            $error_message = 'Ralat pangkalan data: ' . $e->getMessage();
        }
    }
} else {
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Tiada ID permohonan dinyatakan.']);
        exit();
    }
    $error_message = 'Tiada ID permohonan dinyatakan.';
}

// Only include header/footer if it's not an AJAX request
if (!$is_ajax_request) {
    include_once 'includes/header.php';
}
?>

<?php if (!$is_ajax_request): // Only render HTML if not an AJAX request ?>
<div class="max-w-4xl mx-auto bg-white p-10 rounded-xl shadow-2xl border border-gray-200 transform transition-all duration-300 hover:scale-[1.01]">
    <h2 class="text-4xl font-bold text-gray-900 mb-8 text-center bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-700">Butiran Permohonan KEW.PA-9</h2>
    <p class="text-center text-sm text-gray-500 mb-6">No. Permohonan: <span class="font-bold text-indigo-700"><?php echo htmlspecialchars($application['application_number'] ?? 'N/A'); ?></span></p>

    <div class="p-2 mb-4 text-sm text-center <?php echo $isAdmin ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> rounded-md border">
        Status Log Masuk Admin: <?php echo $isAdmin ? 'Aktif' : 'Tidak Aktif'; ?>
    </div>

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

    <?php if ($application): ?>
        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-gray-800">
                <div><span class="font-semibold">Nama Pemohon:</span> <?php echo htmlspecialchars($application['applicant_name']); ?></div>
                <div><span class="font-semibold">Tujuan:</span> <?php echo htmlspecialchars($application['purpose']); ?></div>
                <div><span class="font-semibold">Jawatan:</span> <?php echo htmlspecialchars($application['applicant_position']); ?></div>
                <div><span class="font-semibold">Tempat Digunakan:</span> <?php echo htmlspecialchars($application['location_of_use']); ?></div>
                <div><span class="font-semibold">Bahagian:</span> <?php echo htmlspecialchars($application['department']); ?></div>
                <div><span class="font-semibold">Nama Pengeluar:</span> <?php echo htmlspecialchars($application['issuer_name'] ?: 'N/A'); ?></div>
                <div class="md:col-span-2">
                    <span class="font-semibold">Status Permohonan:</span>
                    <span id="application-status" class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full 
                        <?php
                        switch ($application['status']) {
                            case 'submitted': echo 'bg-blue-600 text-white'; break;
                            case 'approved': echo 'bg-green-600 text-white'; break;
                            case 'rejected': echo 'bg-red-600 text-white'; break;
                            case 'draft': echo 'bg-gray-600 text-white'; break;
                            case 'completed': echo 'bg-gray-600 text-white'; break; // Kelas baru untuk status 'completed'
                            default: echo 'bg-gray-600 text-white'; break;
                        }
                        ?>">
                        <?php
                            switch ($application['status']) {
                                case 'submitted': echo 'Dihantar'; break;
                                case 'approved': echo 'Diluluskan'; break;
                                case 'rejected': echo 'Ditolak'; break;
                                case 'draft': echo 'Draf'; break;
                                case 'completed': echo 'Lengkap'; break; // Teks baru untuk status 'completed'
                                default: echo ucfirst(htmlspecialchars($application['status'])); break;
                            }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Butiran Aset</h3>
            <?php if (!empty($assets)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-300 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bil.</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Siri Pendaftaran</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Keterangan Aset</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tarikh Dipinjam</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Dijangka Pulang</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status Aset</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tarikh Dipulangkan</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Catatan</th>
                                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $bil = 1; foreach ($assets as $asset): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo $bil++; ?>.</td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['description']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['loan_date']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['expected_return_date']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800">
                                        <span id="asset-status-<?php echo $asset['asset_id']; ?>" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php
                                            switch ($asset['status']) {
                                                case 'pending': echo 'bg-orange-100 text-orange-800'; break;
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                case 'returned': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?php
                                            switch ($asset['status']) {
                                                case 'pending': echo 'Menunggu'; break;
                                                case 'approved': echo 'Diluluskan'; break;
                                                case 'rejected': echo 'Ditolak'; break;
                                                case 'returned': echo 'Dipulangkan'; break;
                                                default: echo ucfirst(htmlspecialchars($asset['status'])); break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td id="actual-return-date-<?php echo $asset['asset_id']; ?>" class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['actual_return_date'] ?: 'Belum Dipulangkan'); ?></td>
                                    <td id="asset-notes-<?php echo $asset['asset_id']; ?>" class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($asset['notes'] ?: 'Tiada'); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-800">
                                        <?php if ($isAdmin && $asset['status'] === 'approved'): ?>
                                            <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white text-xs py-1 px-3 rounded-md shadow-sm return-asset-button"
                                                data-asset-id="<?php echo $asset['asset_id']; ?>"
                                                data-application-id="<?php echo $application['application_id']; ?>">
                                                Pulangkan Aset
                                            </button>
                                        <?php elseif ($asset['status'] === 'returned'): ?>
                                            <span class="text-green-600 font-semibold text-xs">Telah Dipulangkan</span>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600 text-center">Tiada butiran aset untuk permohonan ini.</p>
            <?php endif; ?>
        </div>

        <div class="border p-6 rounded-lg bg-gray-50 shadow-inner mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Tandatangan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-8 gap-x-8">
                <div class="flex flex-col items-center border border-gray-300 p-4 rounded-lg bg-white shadow-sm">
                    <p class="font-semibold text-gray-700 mb-2">Tandatangan Peminjam</p>
                    <?php if (isset($signatures['applicant']) && $signatures['applicant']['signature_data']): ?>
                        <img src="<?php echo htmlspecialchars($signatures['applicant']['signature_data']); ?>" alt="Tandatangan Peminjam" class="border border-gray-200 rounded-md bg-gray-50" style="max-width: 200px; max-height: 100px;">
                        <p class="text-sm text-gray-800 mt-2 font-medium"><?php echo htmlspecialchars($signatures['applicant']['name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($signatures['applicant']['position']); ?></p>
                        <p class="text-xs text-gray-500">Tarikh: <?php echo htmlspecialchars($signatures['applicant']['signature_date']); ?></p>
                    <?php else: ?>
                        <div class="h-24 w-full bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-sm text-gray-500 rounded-md">Tiada Tandatangan</div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col items-center border border-gray-300 p-4 rounded-lg bg-white shadow-sm">
                    <p class="font-semibold text-gray-700 mb-2">Tandatangan Pelulus</p>
                    <?php if (isset($signatures['approver']) && $signatures['approver']['signature_data']): ?>
                        <img src="<?php echo htmlspecialchars($signatures['approver']['signature_data']); ?>" alt="Tandatangan Pelulus" class="border border-gray-200 rounded-md bg-gray-50" style="max-width: 200px; max-height: 100px;">
                        <p class="text-sm text-gray-800 mt-2 font-medium"><?php echo htmlspecialchars($signatures['approver']['name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($signatures['approver']['position']); ?></p>
                        <p class="text-xs text-gray-500">Tarikh: <?php echo htmlspecialchars($signatures['approver']['signature_date']); ?></p>
                    <?php else: ?>
                        <div class="h-24 w-full bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-sm text-gray-500 rounded-md">Tiada Tandatangan</div>
                        <?php if ($isAdmin && ($application['status'] === 'approved' || $application['status'] === 'completed') && !isset($signatures['approver'])): ?>
                            <button type="button" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white text-sm py-2 px-4 rounded-lg shadow-md sign-button" data-role="approver" data-app-id="<?php echo $application['application_id']; ?>">
                                Tandatangan sebagai Pelulus
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col items-center border border-gray-300 p-4 rounded-lg bg-white shadow-sm">
                    <p class="font-semibold text-gray-700 mb-2">Tandatangan Pemulang</p>
                    <?php if (isset($signatures['returner']) && $signatures['returner']['signature_data']): ?>
                        <img src="<?php echo htmlspecialchars($signatures['returner']['signature_data']); ?>" alt="Tandatangan Pemulang" class="border border-gray-200 rounded-md bg-gray-50" style="max-width: 200px; max-height: 100px;">
                        <p class="text-sm text-gray-800 mt-2 font-medium"><?php echo htmlspecialchars($signatures['returner']['name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($signatures['returner']['position']); ?></p>
                        <p class="text-xs text-gray-500">Tarikh: <?php echo htmlspecialchars($signatures['returner']['signature_date']); ?></p>
                    <?php else: ?>
                        <div class="h-24 w-full bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-sm text-gray-500 rounded-md">Tiada Tandatangan</div>
                        <?php if ($isAdmin && ($application['status'] === 'approved' || $application['status'] === 'completed') && !isset($signatures['returner'])): ?>
                            <button type="button" class="mt-4 bg-yellow-600 hover:bg-yellow-700 text-white text-sm py-2 px-4 rounded-lg shadow-md sign-button" data-role="returner" data-app-id="<?php echo $application['application_id']; ?>">
                                Tandatangan sebagai Pemulang
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col items-center border border-gray-300 p-4 rounded-lg bg-white shadow-sm">
                    <p class="font-semibold text-gray-700 mb-2">Tandatangan Penerima</p>
                    <?php if (isset($signatures['receiver']) && $signatures['receiver']['signature_data']): ?>
                        <img src="<?php echo htmlspecialchars($signatures['receiver']['signature_data']); ?>" alt="Tandatangan Penerima" class="border border-gray-200 rounded-md bg-gray-50" style="max-width: 200px; max-height: 100px;">
                        <p class="text-sm text-gray-800 mt-2 font-medium"><?php echo htmlspecialchars($signatures['receiver']['name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($signatures['receiver']['position']); ?></p>
                        <p class="text-xs text-gray-500">Tarikh: <?php echo htmlspecialchars($signatures['receiver']['signature_date']); ?></p>
                    <?php else: ?>
                        <div class="h-24 w-full bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-sm text-gray-500 rounded-md">Tiada Tandatangan</div>
                        <?php if ($isAdmin && ($application['status'] === 'approved' || $application['status'] === 'completed') && !isset($signatures['receiver'])): ?>
                            <button type="button" class="mt-4 bg-orange-600 hover:bg-orange-700 text-white text-sm py-2 px-4 rounded-lg shadow-md sign-button" data-role="receiver" data-app-id="<?php echo $application['application_id']; ?>">
                                Tandatangan sebagai Penerima
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // Hanya tunjukkan tindakan pentadbir jika log masuk sebagai admin DAN status bukan 'completed'
        if ($isAdmin && ($application['status'] === 'submitted' || $application['status'] === 'approved' || $application['status'] === 'rejected')):
        ?>
        <div class="mt-10 pt-6 border-t border-gray-200">
            <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">Tindakan Pentadbir</h3>
            <form id="statusUpdateForm" method="POST" class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['application_id']); ?>">
                <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($application['status']); ?>">
                
                <?php if ($application['status'] === 'submitted'): ?>
                    <button type="submit" name="status_action" value="approved" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-green-300">
                        Luluskan Permohonan
                    </button>
                    <button type="submit" name="status_action" value="rejected" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-red-300">
                        Tolak Permohonan
                    </button>
                <?php elseif ($application['status'] === 'approved'): ?>
                    <p class="text-lg text-gray-700 font-medium text-center self-center">Status permohonan ini adalah <span class="font-bold text-green-600">Diluluskan</span>.</p>
                    <button type="submit" name="status_action" value="rejected" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-red-300">
                        Tolak Permohonan (Ubah Status)
                    </button>
                <?php elseif ($application['status'] === 'rejected'): ?>
                    <p class="text-lg text-gray-700 font-medium text-center self-center">Status permohonan ini adalah <span class="font-bold text-red-600">Ditolak</span>.</p>
                    <button type="submit" name="status_action" value="approved" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-green-300">
                        Luluskan Permohonan (Ubah Status)
                    </button>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <div class="mt-8 text-center flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
            <a href="dashboard.php" class="inline-block bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                Kembali ke Papan Pemuka
            </a>
            <a href="cetak.php?id=<?php echo htmlspecialchars($application['application_id']); ?>" target="_blank" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-gray-300">
                Cetak Borang (KEW.PA-9)
            </a>
        </div>
    <?php endif; ?>
</div>

<div id="signatureModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tandatangan sebagai <span id="modalRoleName" class="text-blue-600"></span></h3>
        <input type="hidden" id="modalApplicationId">
        <input type="hidden" id="modalSignatureRole">
        <div class="mb-4">
            <label for="modalSignerName" class="block text-gray-700 text-sm font-semibold mb-2">Nama</label>
            <input type="text" id="modalSignerName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
        </div>
        <div class="mb-4">
            <label for="modalSignerPosition" class="block text-gray-700 text-sm font-semibold mb-2">Jawatan</label>
            <input type="text" id="modalSignerPosition" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm">
        </div>
        <canvas id="modalSignatureCanvas" class="border border-gray-400 rounded-lg w-full bg-white cursor-crosshair" width="400" height="150"></canvas>
        <div class="mt-4 flex justify-between space-x-2">
            <button type="button" id="modalClearSignature" class="bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-4 rounded-lg shadow-md transition-all duration-200">
                Padam Tandatangan
            </button>
            <button type="button" id="modalSaveSignature" class="bg-green-600 hover:bg-green-700 text-white text-sm py-2 px-4 rounded-lg shadow-md transition-all duration-200">
                Simpan Tandatangan
            </button>
        </div>
        <button type="button" id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-2xl font-bold">
            &times;
        </button>
    </div>
</div>

<div id="returnAssetModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">Pulangkan Aset</h3>
        <input type="hidden" id="returnAssetId">
        <input type="hidden" id="returnApplicationId">
        <div class="mb-4">
            <label for="actualReturnDate" class="block text-gray-700 text-sm font-semibold mb-2">Tarikh Dipulangkan Sebenar <span class="text-red-500">*</span></label>
            <input type="date" id="actualReturnDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="mb-4">
            <label for="returnNotes" class="block text-gray-700 text-sm font-semibold mb-2">Catatan (Pilihan)</label>
            <textarea id="returnNotes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition duration-200 shadow-sm"></textarea>
        </div>
        <div class="mt-4 flex justify-between space-x-2">
            <button type="button" id="cancelReturn" class="bg-gray-500 hover:bg-gray-600 text-white text-sm py-2 px-4 rounded-lg shadow-md transition-all duration-200">
                Batal
            </button>
            <button type="button" id="confirmReturn" class="bg-green-600 hover:bg-green-700 text-white text-sm py-2 px-4 rounded-lg shadow-md transition-all duration-200">
                Sahkan Pemulangan
            </button>
        </div>
        <button type="button" id="closeReturnModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-2xl font-bold">
            &times;
        </button>
    </div>
</div>


<?php
include_once 'includes/footer.php';
?>
<?php endif; // This endif closes the main if (!$is_ajax_request): block ?>
